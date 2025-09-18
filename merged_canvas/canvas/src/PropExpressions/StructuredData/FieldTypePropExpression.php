<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * For pointing to a prop in a field type (not considering any delta).
 */
final class FieldTypePropExpression implements StructuredDataPropExpressionInterface {

  public function __construct(
    public readonly string $fieldType,
    public readonly string $propName,
  ) {}

  public function __toString(): string {
    return static::PREFIX
      . $this->fieldType
      . static::PREFIX_PROPERTY_LEVEL . $this->propName;
  }

  public static function fromString(string $representation): static {
    $parts = explode('␟', mb_substr($representation, 2));
    return new FieldTypePropExpression(...$parts);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $field_item_list = NULL): array {
    assert($field_item_list === NULL || $field_item_list instanceof FieldItemListInterface);
    // @phpstan-ignore-next-line
    $field_type_manager = \Drupal::service(FieldTypePluginManagerInterface::class);
    assert($field_type_manager instanceof FieldTypePluginManagerInterface);
    $provider = $field_type_manager->getDefinition($this->fieldType)['provider'] ?? NULL;

    $dependencies = [];
    // Core-provided field types need no modules to be installed.
    if (!in_array($provider, [NULL, 'core'], TRUE)) {
      $dependencies['module'][] = $provider;
    }

    // Computed properties can have dependencies of their own.
    if ($field_item_list !== NULL) {
      $field_definition = $field_item_list->getFieldDefinition();
      $field_storage_definition = match ($field_definition instanceof FieldStorageDefinitionInterface) {
        TRUE => $field_definition,
        FALSE => $field_definition->getFieldStorageDefinition(),
      };
      $property_definitions = $field_storage_definition->getPropertyDefinitions();
      if (!array_key_exists($this->propName, $property_definitions)) {
        // @phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
        @trigger_error(sprintf('Property %s does not exist', $this->propName), E_USER_DEPRECATED);
      }
      elseif (is_a($property_definitions[$this->propName]->getClass(), DependentPluginInterface::class, TRUE)) {
        assert($property_definitions[$this->propName]->isComputed());
        foreach ($field_item_list as $field_item) {
          $dependencies = NestedArray::mergeDeep($dependencies, $field_item->get($this->propName)->calculateDependencies());
        }
      }
    }

    return $dependencies;
  }

  public function isSupported(EntityInterface|FieldItemInterface|FieldItemListInterface $field): bool {
    assert($field instanceof FieldItemInterface || $field instanceof FieldItemListInterface);
    $actual_field_type = $field->getFieldDefinition()->getType();
    if ($actual_field_type !== $this->fieldType) {
      throw new \DomainException(sprintf("`%s` is an expression for field type `%s`, but the provided field item (list) is of type `%s`.", (string) $this, $this->fieldType, $actual_field_type));
    }
    return TRUE;
  }

}
