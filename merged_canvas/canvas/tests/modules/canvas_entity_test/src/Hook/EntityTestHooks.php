<?php

declare(strict_types=1);

namespace Drupal\canvas_entity_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines hooks for test entities.
 */
final class EntityTestHooks {

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() === 'entity_test_mulrev') {
      $key = $entity_type->getKey('revision');
      \assert(\is_string($key));
      // Make the revision key use a string data type.
      $fields[$key] = BaseFieldDefinition::create('string')
        ->setName($key)
        ->setTargetEntityTypeId('entity_test_mulrev')
        ->setLabel(new TranslatableMarkup('Revision ID, but a string'))
        ->setReadOnly(TRUE)
        ->setSetting('max_length', 36);
    }
  }

}
