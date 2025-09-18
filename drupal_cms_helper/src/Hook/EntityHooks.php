<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\drupal_cms_helper\UrlField;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class EntityHooks {

  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $definitions = [];
    if ($entity_type->id() === 'node') {
      $definitions['url'] = BaseFieldDefinition::create('string')
        ->setReadOnly(TRUE)
        ->setComputed(TRUE)
        ->setClass(UrlField::class);
    }
    return $definitions;
  }

}
