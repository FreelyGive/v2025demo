<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Utility\TypedDataHelper;

trait ConfigComponentTreeTrait {

  /**
   * @param array{uuid: string, inputs: string|array, component_id: string, parent_uuid?: string, slot?: string} $value
   *
   * @return \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem
   */
  private function conjureFieldItemObject(array $value): ComponentTreeItem {
    $field_item = TypedDataHelper::conjureFieldItemObject(ComponentTreeItem::PLUGIN_ID);
    assert($field_item instanceof ComponentTreeItem);
    $field_item->setValue($value);
    return $field_item;
  }

}
