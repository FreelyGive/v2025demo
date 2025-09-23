<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\canvas\Plugin\Validation\Constraint\StringSemanticsConstraint;
use Drupal\text\Plugin\Field\FieldType\TextLongItem;

/**
 * @todo Fix upstream.
 *
 * Adds StringSemantics constraint to the 'processed' property to handle rich
 * text content with proper semantic typing.
 */
class TextLongItemOverride extends TextLongItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['processed']
      // It is computed from the required `value` property, so this value can be
      // considered required, too.
      ->setRequired(TRUE)
      ->addConstraint('StringSemantics', StringSemanticsConstraint::MARKUP);
    // Convey to schema-matching systems like Drupal Canvas to deduce that
    // only `processed` contains actually relevant information for humans.
    $properties['format']->setSetting('is source for', 'processed');
    $properties['value']->setSetting('is source for', 'processed');
    return $properties;
  }

}
