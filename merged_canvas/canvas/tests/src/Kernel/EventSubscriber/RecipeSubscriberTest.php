<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\EventSubscriber;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\Tests\canvas\Kernel\Traits\VfsPublicStreamUrlTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\CrawlerTrait;

/**
 * @group canvas
 * @group #slow
 * @covers \Drupal\canvas\EventSubscriber\RecipeSubscriber
 * @covers \Drupal\canvas\Plugin\Field\FieldTypeOverride\EntityReferenceItemOverride
 */
final class RecipeSubscriberTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use RecipeTestTrait;
  use CrawlerTrait;
  use VfsPublicStreamUrlTrait;

  private const string FIXTURES_DIR = __DIR__ . '/../../../fixtures/recipes';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up the basic stuff needed for Canvas to work.
    $recipe = Recipe::createFromDirectory(self::FIXTURES_DIR . '/base');
    RecipeRunner::processRecipe($recipe);
  }

  public function testComponentsAndDefaultContentAvailableOnRecipeApply(): void {
    // The recipe should apply without errors, because the components used by
    // the content should be available by the time the content is imported.
    $recipe = Recipe::createFromDirectory(self::FIXTURES_DIR . '/test_site');
    RecipeRunner::processRecipe($recipe);

    // Components should have been created.
    $this->assertInstanceOf(Component::class, Component::load('sdc.canvas_test_sdc.grid-container'));
    $this->assertInstanceOf(Component::class, Component::load('block.system_menu_block.admin'));

    // Demo Canvas field should have been created.
    $this->assertArrayHasKey('node.article.field_canvas_demo', FieldConfig::loadMultiple());
    $this->assertSame([
      'type' => 'canvas_naive_render_sdc_tree',
      'label' => 'hidden',
      'settings' => [],
      'third_party_settings' => [],
      'weight' => -2,
      'region' => 'content',
    ], EntityViewDisplay::load('node.article.default')?->getComponent('field_canvas_demo'));

    // Demo content should have been created.
    $this->assertSame([
      1 => ['Homepage', '/homepage'],
      2 => ['Empty Page', '/test-page'],
      3 => ['Page without a path', NULL],
    ], array_map(
      // @phpstan-ignore-next-line
      fn (Page $page) => [$page->label(), $page->get('path')->alias],
      Page::loadMultiple()
    ));
    $this->assertSame('/homepage', $this->config('system.site')->get('page.front'));
  }

  public function testEntityReferencesInDefaultContentComponents(): void {
    $image_uri = $this->getRandomGenerator()
      ->image('public://test.png', '100x100', '200x200');
    $file = File::create(['uri' => $image_uri]);
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'field_media_image' => $file->id(),
    ]);
    $media->save();
    $this->assertSame('1', $media->id());

    // The default content of the test_site recipe contains a component that
    // references a media item by UUID and serial ID (1). When the content is
    // imported, the UUID should "win" and be used to resolve the reference.
    $recipe = Recipe::createFromDirectory(self::FIXTURES_DIR . '/test_site');
    RecipeRunner::processRecipe($recipe);

    $node = $this->container->get(EntityRepositoryInterface::class)
      ->loadEntityByUuid('node', 'c66664af-53b9-42f4-a0ca-8ecc9edacb8c');
    $this->assertInstanceOf(FieldableEntityInterface::class, $node);
    $canvas_field = $node->get('field_canvas_demo');
    assert($canvas_field instanceof ComponentTreeItemList);
    $inputs = $canvas_field
      ->getComponentTreeItemByUuid('348bfa10-af72-49cd-900b-084d617c87df')
      ?->getInputs();
    $this->assertIsArray($inputs);
    // We should only store the target_uuid as the input is collapsed.
    $media_uuid = '346210de-12d8-4d02-9db4-455f1bdd99f7';
    self::assertEquals(['image' => ['target_uuid' => $media_uuid]], $inputs);
    $output = $canvas_field->toRenderable($node);
    $crawler = $this->crawlerForRenderArray($output);
    $media = \Drupal::service(EntityRepositoryInterface::class)->loadEntityByUuid('media', $media_uuid);
    self::assertInstanceOf(MediaInterface::class, $media);
    $file = $media->get('field_media_image')->entity;
    \assert($file instanceof FileInterface);
    $file_url = \Drupal::service(FileUrlGeneratorInterface::class)->generate($file->getFileUri() ?? '')->toString();
    // But the rendered output should contain the actual image referenced by the
    // target_uuid.
    self::assertStringContainsString(\urldecode($file_url), $crawler->filter('img')->attr('src') ?? '');
    $this->assertGreaterThan(1, $media->id());
  }

  public function testComponentConfigActions(): void {
    $recipe = $this->createRecipe(<<<YAML
name: Disable components
type: Testing
install:
  - canvas
  - stark
  - canvas_stark
config:
  import:
    canvas: '*'
  actions:
    canvas.component.sdc.canvas.*:
      disable: []
YAML
    );
    RecipeRunner::processRecipe($recipe);

    $components = Component::loadMultiple();
    $this->assertNotEmpty($components);
    // All Component config entities must be `status: true`, except this one.
    self::assertGreaterThanOrEqual(2, count($components));
    foreach ($components as $id => $component) {
      $this->assertSame(
        !str_contains($id, 'sdc.canvas.'),
        $component->status(),
      );
    }
  }

}
