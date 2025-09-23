<?php

declare(strict_types=1);

namespace Drupal\canvas\Utility;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;

/**
 * @internal
 */
final readonly class TypedDataHelper {

  public static function conjureFieldItemObject(string $field_type): FieldItemInterface {
    $typed_data_manager = self::getTypedDataManger();
    $field_item_definition = $typed_data_manager->createDataDefinition("field_item:$field_type");
    $field_item = $typed_data_manager->createInstance("field_item:$field_type", [
      'name' => NULL,
      'parent' => NULL,
      'data_definition' => $field_item_definition,
    ]);
    assert($field_item instanceof FieldItemInterface);
    return $field_item;
  }

  private static function getTypedDataManger(): TypedDataManagerInterface {
    return \Drupal::typedDataManager();
  }

}
