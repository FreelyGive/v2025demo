<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;
use Drupal\field\FieldConfigInterface;

/**
 * For pointing to a prop in a concrete field.
 */
final class FieldPropExpression implements StructuredDataPropExpressionInterface {

  public function __construct(
    // @todo will this break down once we support config entities? It must, because top-level config entity props ~= content entity fields, but deeper than that it is different.
    public readonly EntityDataDefinitionInterface $entityType,
    public readonly string|array $fieldName,
    // A content entity field item delta is optional.
    // @todo Should this allow expressing "all deltas"? Should that be represented using `NULL`, `TRUE`, `*` or `∀`? For now assuming NULL.
    public readonly int|null $delta,
    public readonly string|array $propName,
  ) {
    $bundles = $entityType->getBundles();
    if (($bundles === NULL || count($bundles) <= 1) && is_array($fieldName) && count($fieldName) > 1) {
      throw new \InvalidArgumentException('When targeting a (single bundle of) an entity type, only a single field name can be specified.');
    }
    if (($bundles === NULL || count($bundles) <= 1) && is_array($this->propName) && count($this->propName) > 1) {
      throw new \InvalidArgumentException('When targeting a (single bundle of) an entity type, only a single field property name can be specified.');
    }
    // When targeting >1 bundle, it's possible to target either:
    // - a base field, then $fieldName will be a string;
    // - bundle fields, then $fieldName must be an array: keys are bundle names,
    //   values are bundle field names;
    // - different prop names if having different field names (as we could have
    //   different field types) then $propNames must be an array: keys are
    //   bundle-specific field names, values are the prop names for each field.
    // ⚠️ Note that $delta continue to be unchanged; this is only
    // designed for the use case where different bundles have different fields
    // of the same or different type (and cardinality and storage settings).
    // For example: pointing to multiple media types, with differently named
    // "media source" fields, but with the same or different field types because
    // having different media sources.
    // If a value for the expression cannot be associated with a field type
    // property, the special NULL symbol value (␀) can be used to opt out, but
    // only in the context of a FieldObjectPropsExpression. For example: a prop
    // shape to populate `<img>` would always need to populate `src`, but `alt`,
    // `width` and `height` may be optional. Those last 3 could then use ␀ if
    // only a subset of the bundle-specific fields with different field types
    // are able to populate any of those.
    // @see \Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface::SYMBOL_OBJECT_MAPPED_OPTIONAL_PROP
    if (is_array($fieldName)) {
      $bundles = $entityType->getBundles();
      assert($bundles !== NULL && count($bundles) >= 1);

      if (count($bundles) !== count(array_unique($bundles))) {
        throw new \InvalidArgumentException('Duplicate bundles are nonsensical.');
      }

      // Ensure that the $fieldName ordering matches that of the bundles.
      // @see \Drupal\canvas\TypedData\BetterEntityDataDefinition::create()
      if ($bundles !== array_keys($fieldName)) {
        throw new \InvalidArgumentException('A field name must be specified for every bundle, and in the same order.');
      }
    }
    if (is_array($propName)) {
      // If propName is an array, fieldName must be too.
      assert(is_array($fieldName));
      if (array_values($fieldName) !== array_keys($propName)) {
        throw new \InvalidArgumentException('A field property name must be specified for every field name, and in the same order.');
      }
      if (array_values(array_unique($propName)) === [StructuredDataPropExpressionInterface::SYMBOL_OBJECT_MAPPED_OPTIONAL_PROP]) {
        throw new \InvalidArgumentException('At least one of the field names must have a field property specified; otherwise it should be omitted (␀ can only be used when a subset of the bundles does not provide a certain value).');
      }
    }
  }

  public function __toString(): string {
    return static::PREFIX
      . static::PREFIX_ENTITY_LEVEL . $this->entityType->getDataType()
      // Note that BetterEntityDataDefinition sorts bundles alphabetically (to
      // ensure a predictable data type ID). Hence an array of field names must
      // correspond to the alphabetically sorted bundle order.
      . static::PREFIX_FIELD_LEVEL . implode('|', (array) $this->fieldName)
      . static::PREFIX_FIELD_ITEM_LEVEL . ($this->delta ?? '')
      // See the above remark: the same is true for an array of field property
      // names.
      . static::PREFIX_PROPERTY_LEVEL . implode('|', (array) $this->propName);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $host_entity = NULL): array {
    assert($host_entity === NULL || $host_entity instanceof FieldableEntityInterface);
    // @phpstan-ignore-next-line
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type_id = $this->entityType->getEntityTypeId();
    assert($entity_type_manager instanceof EntityTypeManagerInterface);
    assert(\is_string($entity_type_id));
    $entity_type = $entity_type_manager->getDefinition($entity_type_id);
    $dependencies = [];

    // Entity type: provided by a module.
    $dependencies['module'][] = $entity_type->getProvider();

    // Bundle: only if there is a bundle config entity type.
    $bundle = NULL;
    $possible_bundles = $this->entityType->getBundles();
    if ($possible_bundles !== NULL && $entity_type->getBundleEntityType()) {
      $possible_bundles = $this->entityType->getBundles();
      assert(is_array($possible_bundles));
      foreach ($possible_bundles as $bundle) {
        $bundle_config_dependency = $entity_type->getBundleConfigDependency($bundle);
        $dependencies[$bundle_config_dependency['type']][] = $bundle_config_dependency['name'];
      }
    }

    if (is_string($this->fieldName)) {
      assert(is_string($this->propName));
      $field_definitions = $this->entityType->getPropertyDefinitions();
      if (!isset($field_definitions[$this->fieldName])) {
        throw new \LogicException(sprintf("%s field referenced in %s %s does not exist.", $this->fieldName, (string) $this, __CLASS__));
      }
      // Determine the bundle to use during dependency calculation:
      $bundle = match (TRUE) {
        // - an array with a single value: a single bundle is targeted
        is_array($possible_bundles) && count($possible_bundles) === 1 => reset($possible_bundles),
        // - no bundle: the entity type is targeted
        // - an array with multiple values: multiple bundles are targeted, but
        //   the same base field on all of them, so then fall back to `NULL` as
        //   the bundle
        default => NULL,
      };
      assert($field_definitions[$this->fieldName] instanceof FieldDefinitionInterface);
      $field_definition = $field_definitions[$this->fieldName];
      $dependencies = NestedArray::mergeDeep($dependencies, $this->calculateDependenciesForFieldDefinition($field_definition, $bundle));

      // Computed properties can have dependencies of their own.
      if ($host_entity !== NULL) {
        $dependencies = NestedArray::mergeDeep($dependencies, self::calculateDependenciesForProperty(
          $host_entity,
          $this->fieldName,
          $this->propName,
          $field_definition
        ));
      }
    }
    else {
      assert(is_array($possible_bundles));
      assert(is_string($this->propName) || (is_array($this->propName) && is_array($this->fieldName)));
      foreach ($possible_bundles as $bundle) {
        // @phpstan-ignore-next-line
        $bundle_field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
        $bundle_specific_field_name = $this->fieldName[$bundle];
        if (!isset($bundle_field_definitions[$bundle_specific_field_name])) {
          throw new \LogicException(sprintf("%s field on the %s bundle referenced in %s %s does not exist.", $bundle_specific_field_name, $bundle, (string) $this, __CLASS__));
        }
        $dependencies = NestedArray::mergeDeep($dependencies, $this->calculateDependenciesForFieldDefinition($bundle_field_definitions[$bundle_specific_field_name], $bundle));
      }

      // Computed properties can have dependencies of their own.
      if ($host_entity !== NULL) {
        $bundle = $host_entity->bundle();
        $bundle_specific_field_name = $this->fieldName[$bundle];
        $prop_name = match (TRUE) {
          is_string($this->propName) => $this->propName,
          // @see \Drupal\Tests\canvas\Unit\PropExpressionTest::testInvalidFieldPropExpressionDueToMultipleFieldPropNamesWithoutMultipleFieldNames()
          is_array($this->propName) => $this->propName[$bundle_specific_field_name],
        };
        if ($prop_name !== StructuredDataPropExpressionInterface::SYMBOL_OBJECT_MAPPED_OPTIONAL_PROP) {
          $dependencies = NestedArray::mergeDeep($dependencies, self::calculateDependenciesForProperty(
            $host_entity,
            $bundle_specific_field_name,
            $prop_name,
            // @phpstan-ignore-next-line argument.type
            $host_entity->getFieldDefinition($bundle_specific_field_name),
          ));
        }
      }
    }

    return $dependencies;
  }

  private static function calculateDependenciesForProperty(FieldableEntityInterface $host_entity, string $field_name, string $prop_name, FieldDefinitionInterface $field_definition): array {
    $dependencies = [];

    $property_definitions = $field_definition->getFieldStorageDefinition()->getPropertyDefinitions();
    if (!array_key_exists($prop_name, $property_definitions)) {
      // @phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
      @trigger_error(sprintf('Property %s does not exist', $prop_name), E_USER_DEPRECATED);
    }
    elseif (is_a($property_definitions[$prop_name]->getClass(), DependentPluginInterface::class, TRUE)) {
      assert($property_definitions[$prop_name]->isComputed());
      foreach ($host_entity->get($field_name) as $field_item) {
        assert($field_item->get($prop_name) instanceof DependentPluginInterface);
        $dependencies = NestedArray::mergeDeep($dependencies, $field_item->get($prop_name)->calculateDependencies());
      }
    }

    return $dependencies;
  }

  private function calculateDependenciesForFieldDefinition(FieldDefinitionInterface $field_definition, ?string $bundle): array {
    $dependencies = [];

    // If this is a base field definition, there are no other dependencies.
    if ($field_definition instanceof BaseFieldDefinition) {
      return $dependencies;
    }

    // Otherwise, this must be a non-base field definition, and additional
    // dependencies are necessary.
    assert(is_string($bundle));
    $config = $field_definition->getConfig($bundle);
    assert($config instanceof BaseFieldOverride || $config instanceof FieldConfigInterface);
    // Ignore config auto-generated by ::getConfig().
    if (!$config->isNew()) {
      // @todo Possible future optimization: ignore base field overrides unless they modify the `field_type`, `settings` or `required` properties compared to the code-defined base field. Any other modification has no effect on evaluating this expression.
      $dependencies['config'][] = $config->getConfigDependencyName();
    }

    // Calculate dependencies from the field item and its properties.
    $field_item_class = $field_definition->getItemDefinition()->getClass();
    assert(is_subclass_of($field_item_class, FieldItemInterface::class));
    $instance_deps = $field_item_class::calculateDependencies($field_definition);
    $storage_deps = $field_item_class::calculateStorageDependencies($field_definition->getFieldStorageDefinition());
    $dependencies = NestedArray::mergeDeep(
      $dependencies,
      $instance_deps,
      $storage_deps,
    );
    ksort($dependencies);
    return array_map(static function ($values) {
      $values = array_unique($values);
      sort($values);
      return $values;
    }, $dependencies);
  }

  public function withDelta(int $delta): static {
    return new static(
      $this->entityType,
      $this->fieldName,
      $delta,
      $this->propName,
    );
  }

  public static function fromString(string $representation): static {
    [$entity_part, $remainder] = explode(self::PREFIX_FIELD_LEVEL, $representation);
    $entity_data_definition = BetterEntityDataDefinition::createFromDataType(mb_substr($entity_part, 3));
    [$field_name, $remainder] = explode(self::PREFIX_FIELD_ITEM_LEVEL, $remainder, 2);
    [$delta, $prop_name] = explode(self::PREFIX_PROPERTY_LEVEL, $remainder, 2);
    return new static(
      $entity_data_definition,
      str_contains($field_name, '|')
        ? array_combine(
          // @phpstan-ignore-next-line
          $entity_data_definition->getBundles(),
          explode('|', $field_name),
        )
        : $field_name,
      $delta === '' ? NULL : (int) $delta,
      str_contains($prop_name, '|')
        ? array_combine(
          explode('|', $field_name),
          explode('|', $prop_name),
        )
        : $prop_name,
    );
  }

  public function isSupported(EntityInterface|FieldItemInterface|FieldItemListInterface $entity): bool {
    assert($entity instanceof EntityInterface);
    $expected_entity_type_id = $this->entityType->getEntityTypeId();
    $expected_bundles = $this->entityType->getBundles();
    if ($entity->getEntityTypeId() !== $expected_entity_type_id) {
      throw new \DomainException(sprintf("`%s` is an expression for entity type `%s`, but the provided entity is of type `%s`.", (string) $this, $expected_entity_type_id, $entity->getEntityTypeId()));
    }
    if ($expected_bundles !== NULL && !in_array($entity->bundle(), $expected_bundles)) {
      throw new \DomainException(sprintf("`%s` is an expression for entity type `%s`, bundle(s) `%s`, but the provided entity is of the bundle `%s`.", (string) $this, $expected_entity_type_id, implode(', ', $expected_bundles), $entity->bundle()));
    }
    // @todo validate that the field exists?
    return TRUE;
  }

}
