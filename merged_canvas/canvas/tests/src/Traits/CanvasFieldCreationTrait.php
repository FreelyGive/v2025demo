<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

trait CanvasFieldCreationTrait {

  protected function createComponentTreeField(string $entity_type_id, string $bundle, string $field_name): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'type' => 'component_tree',
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
    ])->save();
  }

}
