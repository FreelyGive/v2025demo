<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;

/**
 * Defines an interface for entities that store a component tree.
 *
 * @phpstan-import-type ComponentTreeItemListArray from \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
 */
interface ComponentTreeEntityInterface extends EntityInterface {

  /**
   * Gets the component tree stored by this entity.
   *
   * @return \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
   *   One (dangling) component tree.
   */
  public function getComponentTree(): ComponentTreeItemList;

  /**
   * @phpstan-param ComponentTreeItemListArray $values
   *   The list of component instances that together form the component tree.
   *
   * @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
   * @see docs/data-model.md#3.1.2
   */
  public function setComponentTree(array $values): self;

}
