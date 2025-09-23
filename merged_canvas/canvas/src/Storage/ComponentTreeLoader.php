<?php

declare(strict_types=1);

namespace Drupal\canvas\Storage;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;

/**
 * Handles loading a component tree from entities.
 */
final class ComponentTreeLoader {

  public function __construct(
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Loads a component tree from an entity.
   *
   * @param \Drupal\canvas\Entity\ComponentTreeEntityInterface|\Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity that stores the component tree. If it does not specifically
   *   implement ComponentTreeEntityInterface, then it is expected to be a
   *   fieldable entity with at least one field that stores a component tree.
   *
   * @return \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
   */
  public function load(ComponentTreeEntityInterface|FieldableEntityInterface $entity): ComponentTreeItemList {
    if ($entity instanceof ComponentTreeEntityInterface) {
      return $entity->getComponentTree();
    }
    $field_name = $this->getCanvasFieldName($entity);
    $item = $entity->get($field_name);
    assert($item instanceof ComponentTreeItemList);
    return $item;
  }

  /**
   * Gets the Canvas field name from the entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The Canvas field name, or throws an exception
   *   if not found or not supported entity type/bundle.
   *
   * @throws \LogicException
   */
  public function getCanvasFieldName(FieldableEntityInterface $entity): string {
    // @todo Remove this restriction once other entity types and bundles are
    //   allowed in https://drupal.org/i/3498525.
    $articles_allowed_only_on_tests = $entity->getEntityTypeId() === 'node' && $entity->bundle() === 'article' && drupal_valid_test_ua();
    if ($entity->getEntityTypeId() !== Page::ENTITY_TYPE_ID && !$articles_allowed_only_on_tests) {
      throw new \LogicException('For now Canvas only works if the entity is a canvas_page! Other entity types and bundles must use content templates for now, see https://drupal.org/i/3498525');
    }

    $map = $this->entityFieldManager->getFieldMapByFieldType(ComponentTreeItem::PLUGIN_ID);

    foreach ($map[$entity->getEntityTypeId()] ?? [] as $field_name => $info) {
      if (in_array($entity->bundle(), $info['bundles'], TRUE)) {
        return $field_name;
      }
    }
    throw new \LogicException("This entity does not have an Canvas field!");
  }

}
