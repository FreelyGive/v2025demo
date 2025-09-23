<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\canvas\Plugin\Validation\Constraint\StringSemanticsConstraint;
use Drupal\canvas\TypedData\LinkUrl;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * @todo Fix upstream.
 */
class LinkItemOverride extends LinkItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['title']->addConstraint('StringSemantics', StringSemanticsConstraint::PROSE);
    $properties['url'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Resolved URL'))
      ->setDescription(new TranslatableMarkup('The resolved URL for this link, that can be navigated to by a web browser.'))
      ->setComputed(TRUE)
      ->setClass(LinkUrl::class);
    return $properties;
  }

}
