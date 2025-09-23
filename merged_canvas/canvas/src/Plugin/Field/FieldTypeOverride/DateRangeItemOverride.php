<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;

/**
 * @todo Fix upstream.
 */
class DateRangeItemOverride extends DateRangeItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['start_date']->setInternal(TRUE);
    $properties['end_date']->setInternal(TRUE);
    return $properties;
  }

}
