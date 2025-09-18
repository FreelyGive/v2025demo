<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\canvas\Entity\Page;
use Drupal\canvas\Storage\ComponentTreeLoader;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\canvas\Kernel\Traits\VfsPublicStreamUrlTrait;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;

/**
 * @coversDefaultClass \Drupal\canvas\Storage\ComponentTreeLoader
 *
 * @group canvas
 */
class ComponentTreeLoaderTest extends KernelTestBase {

  use VfsPublicStreamUrlTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['system']);
    (new CanvasTestSetup())->setup();
  }

  public function testGetCanvasFieldName(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => '5 amazing uses for old toothbrushes',
    ]);
    /** @var \Drupal\canvas\Storage\ComponentTreeLoader $loader */
    $loader = $this->container->get(ComponentTreeLoader::class);
    $this->assertEquals('field_canvas_demo', $loader->load($node)->getFieldDefinition()->getName());
    $page = Page::create([
      'title' => 'My page',
    ]);
    $this->assertEquals('components', $loader->load($page)->getFieldDefinition()->getName());
  }

  public function testEntityBundleRestriction(): void {
    $page_type = NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $page_type->save();
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
    ]);
    $node->save();
    $this->expectException(\LogicException::class);
    // @todo Fix in https://drupal.org/i/3498525 for testing a bundle where a
    //   canvas field is not present.
    // @see \Drupal\canvas\Storage\ComponentTreeLoader::getCanvasFieldName
    $this->expectExceptionMessage('For now Canvas only works if the entity is a canvas_page! Other entity types and bundles must use content templates for now, see https://drupal.org/i/3498525');
    /** @var \Drupal\canvas\Storage\ComponentTreeLoader $component_tree_loader */
    $component_tree_loader = $this->container->get(ComponentTreeLoader::class);
    $component_tree_loader->load($node);
  }

  public function testMissingCanvasField(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => '5 amazing uses for old toothbrushes',
    ]);
    $node->save();
    FieldStorageConfig::loadByName('node', 'field_canvas_demo')?->delete();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    // Reload the node to refresh field definitions.
    $node = Node::load($node->id());
    self::assertNotNull($node);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('This entity does not have an Canvas field!');
    /** @var \Drupal\canvas\Storage\ComponentTreeLoader $component_tree_loader */
    $component_tree_loader = $this->container->get(ComponentTreeLoader::class);
    $component_tree_loader->load($node);
  }

}
