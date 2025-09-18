<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldType;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\TypedData\TypedDataTrait;

/**
 * @phpstan-import-type ComponentTreeItemListArray from \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
 * @phpstan-import-type ComponentTreeItemArray from \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
 * @phpstan-import-type SingleComponentInputArray from \Drupal\canvas\Plugin\DataType\ComponentInputs
 */
trait ComponentTreeItemListInstantiatorTrait {

  use TypedDataTrait;

  /**
   * Instantiates a (dangling) Canvas component tree.
   */
  protected function createDanglingComponentTreeItemList(?FieldableEntityInterface $parent = NULL): ComponentTreeItemList {
    return self::staticallyCreateDanglingComponentTreeItemList($this->getTypedDataManager(), $parent);
  }

  /**
   * Instantiates a (dangling) Canvas component tree.
   */
  protected static function staticallyCreateDanglingComponentTreeItemList(TypedDataManagerInterface $typed_data_manager, ?FieldableEntityInterface $parent = NULL): ComponentTreeItemList {
    $list_definition = $typed_data_manager->createListDataDefinition('field_item:component_tree');
    \assert(\method_exists($list_definition, 'setCardinality'));
    $list_definition->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $item_list = $typed_data_manager->createInstance('list', [
      'name' => NULL,
      'parent' => $parent?->getTypedData(),
      'data_definition' => $list_definition,
    ]);
    assert($item_list instanceof ComponentTreeItemList);

    return $item_list;
  }

  /**
   * @phpstan-param ComponentTreeItemListArray $tree
   *
   * @return array<string, ComponentTreeItemArray>
   *
   * @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList::constructDepthFirstGraph()
   */
  protected static function generateComponentTreeKeys(array $tree): array {
    $graph = [];
    // First construct a graph so we can order the component instances (i.e.
    // items in a ComponentTreeItemList) based on their depth.
    foreach ($tree as $value) {
      \assert(\array_key_exists('uuid', $value));
      $uuid = $value['uuid'];
      if (!\array_key_exists($uuid, $graph)) {
        // Create the initial entry for this item in the graph.
        $graph[$uuid] = [
          // Children that reference this item.
          'edges' => [],
          // UUIDs of children keyed by slot name.
          'slot_children' => [],
        ];
      }
      $slot = $value['slot'] ?? NULL;
      if ($slot !== NULL) {
        // Store the slot this item is in.
        $graph[$uuid]['slot'] = $slot;
      }
      if (\array_key_exists('parent_uuid', $value) && $value['parent_uuid'] !== NULL) {
        $parent_uuid = $value['parent_uuid'];
        // Flag this item as a child of its parent.
        $graph[$parent_uuid]['edges'][$uuid] = TRUE;
        if ($slot !== NULL) {
          // And the slot that it lives in.
          $graph[$parent_uuid]['slot_children'][$slot][] = $uuid;
          // And the delta position it has in this slot.
          $graph[$uuid]['delta'] = \count($graph[$parent_uuid]['slot_children'][$slot]) - 1;
        }
      }
    }

    // Then sort the graph.
    $sorted_graph = (new Graph($graph))->searchAndSort();
    \uasort($sorted_graph, SortArray::sortByWeightElement(...));

    // Keep track of the component items by their UUID.
    /** @var array<string, ComponentTreeItemArray> $tree */
    $uuid_lookup = \array_combine(\array_column($tree, 'uuid'), $tree);
    $keyed_tree = [];
    $parent_key_lookup = [];

    // Loop over each vertex in the graph and construct a keyed array.
    $outer_delta = 0;
    foreach ($sorted_graph as $uuid => $graph) {
      // If this UUID is not in the lookup, it could mean that there is an
      // invalid parent_uuid, but that parent item does not exist in the tree.
      // Validation doesn't happen until after this, so we can't rely on it here.
      if (!\array_key_exists($uuid, $uuid_lookup)) {
        continue;
      }
      // Grab our item from the lookup.
      $item = $uuid_lookup[$uuid];
      if (!\array_key_exists('slot', $graph)) {
        // This is a top level slot, so we key by the outer-delta.
        $keyed_tree[(string) $outer_delta] = $item;
        // Record the key of this component for child components to use when
        // constructing their key.
        $parent_key_lookup[$uuid] = (string) $outer_delta;
        // Increment the delta so the next component with no parent appears in
        // sequence.
        $outer_delta++;
        continue;
      }
      \assert(\array_key_exists('reverse_paths', $graph));
      $parents = \array_keys($graph['reverse_paths']);
      \assert(\count($parents) > 0);
      // The parent UUID is the first item in the reverse path.
      $parent_uuid = \reset($parents);
      // Start with the key of our parent.
      $key = $parent_key_lookup[$parent_uuid] ?? '';
      // Then append the slot and our relative position (delta) in the slot.
      $key .= ':' . $graph['slot'] . ':' . $graph['delta'];
      // Store this key for any children to retrieve.
      $parent_key_lookup[$uuid] = $key;
      // Add this component to the keyed tree.
      $keyed_tree[$key] = $item;
    }

    // Order the items by the key.
    \ksort($keyed_tree);
    /** @var array<string, ComponentTreeItemArray> */
    return $keyed_tree;
  }

}
