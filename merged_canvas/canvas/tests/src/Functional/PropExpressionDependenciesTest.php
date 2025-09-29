<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests the most complex prop expression dependencies functionally.
 *
 * - Functional tests are most realistic, but are slow.
 * - Kernel tests risk simulating only a subset of reality, but are faster.
 *
 * This functional test then complements the much more complete kernel test
 * coverage to keep the kernel tests "honest".
 *
 * @see \Drupal\Tests\canvas\Kernel\PropExpressionDependenciesTest
 *
 * @group canvas
 */
class PropExpressionDependenciesTest extends FunctionalTestBase {

  use EntityReferenceFieldCreationTrait;
  use ImageFieldCreationTrait;
  use MediaTypeCreationTrait;
  use GenerateComponentConfigTrait;

  const SOME_IMAGE_COMPONENT_ID = 'sdc.canvas_test_sdc.image';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'system',
    'taxonomy',
    'text',
    'filter',
    'user',
    'file',
    'image',
    'media',
    'media_library',
    'views',
    'path',
    'canvas_test_sdc',
    // Ensure field type overrides are installed and hence testable.
    'canvas',
  ];

  protected $defaultTheme = 'stark';

  /**
   * Ensures multiple references are tested correctly.
   *
   * This is the functional test equivalent for the "Reference field type that
   * fetches a reference of a reference." test case in the kernel test coverage.
   */
  public function testIntermediateDependencies(): void {
    $image_uri = $this->getRandomGenerator()
      ->image(uniqid('public://') . '.png', '200x200', '400x400');
    $this->assertFileExists($image_uri);
    $file = File::create(['uri' => $image_uri]);
    $file->save();

    $media = Media::create([
      'bundle' => $this->createMediaType('image')->id(),
      'name' => 'Test image',
      'field_media_image' => $file,
    ]);
    $media->save();

    // Re-generate component config now that media types have been created.
    // @see \Drupal\canvas\Hook\ShapeMatchingHooks::mediaLibraryStoragePropShapeAlter()
    // @todo This should not be necessary anymore after https://www.drupal.org/i/3547579
    // @phpstan-ignore-next-line
    $expression_string = Component::load(self::SOME_IMAGE_COMPONENT_ID)->getComponentSource()->getDefaultExplicitInput()['image']['expression'];
    self::assertStringNotContainsString('entity:media', $expression_string);
    $this->generateComponentConfig();
    // @phpstan-ignore-next-line
    $expression_string = Component::load(self::SOME_IMAGE_COMPONENT_ID)->getComponentSource()->getDefaultExplicitInput()['image']['expression'];
    self::assertStringContainsString('entity:media', $expression_string);

    $page = Page::create([
      'title' => 'A simple page',
      'components' => [
        // An image: references a media item.
        [
          'uuid' => 'c990c4ee-341a-4f38-ab5d-e75b3de1fa1f',
          'component_id' => self::SOME_IMAGE_COMPONENT_ID,
          'component_version' => Component::load('sdc.canvas_test_sdc.image')?->getActiveVersion(),
          'inputs' => [
            'image' => [
              'target_id' => $media->id(),
            ],
          ],
        ],
      ],
    ]);
    $page->save();

    $item = $page->getComponentTree()->first();
    self::assertNotNull($item);

    $deps = $item->calculateFieldItemValueDependencies($page);
    self::assertArrayHasKey('content', $deps);

    self::assertSame([
      $file->getConfigDependencyName(),
      $media->getConfigDependencyName(),
    ], $deps['content']);
  }

}
