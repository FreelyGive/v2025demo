<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;

final class FieldObjectPropsExpression implements StructuredDataPropExpressionInterface {

  use CompoundExpressionTrait;

  /**
   * @param array<string, FieldPropExpression|ReferenceFieldPropExpression> $objectPropsToFieldProps
   *   A mapping of SDC prop names to Field Type prop expressions.
   */
  public function __construct(
    // @todo will this break down once we support config entities? It must, because top-level config entity props ~= content entity fields, but deeper than that it is different.
    public readonly EntityDataDefinitionInterface $entityType,
    public readonly string $fieldName,
    // A content entity field item delta is optional.
    // @todo Should this allow expressing "all deltas"? Should that be represented using `NULL`, `TRUE`, `*` or `∀`? For now assuming NULL.
    public readonly int|null $delta,
    public readonly array $objectPropsToFieldProps,
  ) {
    assert(Inspector::assertAllStrings(array_keys($this->objectPropsToFieldProps)));
    assert(Inspector::assertAll(function ($expr) {
      return $expr instanceof FieldPropExpression || $expr instanceof ReferenceFieldPropExpression;
    }, $this->objectPropsToFieldProps));
    array_walk($objectPropsToFieldProps, function (StructuredDataPropExpressionInterface $expr) {
      // Each of the expressions in $objectPropsToFieldProps MUST target the
      // same field item; otherwise it'd be nonsense. IOW: the following MUST match `entityType`, `fieldName` and `delta`.
      $targets_same_field_item = $expr instanceof ReferenceFieldPropExpression
        ? $expr->referencer->entityType == $this->entityType && $expr->referencer->fieldName === $this->fieldName && $expr->referencer->delta === $this->delta
        : $expr->entityType == $this->entityType && $expr->fieldName === $this->fieldName && $expr->delta === $this->delta;
      if (!$targets_same_field_item) {
        throw new \InvalidArgumentException(sprintf(
          '`%s` is not a valid expression, because it does not map the same field item (entity type `%s`, field name `%s`, delta `%s`).',
          (string) $expr,
          $this->entityType->getDataType(),
          $this->fieldName,
          $this->delta === NULL ? 'null' : (string) $this->delta
        ));
      }
    });
  }

  public function __toString(): string {
    return static::PREFIX
      . static::PREFIX_ENTITY_LEVEL . $this->entityType->getDataType()
      . static::PREFIX_FIELD_LEVEL . $this->fieldName
      . static::PREFIX_FIELD_ITEM_LEVEL . ($this->delta ?? '')
      . static::PREFIX_PROPERTY_LEVEL . static::PREFIX_OBJECT
      . implode(',', array_map(
        function (
          string $obj_prop_name,
          FieldPropExpression|ReferenceFieldPropExpression $expr,
        ) {
          // It is guaranteed that every referencer's fieldName matches exactly
          // and is hence guaranteed to be a string. Which automatically means
          // propName must also be a string.
          // Assert it here both to satisfy PHPStan and to prove it while
          // assertions are on.
          // @see __construct()
          // @see \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::__construct()
          // @see \Drupal\Tests\canvas\Unit\PropExpressionTest::testInvalidFieldPropExpressionDueToMultipleFieldPropNamesWithoutMultipleFieldNames()
          assert(($expr instanceof ReferenceFieldPropExpression && is_string($expr->referencer->propName)) || ($expr instanceof FieldPropExpression && is_string($expr->propName)));
          $tail = match (get_class($expr)) {
            ReferenceFieldPropExpression::class => (function () use ($expr) {
              assert(is_string($expr->referencer->propName));
              return $expr->referencer->propName . static::PREFIX_ENTITY_LEVEL . self::withoutPrefix((string) $expr->referenced);
            })(),
            FieldPropExpression::class => (function () use ($expr) {
              assert(is_string($expr->propName));
              return $expr->propName;
            })(),
          };
          return sprintf(
            '%s%s%s',
            $obj_prop_name,
            $expr instanceof ReferenceFieldPropExpression
              ? self::SYMBOL_OBJECT_MAPPED_FOLLOW_REFERENCE
              : self::SYMBOL_OBJECT_MAPPED_USE_PROP,
            $tail,
          );
        },
        array_keys($this->objectPropsToFieldProps),
        array_values($this->objectPropsToFieldProps),
      ))
      . static::SUFFIX_OBJECT;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $host_entity = NULL): array {
    assert($host_entity === NULL || $host_entity instanceof FieldableEntityInterface);
    $dependencies = [];
    foreach ($this->objectPropsToFieldProps as $expr) {
      $dependencies = NestedArray::mergeDeep($dependencies, $expr->calculateDependencies($host_entity));
    }
    return $dependencies;
  }

  public function withDelta(int $delta): static {
    return new static(
      $this->entityType,
      $this->fieldName,
      $delta,
      $this->objectPropsToFieldProps,
    );
  }

  public static function fromString(string $representation): static {
    [$entity_part, $remainder] = explode(self::PREFIX_FIELD_LEVEL, $representation, 2);
    $entity_data_definition = BetterEntityDataDefinition::createFromDataType(mb_substr($entity_part, 3));
    [$field_name, $remainder] = explode(self::PREFIX_FIELD_ITEM_LEVEL, $remainder, 2);
    [$delta, $object_mapping] = explode(self::PREFIX_PROPERTY_LEVEL, $remainder, 2);
    // Strip the surrounding curly braces.
    $object_mapping = mb_substr($object_mapping, 1, -1);

    $objectPropsToFieldTypeProps = [];
    foreach (explode(',', $object_mapping) as $obj_prop_mapping) {
      if (str_contains($obj_prop_mapping, self::SYMBOL_OBJECT_MAPPED_USE_PROP)) {
        [$sdc_obj_prop_name, $field_instance_prop_name] = explode(self::SYMBOL_OBJECT_MAPPED_USE_PROP, $obj_prop_mapping);
        $objectPropsToFieldTypeProps[$sdc_obj_prop_name] = new FieldPropExpression(
          $entity_data_definition,
          $field_name,
          $delta === '' ? NULL : (int) $delta,
          $field_instance_prop_name
        );
      }
      else {
        [$sdc_obj_prop_name, $obj_prop_mapping_remainder] = explode(self::SYMBOL_OBJECT_MAPPED_FOLLOW_REFERENCE, $obj_prop_mapping);
        [$field_instance_prop_name, $field_prop_ref_expr] = explode(self::PREFIX_ENTITY_LEVEL, $obj_prop_mapping_remainder, 2);
        $referenced = StructuredDataPropExpression::fromString(self::PREFIX . $field_prop_ref_expr);
        assert($referenced instanceof ReferenceFieldPropExpression || $referenced instanceof FieldPropExpression || $referenced instanceof FieldObjectPropsExpression);
        $objectPropsToFieldTypeProps[$sdc_obj_prop_name] = new ReferenceFieldPropExpression(
          new FieldPropExpression($entity_data_definition, $field_name, NULL, $field_instance_prop_name),
          $referenced,
        );
      }
    }

    return new static(
      $entity_data_definition,
      $field_name,
      $delta === '' ? NULL : (int) $delta,
      $objectPropsToFieldTypeProps
    );
  }

  public function isSupported(EntityInterface|FieldItemInterface|FieldItemListInterface $entity): bool {
    assert($entity instanceof EntityInterface);
    $expected_entity_type_id = $this->entityType->getEntityTypeId();
    $expected_bundle = $this->entityType->getBundles()[0] ?? $expected_entity_type_id;
    if ($entity->getEntityTypeId() !== $expected_entity_type_id) {
      throw new \DomainException(sprintf("`%s` is an expression for entity type `%s`, but the provided entity is of type `%s`.", (string) $this, $expected_entity_type_id, $entity->getEntityTypeId()));
    }
    if ($entity->bundle() !== $expected_bundle) {
      throw new \DomainException(sprintf("`%s` is an expression for entity type `%s`, bundle `%s`, but the provided entity is of the bundle `%s`.", (string) $this, $expected_entity_type_id, $expected_bundle, $entity->bundle()));
    }
    // @todo validate that the field exists?
    return TRUE;
  }

}
