<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;

/**
 * @group canvas
 */
class DefaultContentImportTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['canvas', 'canvas_test_sdc'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testImportDefaultContentWithCanvasData(): void {
    $finder = new Finder(__DIR__ . '/../../fixtures/default_content_export');
    $this->container->get(Importer::class)->importContent($finder);

    // The imported page should have some Canvas data.
    /** @var \Drupal\canvas\Entity\Page $page */
    $page = $this->container->get(EntityRepositoryInterface::class)
      ->loadEntityByUuid(Page::ENTITY_TYPE_ID, '20354d7a-e4fe-47af-8ff6-187bca92f3f7');
    $canvas_field = $page->get('components')->first();
    $this->assertInstanceOf(ComponentTreeItem::class, $canvas_field);
    $this->assertFalse($canvas_field->isEmpty());
  }

}
