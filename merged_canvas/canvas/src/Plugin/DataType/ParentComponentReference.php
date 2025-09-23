<?php

namespace Drupal\canvas\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\DataReferenceBase;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;

/**
 * Defines a data type that resolves to the parent component tree item.
 *
 * This serves as 'parent_component' property of component tree item field items and
 * gets its value set from the parent, i.e. ComponentTreeItem.
 *
 * @property \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem $parent
 * @property ?\Drupal\Core\TypedData\TypedDataInterface $target
 */
#[DataType(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup("Parent component tree item reference"),
  definition_class: DataReferenceDefinition::class,
)]
final class ParentComponentReference extends DataReferenceBase {

  /**
   * Core expects this to be in the format of {data_type}_reference.
   *
   * @see \Drupal\Core\TypedData\DataReferenceDefinition::create
   */
  public const string PLUGIN_ID = 'field_item:' . ComponentTreeItem::PLUGIN_ID . '_reference';

  /**
   * The UUID of the parent component instance in the component tree.
   *
   * @var ?string
   */
  protected ?string $parentUuid = NULL;

  /**
   * {@inheritdoc}
   */
  public function getTarget(): ?ComponentTreeItem {
    // If we have a valid reference, return the field item.
    if ($this->target === NULL && $this->parentUuid !== NULL) {
      $list = $this->parent->parent;
      \assert($list instanceof ComponentTreeItemList);
      $this->target = $list->getComponentTreeItemByUuid($this->parentUuid);
    }
    // TRICKY: the target may no longer exist.
    // @todo Clarify in https://www.drupal.org/i/3524406
    /** @var ?\Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem */
    return $this->target;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetIdentifier(): ?string {
    return $this->getTarget()?->getUuid();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    unset($this->target);
    unset($this->parentUuid);

    // Both the parent UUID and the field item may be passed as value. The
    // reference may also be unset by passing NULL as value.
    if ($value === NULL) {
      $this->target = NULL;
      $this->parentUuid = NULL;
      $this->doNotify($notify);
      return;
    }
    if ($value instanceof ComponentTreeItem) {
      $this->target = $value;
      $this->parentUuid = $value->getUuid();
      $this->doNotify($notify);
      return;
    }
    if (!is_string($value)) {
      throw new \InvalidArgumentException('Value is not a valid parent component tree item.');
    }
    $this->parentUuid = $value;
    $this->target = NULL;
    $this->doNotify($notify);
  }

  private function doNotify(bool $notify): void {
    // Notify the parent of any changes.
    if ($notify && $this->parent !== NULL) {
      $this->parent->onChange($this->name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getString(): ?string {
    return $this->parentUuid;
  }

}
