<?php

declare(strict_types=1);

namespace Drupal\canvas\ComponentSource;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\Plugin\PluginBase;

/**
 * Defines a base class for component source plugins.
 *
 * @see \Drupal\canvas\Attribute\ComponentSource
 * @see \Drupal\canvas\ComponentSource\ComponentSourceInterface
 * @see \Drupal\canvas\ComponentSource\ComponentSourceManager
 */
abstract class ComponentSourceBase extends PluginBase implements ComponentSourceInterface {

  use ContextAwarePluginAssignmentTrait;
  use ContextAwarePluginTrait;

  public function getSourceSpecificComponentId(): string {
    return $this->getConfiguration()['local_source_id'];
  }

  public function generateVersionHash(): string {
    // @phpstan-ignore-next-line
    $typed_source_specific_settings = \Drupal::service(TypedConfigManagerInterface::class)->createFromNameAndData(
      'canvas.component_source_settings.' . $this->getPluginId(),
      // TRICKY: the ComponentSource plugin instance always receives the local
      // source ID that identifies the component within that source. But that
      // plugin ID is not part of the config schema.
      // @see `type: canvas.component_source_settings.*`
      array_diff_key($this->configuration, array_flip(['local_source_id'])),
    );
    assert($typed_source_specific_settings instanceof Mapping);
    $normalized_data = [
      'settings' => $typed_source_specific_settings->toArray(),
      'slot_definitions' => $this instanceof ComponentSourceWithSlotsInterface
        ? self::normalizeSlotDefinitions($this->getSlotDefinitions())
        : [],
      'schema' => $this->getExplicitInputDefinitions(),
    ];
    // Intuitively, we'd want to rely on:
    // - config export order (https://www.drupal.org/node/3230199)
    // - slot definition order
    // - explicit input schema order
    // But that would lead to unnecessary new versions: the order of slots and
    // explicit inputs (SDC: "props") does not impact existing component
    // instances, other than their corresponding component instance form perhaps
    // showing a different order. New versions of Component config entities are
    // only warranted if there is a change in the data needing to be stored for
    // a component instance.
    self::recursiveKsort($normalized_data);
    $hash = \hash('xxh64', \json_encode($normalized_data, JSON_THROW_ON_ERROR));
    // 💡 If you are debugging why a version hash does not match, put a
    // conditional breakpoint here.
    return $hash;
  }

  protected static function recursiveKsort(array &$array): void {
    ksort($array);
    foreach ($array as &$value) {
      if (is_array($value)) {
        self::recursiveKsort($value);
      }
    }
  }

  private static function normalizeSlotDefinitions(array $slot_definitions): array {
    \array_walk($slot_definitions, function (&$slot_definition) {
      \reset($slot_definition);
    });
    return \array_reduce(
      \array_keys(\array_filter($slot_definitions, \is_array(...))),
      static fn(array $carry, string $slot_name) => $carry + [
        $slot_name => [
          'title' => $slot_definitions[$slot_name]['title'],
          'example' => \current($slot_definitions[$slot_name]['examples'] ?? []) ?: '',
        ],
      ],
      []
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition(): array {
    $definition = parent::getPluginDefinition();
    assert(is_array($definition));
    return $definition;
  }

  /**
   * Returns the plugin dependencies being removed.
   *
   * The function recursively computes the intersection between all plugin
   * dependencies and all removed dependencies.
   *
   * Note: The two arguments do not have the same structure.
   *
   * @param array[] $plugin_dependencies
   *   A list of dependencies having the same structure as the return value of
   *   ConfigEntityInterface::calculateDependencies().
   * @param array[] $removed_dependencies
   *   A list of dependencies having the same structure as the input argument of
   *   ConfigEntityInterface::onDependencyRemoval().
   *
   * @return array
   *   A recursively computed intersection.
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityInterface::calculateDependencies()
   * @see \Drupal\Core\Config\Entity\ConfigEntityInterface::onDependencyRemoval()
   * @see \Drupal\Core\Entity\EntityDisplayBase::getPluginRemovedDependencies()
   * @todo Remove this verbatim copy of \Drupal\Core\Entity\EntityDisplayBase::getPluginRemovedDependencies() and move it to a trait in Drupal core.
   */
  protected function getPluginRemovedDependencies(array $plugin_dependencies, array $removed_dependencies) {
    $intersect = [];
    foreach ($plugin_dependencies as $type => $dependencies) {
      if (array_key_exists($type, $removed_dependencies) && $removed_dependencies[$type]) {
        // Config and content entities have the dependency names as keys while
        // module and theme dependencies are indexed arrays of dependency names.
        // @see \Drupal\Core\Config\ConfigManager::callOnDependencyRemoval()
        if (in_array($type, ['config', 'content'])) {
          $removed = array_intersect_key($removed_dependencies[$type], array_flip($dependencies));
        }
        else {
          $removed = array_values(array_intersect($removed_dependencies[$type], $dependencies));
        }
        if ($removed) {
          $intersect[$type] = $removed;
        }
      }
    }
    return $intersect;
  }

  /**
   * Gets information about the explicit inputs.
   *
   * @return array<string, mixed>
   *   Keys are names of explicit inputs. Values are some normalized schema
   *   representation, for example:
   *   - JSON Schema (SDCs, code components)
   *   - config schema (Block plugins)
   *   - …
   */
  abstract protected function getExplicitInputDefinitions(): array;

  /**
   * {@inheritdoc}
   */
  public function optimizeExplicitInput(array $values): array {
    // Nil-op.
    return $values;
  }

}
