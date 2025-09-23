<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Entity;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Controller\EntityFormController;
use Drupal\canvas\Entity\Page;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\canvas\Kernel\Traits\PageTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @group canvas
 * @requires function Drupal\metatag\MetatagManager::tagsFromEntity
 */
final class PageMetatagIntegrationTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use PageTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'block',
    'sdc',
    'sdc_test',
    'canvas_test_sdc',
    // Modules providing field types + widgets for the SDC Components'
    // `prop_field_definitions`.
    'file',
    'image',
    'options',
    'link',
    'system',
    ...self::PAGE_TEST_MODULES,
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installPageEntitySchema();
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');
    $this->installEntitySchema('user');
  }

  public function testTags(): void {
    self::assertArrayNotHasKey(
      'metatags',
      $this->container->get('entity_field.manager')
        ->getFieldDefinitions(Page::ENTITY_TYPE_ID, Page::ENTITY_TYPE_ID)
    );
    $this->container->get('module_installer')->install(['metatag']);
    self::assertArrayHasKey(
      'metatags',
      $this->container->get('entity_field.manager')
        ->getFieldDefinitions(Page::ENTITY_TYPE_ID, Page::ENTITY_TYPE_ID)
    );
    $changes = $this->container->get('entity.definition_update_manager')->getChangeList();
    self::assertArrayNotHasKey(Page::ENTITY_TYPE_ID, $changes);

    $media_type = $this->createMediaType('image');
    $image_file = File::create([
      // @phpstan-ignore-next-line
      'uri' => $this->getTestFiles('image')[0]->uri,
    ]);
    $image_file->save();
    $media_image = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Test image',
      'field_media_image' => [
        'target_id' => $image_file->id(),
        'alt' => 'default alt',
        'title' => 'default title',
      ],
    ]);
    $media_image->save();

    $sut = Page::create([
      'title' => 'Test page',
      'description' => 'This is a test page.',
      'path' => ['alias' => '/test-page'],
      'components' => [],
      'image' => $media_image->id(),
    ]);
    self::assertSaveWithoutViolations($sut);

    self::assertMetatags($sut, [
      [
        [
          '#tag' => 'meta',
          '#attributes' => [
            'name' => 'title',
            'content' => 'Test page |',
          ],
        ],
        'title',
      ],
      [
        [
          '#tag' => 'meta',
          '#attributes' => [
            'name' => 'description',
            'content' => 'This is a test page.',
          ],
        ],
        'description',
      ],
      [
        [
          '#tag' => 'link',
          '#attributes' => [
            'rel' => 'canonical',
            'href' => '/test-page',
          ],
        ],
        'canonical_url',
      ],
      [
        [
          '#tag' => 'link',
          '#attributes' => [
            'rel' => 'image_src',
            'href' => $image_file->createFileUrl(FALSE),
          ],
        ],
        'image_src',
      ],
    ]);
  }

  private static function assertMetatags(Page $page, array $expected): void {
    $metatags = metatag_get_tags_from_route($page);
    self::assertEquals($expected, $metatags['#attached']['html_head']);
  }

  public function testSeoSettingsForm(): void {
    \Drupal::service(ThemeInstallerInterface::class)->install(['canvas_stark']);
    $this->container->get('module_installer')->install(['metatag']);
    $page = Page::create([
      'title' => 'Test page',
      'description' => 'This is a test page.',
      'path' => ['alias' => '/test-page'],
      'components' => [],
    ]);
    self::assertSaveWithoutViolations($page);
    $themeHandler = $this->container->get('theme_handler');
    assert($themeHandler instanceof ThemeHandlerInterface);
    $sut = new EntityFormController($this->container->get(AutoSaveManager::class), $this->container->get(RequestStack::class), $themeHandler);
    $form = $sut->form(Page::ENTITY_TYPE_ID, $page, 'default');
    self::assertArrayHasKey('image', $form['seo_settings']);
    self::assertArrayHasKey('description', $form['seo_settings']);
    self::assertEquals('seo_settings', $form['metatags']['widget'][0]['basic']['title']['#group']);
  }

}
