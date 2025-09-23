<?php

namespace Drupal\canvas\Entity;

use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * @phpstan-import-type ComponentTreeItemArray from \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
 */
abstract class ComponentTreeConfigEntityBase extends ConfigEntityBase implements ComponentTreeEntityInterface {

  use ComponentTreeItemListInstantiatorTrait;

  /**
   * The component tree.
   *
   * @var ?array<string, ComponentTreeItemArray>
   */
  protected ?array $component_tree;

  public function setComponentTree(array $values): static {
    $this->set('component_tree', $values);
    return $this;
  }

}
