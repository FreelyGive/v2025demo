<?php

declare(strict_types=1);

namespace Drupal\canvas_test_validation\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Defines hooks for test entities.
 */
final class EntityTestHooks {

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() === 'node') {
      \assert(isset($fields['title']));
      $title_field = $fields['title'];
      assert($title_field instanceof BaseFieldDefinition);
      $title_field->addConstraint('canvas_test_validation_unique_field');
    }
  }

}
