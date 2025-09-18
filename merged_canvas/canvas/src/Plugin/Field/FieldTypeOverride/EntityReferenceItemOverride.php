<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem as CoreEntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Overrides generic entity reference items.
 *
 * This allows components in default content to use a `target_uuid` property
 * transparently and have it translated to a `target_id`.
 */
final class EntityReferenceItemOverride extends CoreEntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $definitions = parent::propertyDefinitions($field_definition);

    $definitions['target_uuid'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Target UUID'))
      ->setRequired(FALSE)
      ->addConstraint('Uuid');

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE): void {
    if ($property_name === 'target_uuid' && empty($this->target_id)) {
      $this->set('target_id', $this->get('target_uuid')->getValue());
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE): void {
    if (\is_array($values) && isset($values['target_uuid'])) {
      $values['target_id'] = $this->getTargetId($values['target_uuid']);
    }
    parent::setValue($values, $notify);
  }

  private function getTargetId(string $uuid): int|string|null {
    $target_type = $this->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSetting('target_type');

    return \Drupal::service(EntityRepositoryInterface::class)
      ->loadEntityByUuid($target_type, $uuid)
      ?->id();
  }

}
