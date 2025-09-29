<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldType;

use Drupal\Component\Graph\Graph;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderableInterface;
use Drupal\canvas\ComponentSource\ComponentSourceInterface;
use Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface;
use Drupal\canvas\ComponentSource\ComponentSourceWithSwitchCasesInterface;
use Drupal\canvas\Element\RenderSafeComponentContainer;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\Exception\SubtreeInjectionException;
use Drupal\canvas\HydratedTree;
use Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraint;

/**
 * A component tree: a list item class for ComponentTreeItem.
 *
 * @phpstan-import-type OptimizedSingleComponentInputArray from \Drupal\canvas\Plugin\DataType\ComponentInputs
 * @phpstan-type ComponentTreeItemArray array{'uuid': string, 'component_id': string, 'parent_uuid'?: string, 'slot'?: string, inputs: OptimizedSingleComponentInputArray}
 * @phpstan-type ComponentTreeItemListArray array<int, ComponentTreeItemArray>
 * @phpstan-type ExposedSlotDefinitions array<string, array{'component_uuid': string, 'slot_name': string, 'label': string}>
 */
final class ComponentTreeItemList extends FieldItemList implements RenderableInterface, CacheableDependencyInterface, DependentPluginInterface {

  // @todo Remove in https://drupal.org/i/3495625
  public const string ROOT_UUID = 'a548b48d-58a8-4077-aa04-da9405a6f418';

  /**
   * @var null|array<string, array{'edges': array<string, TRUE>}>
   */
  protected ?array $graph = NULL;

  public function first() : ?ComponentTreeItem {
    // @phpstan-ignore-next-line
    return parent::first();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() : array {
    $dependencies = [];
    foreach ($this as $item) {
      \assert($item instanceof ComponentTreeItem);
      $dependencies = NestedArray::mergeDeep($dependencies, $item->calculateFieldItemValueDependencies(
        $this->getParent()
          ? $this->getEntity()
          // Support dangling component trees.
          // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait
          : NULL
      ));
    }
    return $dependencies;
  }

  /**
   * @todo Move this into a normalizer at https://www.drupal.org/i/3499632
   */
  public function getClientSideRepresentation(?FieldableEntityInterface $host_entity = NULL): array {
    return $this->buildLayoutAndModel($this->componentTreeItemsIterator(self::inRootLevel()), $host_entity);
  }

  /**
   * @todo Move this into a normalizer at https://www.drupal.org/i/3499632
   */
  private function buildLayoutAndModel(iterable $tree_tier, ?FieldableEntityInterface $host_entity = NULL): array {
    $built = ['layout' => [], 'model' => []];
    /** @var \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem $item */
    foreach ($tree_tier as $item) {
      $item_layout_node = $item->getClientSideRepresentation();
      $component_instance_uuid = $item->getUuid();

      // Use ComponentSourceInterface::inputToClientModel() to map the server-
      // stored `inputs` data to the client-side `model`.
      // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem::propertyDefinitions()
      // @see \Drupal\canvas\Plugin\DataType\ComponentInputs
      // @see DynamicComponent type-script definition.
      // @see ComponentModel type-script definition.
      // @see PropSourceComponent type-script definition.
      // @see EvaluatedComponentModel type-script definition.
      $source = $item->getComponent()?->getComponentSource();
      \assert($source instanceof ComponentSourceInterface);
      if ($source->requiresExplicitInput()) {
        $built['model'][$component_instance_uuid] = $source->inputToClientModel($source->getExplicitInput($component_instance_uuid, $item, $host_entity));
      }

      // TRICKY: the server-side implementation (for storage efficiency) forbids
      // - empty component subtrees
      // - empty slots
      // @see \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator
      // But the client expects all *available* slots to get a `slot node`: in
      // every `component node` for every slot of that component, even the slot
      // is empty.
      // @see docs/data-model.md#3.4.1
      $known_slot_names_for_component = match ($source instanceof ComponentSourceWithSlotsInterface) {
        FALSE => [],
        TRUE => array_keys($source->getSlotDefinitions()),
      };
      foreach ($known_slot_names_for_component as $slot_name) {
        $component_instance_slot = [
          'id' => $component_instance_uuid . '/' . $slot_name,
          'name' => $slot_name,
          'nodeType' => 'slot',
        ];
        $child_build = self::buildLayoutAndModel($this->componentTreeItemsIterator(self::isChildOfComponentTreeItemSlot($component_instance_uuid, $slot_name)), $host_entity);
        $built['model'] += $child_build['model'];
        $component_instance_slot['components'] = $child_build['layout'];
        $item_layout_node['slots'][] = $component_instance_slot;
      }
      $built['layout'][] = $item_layout_node;
    }
    return $built;
  }

  public static function inRootLevel(): callable {
    return static fn (ComponentTreeItem $item) => $item->getParentUuid() === NULL;
  }

  public static function isChildOfComponentTreeItemSlot(string $parent_uuid, string $slot_name): callable {
    return static fn (ComponentTreeItem $item) => $item->getParentUuid() === $parent_uuid && $item->getSlot() === $slot_name;
  }

  public function componentTreeItemsIterator(?callable $filter = NULL): iterable {
    foreach ($this as $delta => $item) {
      \assert($item instanceof ComponentTreeItem);
      if ($filter === NULL || $filter($item)) {
        yield $delta => $item;
      }
    }
  }

  public function getComponentTreeItemByUuid(string $uuid): ?ComponentTreeItem {
    foreach ($this as $item) {
      \assert($item instanceof ComponentTreeItem);
      if ($item->getUuid() === $uuid) {
        return $item;
      }
    }
    return NULL;
  }

  public function getComponentTreeDeltaByUuid(string $uuid): ?int {
    foreach ($this as $delta => $item) {
      \assert($item instanceof ComponentTreeItem);
      if ($item->getUuid() === $uuid) {
        return $delta;
      }
    }
    return NULL;
  }

  public function getComponentIdList(): array {
    return \array_unique(\array_column($this->getValue(), 'component_id'));
  }

  public function getConstraints() {
    $constraints = parent::getConstraints();
    $constraint_manger = $this->getTypedDataManager()
      ->getValidationConstraintManager();
    $constraints[] = $constraint_manger
      ->create(ComponentTreeStructureConstraint::PLUGIN_ID, [
        'basePropertyPath' => $this->getName() ?? '',
      ]);
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function toRenderable(ComponentTreeEntityInterface|FieldableEntityInterface|null $entity = NULL, bool $isPreview = FALSE): array {
    // We have to allow NULL for the entity argument here for co-variance with
    // the parent interface, but we don't support it.
    \assert(!\is_null($entity));
    // ⚠️ We *could* convert to a render array directly. But that should not be
    // the source of truth. So we start from a Drupal Render API-agnostic point,
    // and map that into a render array. This guarantees none of this will ever
    // rely on Render API specifics.
    $renderable_component_tree = $this->getHydratedTree();

    $build = [];
    // @see \Drupal\Core\Entity\EntityViewBuilder::getBuildDefaults()
    CacheableMetadata::createFromObject($renderable_component_tree)->applyTo($build);

    $hydrated = $renderable_component_tree->getTree();

    assert(array_keys($hydrated) === [self::ROOT_UUID]);
    return self::renderify(self::buildRenderingContext($this, $entity), $hydrated, $isPreview);
  }

  /**
   * Recursively converts an array generated by ::getValue() to a render array.
   *
   * @param string $componentRenderingContext
   *   A rendering context for adding debugging context when rendering fails.
   * @param array $hydrated
   *   An array generated by ::getValue().
   * @param bool $isPreview
   *   TRUE if is preview.
   *
   * @return array
   *   The corresponding render array.
   */
  private static function renderify(string $componentRenderingContext, array $hydrated, bool $isPreview = FALSE) {
    $build = [];
    foreach ($hydrated as $component_subtree_uuid => $component_instances) {
      foreach ($component_instances as $component_instance_uuid => $component_instance) {
        try {
          $component = Component::load($component_instance['component']);
          assert($component instanceof Component);
          $source = $component->getComponentSource();
          $element = $source->renderComponent($component_instance, $component_instance_uuid, $isPreview);

          // A component instance provided by a ComponentSourceWithSwitchCasesInterface
          // is guaranteed to either be a `switch` or a `case`. The `switch` component
          // instance is the inevitable container (that may render nothing at all on the
          // live site) that contains the different possible `case`s. On the live site,
          // only ONE `case` will ever be rendered: the negotiated one.
          if ($source instanceof ComponentSourceWithSwitchCasesInterface && !$isPreview) {
            if ($source->isCase() && !$source->isNegotiatedCase($component_instance)) {
              unset($component_instance['slots']);
            }
          }

          // Wrap each rendered component instance in HTML comments that allow the
          // client side to identify it.
          if ($isPreview) {
            $element['#prefix'] = Markup::create("<!-- canvas-start-$component_instance_uuid -->");
            $element['#suffix'] = Markup::create("<!-- canvas-end-$component_instance_uuid -->");
          }

          // Associate the `Component` config entity cache tag with every rendered
          // component instance — remove the need for each `ComponentSource`
          // plugin to do this in an awkward way in their `::renderComponent()`.
          // This also associates any cache contexts and max-age; both may be used
          // for dynamic config overrides.
          CacheableMetadata::createFromRenderArray($element)
            ->addCacheableDependency($component)
            ->applyTo($element);

          // Figure out the slots, if there are any.
          if ($source instanceof ComponentSourceWithSlotsInterface && !empty($component_instance['slots'])) {
            $slots = [];
            foreach ($component_instance['slots'] as $slot => $slot_value) {
              // Handle default slot value: convert to renderable using either
              // #plain_text or `#markup`.
              if (!$isPreview && is_string($slot_value)) {
                $slots[$slot] = !str_starts_with($slot_value, '<')
                  // Match how Drupal core handles string values for components.
                  // @see https://www.drupal.org/node/3398039
                  ? ['#plain_text' => $slot_value]
                  // TRICKY: this goes beyond what Drupal core appears to allow
                  // in https://www.drupal.org/node/3398039, but the SDC plugin
                  // manager accepts this as a valid SDC definition, so Canvas has
                  // no choice but to support it.
                  : ['#markup' => $slot_value];
              }
              // When previewing and the slot value is a default: omit the
              // default in favor of a placeholder div.
              elseif ($isPreview && is_string($slot_value)) {
                $slots[$slot] = ['#markup' => Markup::create('<div class="canvas--slot-empty-placeholder"></div>')];
              }
              // Explicit slot value: renderify, just like the rest of the
              // component tree.
              else {
                $slots += self::renderify($componentRenderingContext, [$slot => $slot_value], $isPreview);
              }
            }

            $source->setSlots($element, $slots);
          }

          // Wrap in a render-safe container.
          // @todo Remove all the wrapping-in-RenderSafeComponentContainer complexity and make ComponentSourceInterface::renderComponent() for that instead in https://www.drupal.org/i/3521041
          $build[$component_subtree_uuid][$component_instance_uuid] = [
            '#type' => RenderSafeComponentContainer::PLUGIN_ID,
            '#component' => $element,
            '#component_context' => $componentRenderingContext,
            '#component_uuid' => $component_instance_uuid,
            '#is_preview' => $isPreview,
          ];
        }
        catch (\Throwable $e) {
          // @todo Remove all the wrapping-in-RenderSafeComponentContainer complexity and make ComponentSourceInterface::renderComponent() for that instead in https://www.drupal.org/i/3521041
          $build[$component_subtree_uuid][$component_instance_uuid] = RenderSafeComponentContainer::handleComponentException(
            $e,
            $componentRenderingContext,
            $isPreview,
            $component_instance_uuid,
          );
        }
      }
    }
    return $build;
  }

  /**
   * Computes the cacheability of this computed property.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability of the computed value.
   */
  private function getCacheability(): CacheableMetadata {
    // @todo Once bundle-level defaults for `tree` + `inputs` are supported, this should also include cacheability of whatever config that is stored in.
    // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem::preSave()

    $root = $this->getRoot();
    if ($root instanceof EntityAdapter) {
      return CacheableMetadata::createFromObject($root->getEntity());
    }

    // This appears to be an ephemeral component tree, hence it is uncacheable.
    return (new CacheableMetadata())->setCacheMaxAge(0);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->getCacheability()->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->getCacheability()->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->getCacheability()->getCacheMaxAge();
  }

  /**
   * Gets the tree as a hydrated tree value object.
   */
  private function getHydratedTree(): HydratedTree {
    return new HydratedTree(
      $this->getHydratedValue(),
      $this->getCacheability(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($delta): void {
    parent::onChange($delta);
    $this->graph = NULL;
  }

  /**
   * Constructs a depth-first graph based on the given tree.
   *
   * @param \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList $tree
   *   Tree to construct a graph for.
   *
   * @return array<string, array{'edges': array<string, TRUE>}>
   *
   * @see \Drupal\Component\Graph\Graph
   */
  private static function constructDepthFirstGraph(ComponentTreeItemList $tree): array {
    // Transform the tree to the input expected by Drupal's Graph utility.
    $graph = [];
    foreach ($tree as $value) {
      \assert($value instanceof ComponentTreeItem);
      $parent_uuid = $value->getParentUuid() ?? self::ROOT_UUID;
      $component_instance_uuid = $value->getUuid();
      $graph[$parent_uuid]['edges'][$component_instance_uuid] = TRUE;
    }

    // Use Drupal's battle-hardened Graph utility.
    $sorted_graph = (new Graph($graph))->searchAndSort();

    // Sort by weight, then reverse: this results in a depth-first sorted graph.
    \uasort($sorted_graph, SortArray::sortByWeightElement(...));
    $reverse_sorted_graph = array_reverse($sorted_graph);

    return $reverse_sorted_graph;
  }

  /**
   * @return \Generator<string, array{'slot': string, 'uuid': string}>
   */
  private function getSlotChildrenDepthFirst(): \Generator {
    if ($this->graph === NULL) {
      $this->graph = self::constructDepthFirstGraph($this);
    }
    $child_items = \array_filter($this->getValue(), static fn (array $item): bool => ($item['slot'] ?? NULL) !== NULL);
    $slot_map = \array_combine(\array_column($child_items, 'uuid'), \array_column($child_items, 'slot'));
    foreach ($this->graph as $vertex_key => $vertex) {
      $parent_uuid = $vertex_key;
      if ($parent_uuid === self::ROOT_UUID) {
        // Skip items at the root.
        continue;
      }
      // For each vertex (after the filtering above), all edges represent
      // child component instances placed in this slot.
      foreach (array_keys($vertex['edges']) as $component_instance_uuid) {
        assert(is_string($component_instance_uuid));
        yield $parent_uuid => [
          'slot' => $slot_map[$component_instance_uuid],
          'uuid' => $component_instance_uuid,
        ];
      }
    }
  }

  private function getHydratedValue(): array {
    $hydrated = [];

    // Load and bulk set all unique Components used in this tree in a single
    // ::loadMultiple call.
    $components = Component::loadMultiple($this->getComponentIdList());

    // Hydrate all component instances, but only considering props. This
    // essentially means getting the values for each component instance, while
    // ignoring their slots. The result: a flat list of hydrated components, but
    // with all slots empty.
    foreach ($this as $item) {
      \assert($item instanceof ComponentTreeItem);
      $component_id = $item->getComponentId();
      $uuid = $item->getUuid();
      $component = $components[$component_id];
      assert($component instanceof Component);
      $component->loadVersion($item->getComponentVersion());

      $source = $component->getComponentSource();
      $hydrated[$uuid] = [
        'component' => $component_id,
      ] + $source->hydrateComponent($source->getExplicitInput($uuid, $item));
    }

    // Transform the flat list of hydrated components into a hydrated component
    // tree, by assigning child components to their parent component's slot. If
    // this happens depth-first, then the tree will gradually be built, with the
    // last iteration assigning the last component to the component tree's root.
    foreach ($this->getSlotChildrenDepthFirst() as $parent_uuid => ['slot' => $slot, 'uuid' => $uuid]) {
      if ($parent_uuid === self::ROOT_UUID) {
        continue;
      }
      assert(array_key_exists('slots', $hydrated[$parent_uuid]));

      // Remove default slot value: this slot is populated.
      if (\array_key_exists($slot, $hydrated[$parent_uuid]['slots']) && \is_string($hydrated[$parent_uuid]['slots'][$slot])) {
        unset($hydrated[$parent_uuid]['slots'][$slot]);
      }

      $hydrated[$parent_uuid]['slots'][$slot][$uuid] = $hydrated[$uuid];
      unset($hydrated[$uuid]);
    }
    return [self::ROOT_UUID => $hydrated];
  }

  private static function buildRenderingContext(ComponentTreeItemList $itemList, ComponentTreeEntityInterface|FieldableEntityInterface $entity): string {
    $entityId = $entity->isNew() ? '-' : $entity->id();
    if ($itemList->getName() !== NULL) {
      return \sprintf('%s %s (%s), field %s', $entity->getEntityType()->getLabel(), $entity->label(), $entityId, $itemList->getName());
    }
    return \sprintf('%s %s (%s)', $entity->getEntityType()->getLabel(), $entity->label(), $entityId);
  }

  /**
   * Retrieves the list of unique types of prop sources used.
   *
   * @return string[]
   *   A list of all unique PropSourceBase::getSourceTypePrefix() return values
   *   stored in this list of component input values, for this component tree.
   */
  public function getPropSourceTypePrefixList(): array {
    $source_type_prefixes = [];
    foreach ($this as $item) {
      \assert($item instanceof ComponentTreeItem);
      /** @var \Drupal\canvas\Plugin\DataType\ComponentInputs $inputs */
      $inputs = $item->get('inputs');
      $source_type_prefixes = \array_merge($source_type_prefixes, $inputs->getPropSourceTypePrefixList());
    }
    return \array_unique($source_type_prefixes);
  }

  /**
   * @param ExposedSlotDefinitions $exposed_slot_info
   * @param \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList $subTreeItemList
   * @return $this
   */
  public function injectSubTreeItemList(array $exposed_slot_info, ComponentTreeItemList $subTreeItemList): self {
    foreach ($exposed_slot_info as $slot_detail) {
      $parent_uuid = $slot_detail['component_uuid'] ?? NULL;
      $slot = $slot_detail['slot_name'] ?? NULL;
      if ($parent_uuid === NULL) {
        throw new SubtreeInjectionException("Cannot inject subtree because we don't know the UUID of the component instance to target.");
      }
      if ($slot === NULL) {
        throw new SubtreeInjectionException("Cannot inject subtree because we don't know the name of the component slot to target.");
      }
      $existing = \count(\iterator_to_array($this->componentTreeItemsIterator(self::isChildOfComponentTreeItemSlot($parent_uuid, $slot))));
      // The target slot needs to be empty.
      if ($existing !== 0) {
        throw new SubtreeInjectionException("Cannot inject subtree because the targeted slot is not empty.");
      }
      foreach ($subTreeItemList->componentTreeItemsIterator(self::isChildOfComponentTreeItemSlot($parent_uuid, $slot)) as $item) {
        \assert($item instanceof ComponentTreeItem);
        if ($this->getComponentTreeItemByUuid($item->getUuid()) !== NULL) {
          throw new SubtreeInjectionException("Cannot inject subtree because some of its components are already in the final tree.");
        }
        $this->appendItem($item->getValue());
      }
    }
    return $this;
  }

}
