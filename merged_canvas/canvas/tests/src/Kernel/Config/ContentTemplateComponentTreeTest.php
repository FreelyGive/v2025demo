<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests the component tree aspects of the ContentTemplate config entity type.
 *
 * @group canvas
 * @coversDefaultClass \Drupal\canvas\Entity\PageRegion
 */
final class ContentTemplateComponentTreeTest extends ConfigWithComponentTreeTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
  ];

  /**
   * {@inheritdoc}
   *
   * @todo Add expected violation for missing dynamic prop source in
   *   https://drupal.org/i/3541054.
   */
  protected static $expectedViolations = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installConfig('node');
    $this->installConfig('canvas');
    $this->createContentType(['type' => 'alpha']);
    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'type' => 'text',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'bundle' => 'alpha',
      'label' => 'Test field',
    ])->save();
    $this->generateComponentConfig();
    $this->entity = ContentTemplate::create([
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'alpha',
      'content_entity_type_view_mode' => 'full',
      'component_tree' => [],
    ]);
  }

}
