<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Entity;

use Drupal\canvas\Entity\Page;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\PageTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * @group canvas
 */
final class PageTest extends KernelTestBase {

  use GenerateComponentConfigTrait;
  use MediaTypeCreationTrait;
  use PageTrait;

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
    $this->generateComponentConfig();
    $this->installPageEntitySchema();
  }

  public function testDefinition(): void {
    $sut = $this->container->get('entity_type.manager')
      ->getDefinition(Page::ENTITY_TYPE_ID);
    self::assertNotNull($sut);
    self::assertEquals(
      [
        'canonical' => '/page/{canvas_page}',
        'delete-form' => '/page/{canvas_page}/delete',
        'edit-form' => '/canvas/editor/canvas_page/{canvas_page}',
        'revision-delete-form' => '/page/{canvas_page}/revisions/{canvas_page_revision}/delete',
        'revision-revert-form' => '/page/{canvas_page}/revisions/{canvas_page_revision}/revert',
        'version-history' => '/page/{canvas_page}/revisions',
      ],
      $sut->getLinkTemplates()
    );
  }

  public function testImageFieldDefinition(): void {
    $image_media_type = $this->createMediaType('image');
    // Create a `file` media type to ensure that the field definition is
    // correctly filtered to only allow media types that use `image`.
    $this->createMediaType('file');

    $fields = $this->container->get('entity_field.manager')
      ->getFieldDefinitions(Page::ENTITY_TYPE_ID, Page::ENTITY_TYPE_ID);
    self::assertArrayHasKey('image', $fields);
    $field = $fields['image'];
    self::assertEquals([
      'target_type' => 'media',
      'handler' => 'default',
      'handler_settings' => [
        'target_bundles' => [$image_media_type->id()],
      ],
    ], $field->getSettings());
    self::assertEquals([
      'type' => 'media_library_widget',
      'settings' => [
        'media_types' => [],
      ],
    ], $field->getDisplayOptions('form'));

    // Verify adding a new media type causes the base field's settings to be
    // automatically updated.
    $second_image_media_type = $this->createMediaType('image');
    $fields = $this->container->get('entity_field.manager')
      ->getFieldDefinitions(Page::ENTITY_TYPE_ID, Page::ENTITY_TYPE_ID);
    self::assertArrayHasKey('image', $fields);
    $field = $fields['image'];
    self::assertEqualsCanonicalizing([
      'target_type' => 'media',
      'handler' => 'default',
      'handler_settings' => [
        'target_bundles' => [$image_media_type->id(), $second_image_media_type->id()],
      ],
    ], $field->getSettings());

  }

  public function testEntity(): void {
    $test_heading_text = $this->randomString();

    $sut = Page::create([
      'title' => 'Test page',
      'description' => 'This is a test page.',
      'path' => ['alias' => '/test-page'],
      'components' => [
        [
          'uuid' => '09365c2d-1ee1-47fd-b5a3-7e4f34866186',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'inputs' => ['heading' => $test_heading_text],
        ],
        [
          'uuid' => 'af5fc5ab-1457-4258-880f-541a69c0110b',
          'component_id' => 'block.system_branding_block',
          'inputs' => [
            'use_site_logo' => TRUE,
            'use_site_name' => TRUE,
            'use_site_slogan' => TRUE,
            'label_display' => FALSE,
            'label' => '',
          ],
        ],
      ],
    ]);
    self::assertSaveWithoutViolations($sut);
    self::assertEquals('Test page', $sut->label());
    self::assertEquals('This is a test page.', $sut->description->value);
    self::assertEquals('/test-page', $sut->get('path')->first()?->getValue()['alias']);

    $components = $sut->get('components');
    $this->assertInstanceOf(ComponentTreeItemList::class, $components);
    $hydrated_value = \Closure::bind(function () {
      return $this->getHydratedTree();
    }, $components, $components)();
    self::assertEquals(
      [
        ComponentTreeItemList::ROOT_UUID => [
          '09365c2d-1ee1-47fd-b5a3-7e4f34866186' => [
            'component' => 'sdc.canvas_test_sdc.props-slots',
            'props' => [
              'heading' => $test_heading_text,
            ],
            'slots' => [
              'the_body' => '<p>Example value for <strong>the_body</strong> slot in <strong>prop-slots</strong> component.</p>',
              'the_footer' => 'Example value for <strong>the_footer</strong>.',
              'the_colophon' => '',
            ],
          ],
          'af5fc5ab-1457-4258-880f-541a69c0110b' => [
            'component' => 'block.system_branding_block',
            'settings' => [
              'use_site_logo' => TRUE,
              'use_site_name' => TRUE,
              'use_site_slogan' => TRUE,
              'label_display' => FALSE,
              'label' => '',
            ],
          ],
        ],
      ],
      $hydrated_value->getTree(),
    );
    // See \Drupal\Tests\canvas\Kernel\Plugin\Field\FieldType\ComponentTreeItemTest and
    // \Drupal\Tests\canvas\Unit\PropExpressionTest for extended test coverage,
    // which combined with \Drupal\Tests\canvas\Kernel\PropSourceTest::testDynamicPropSource,
    // does already prove that this will work correctly for EVERYTHING.
    $dependencies = $components->calculateDependencies();
    $this->assertSame([
      'canvas.component.sdc.canvas_test_sdc.props-slots',
      'canvas.component.block.system_branding_block',
    ], $dependencies['config']);
    $this->assertSame([], $dependencies['content']);
    $this->assertSame([], $dependencies['module']);
    $this->assertSame([], $dependencies['theme']);
  }

}
