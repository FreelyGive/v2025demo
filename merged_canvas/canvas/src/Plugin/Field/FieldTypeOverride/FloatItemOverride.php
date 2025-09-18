<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\FloatItem;

/**
 * @todo Fix upstream.
 */
class FloatItemOverride extends FloatItem {

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    // Prevent confusing sample values that look like integers. Instead, return
    // a floating point number that *everyone* knows: pi.
    return [
      'value' => 3.14,
    ];
  }

}
