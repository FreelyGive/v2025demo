<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\OptionsProviderInterface;

/**
 * A variant of BaseFieldDefinition that is for pure field storage definitions.
 *
 * @todo Remove this after https://www.drupal.org/node/2280639 is fixed.
 * @see \Drupal\canvas\PropSource\StaticPropSource::conjureFieldItem()
 * @see \Drupal\entity_test\FieldStorageDefinition
 *
 * @internal
 */
final class FieldStorageDefinition extends BaseFieldDefinition {

  /**
   * {@inheritdoc}
   */
  public function isBaseField() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptionsProvider($property_name, FieldableEntityInterface $entity) {
    // If the field item class implements the interface, create an orphaned
    // runtime item object, so that it can be used as the options provider
    // without modifying the entity being worked on.
    $field_item_definition = $this->getItemDefinition();
    if (is_subclass_of($field_item_definition->getClass(), OptionsProviderInterface::class)) {
      // @phpstan-ignore-next-line
      return \Drupal::typedDataManager()->createInstance($field_item_definition->getDataType(), [
        'name' => $property_name,
        'parent' => new FieldItemList($this, $property_name, EntityAdapter::createFromEntity($entity)),
        'data_definition' => $field_item_definition,
      ]);
    }
    return NULL;
  }

}
