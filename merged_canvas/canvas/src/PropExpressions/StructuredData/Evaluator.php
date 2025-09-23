<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;

final class Evaluator {

  public static function evaluate(null|EntityInterface|FieldItemInterface|FieldItemListInterface $entity_or_field, StructuredDataPropExpressionInterface $expr, bool $is_required): mixed {
    $result = self::doEvaluate($entity_or_field, $expr, $is_required);
    // Compensate for DateTimeItemInterface::DATETIME_STORAGE_FORMAT not
    // including the trailing `Z`. In theory, this should always use an adapter.
    // But is the storage and complexity overhead of doing that worth that
    // architectural purity?
    // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::DATETIME_TYPE_DATETIME
    // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface::DATETIME_STORAGE_FORMAT
    // @see https://ijmacd.github.io/rfc3339-iso8601/
    if ($expr instanceof FieldTypePropExpression &&
      $expr->fieldType === 'datetime' &&
      $entity_or_field instanceof FieldItemInterface &&
      $entity_or_field->getFieldDefinition()->getFieldStorageDefinition()->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATETIME &&
      // Don't intervene if the result is already in iso8601 format - this
      // includes a trailing offset, or using the Z flag.
      !\preg_match('/(Z|[+-](?:2[0-3]|[01][0-9])(?::?[0-5][0-9])?)$/', $result)) {

      return $result . 'Z';
    }
    return $result;
  }

  private static function doEvaluate(null|EntityInterface|FieldItemInterface|FieldItemListInterface $entity_or_field, StructuredDataPropExpressionInterface $expr, bool $is_required): mixed {
    // Evaluating an expression when the evaluation context is NULL is
    // impossible.
    // @see \Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface::isSupported()
    if ($entity_or_field === NULL) {
      return match ($is_required) {
        // Optional value: the expression evaluates to NULL.
        FALSE => NULL,
        // Required value: the expression MUST not evaluate to NULL, but without
        // data that is impossible. Throw exception that the caller MAY act on.
        TRUE => throw new \OutOfRangeException('No data provided to evaluate expression ' . (string) $expr),
      };
    }

    // Assert that the received entity or field meets the needs of the
    // expression.
    try {
      $expr->isSupported($entity_or_field);
    }
    catch (\DomainException $e) {
      throw $e;
    }

    // When a list of field items is given:
    // - keep the deltas as keys
    // - evaluate each FieldItemInterface inside the list individually
    if ($entity_or_field instanceof FieldItemListInterface) {
      return array_map(
        fn (FieldItemInterface $item) => self::evaluate($item, $expr, $is_required),
        iterator_to_array($entity_or_field),
      );
    }
    elseif ($entity_or_field instanceof FieldItemInterface) {
      $field = $entity_or_field;
      return match (get_class($expr)) {
        FieldTypePropExpression::class => (function () use ($field, $expr) {
          $prop = $field->get($expr->propName);
          return $prop instanceof PrimitiveInterface
            ? $prop->getCastedValue()
            : $prop->getValue();
        })(),
        FieldTypeObjectPropsExpression::class => array_filter(
          array_combine(
            array_keys($expr->objectPropsToFieldTypeProps),
            array_map(
              fn (FieldTypePropExpression|ReferenceFieldTypePropExpression $sub_expr) => self::evaluate($field, $sub_expr, $is_required),
              $expr->objectPropsToFieldTypeProps
            )
          ),
          // Omit optional props.
          fn (mixed $v) => $v !== StructuredDataPropExpressionInterface::SYMBOL_OBJECT_MAPPED_OPTIONAL_PROP,
        ),
        ReferenceFieldTypePropExpression::class => self::evaluate(
          $field->get($expr->referencer->propName)->getValue(),
          $expr->referenced,
          $is_required,
        ),
        default => throw new \LogicException('Unhandled expression type. ' . (string) $expr),
      };
    }
    else {
      $entity = $entity_or_field;
      // @todo support non-fieldable entities?
      assert($entity instanceof FieldableEntityInterface);
      return match (get_class($expr)) {
        FieldPropExpression::class => (function () use ($entity, $expr) {
          $field_item_list = match (TRUE) {
            is_string($expr->fieldName) => $entity->get($expr->fieldName),
            is_array($expr->fieldName) => $entity->get($expr->fieldName[$entity->bundle()]),
          };
          assert($field_item_list instanceof FieldItemListInterface);
          $field_definition = $field_item_list->getFieldDefinition();
          $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
          // If a specific delta is requested, validate it.
          if ($expr->delta !== NULL) {
            if ($expr->delta < 0) {
              throw new \LogicException(sprintf("Requested delta %d, but deltas must be positive integers.", $expr->delta));
            }
            elseif ($cardinality === 1 && $expr->delta !== 0) {
              throw new \LogicException(sprintf("Requested delta %d for single-cardinality field, must be either zero or omitted.", $expr->delta));
            }
            elseif ($cardinality !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && $expr->delta >= $cardinality) {
              throw new \LogicException(sprintf("Requested delta %d for %d cardinality field, but must be in range [0, %d].", $expr->delta, $cardinality, $cardinality - 1));
            }
          }
          $result = [];
          foreach ($field_item_list as $delta => $field_item) {
            if ($expr->delta === NULL || $expr->delta === $delta) {
              assert(is_string($expr->propName) || (is_array($expr->propName) && is_array($expr->fieldName)));
              $prop_name = match (TRUE) {
                is_string($expr->propName) => $expr->propName,
                // @see \Drupal\Tests\canvas\Unit\PropExpressionTest::testInvalidFieldPropExpressionDueToMultipleFieldPropNamesWithoutMultipleFieldNames()
                // @phpstan-ignore-next-line offsetAccess.notFound
                is_array($expr->propName) => $expr->propName[$expr->fieldName[$entity->bundle()]],
              };
              // TRICKY: when a FieldPropExpression targets multiple bundles of
              // an entity type and a subset of those bundles' fields cannot
              // provide the needed value, it is allowed to explicitly opt out
              // using `␀`.
              // @see \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::__construct()
              if ($prop_name === StructuredDataPropExpressionInterface::SYMBOL_OBJECT_MAPPED_OPTIONAL_PROP) {
                return StructuredDataPropExpressionInterface::SYMBOL_OBJECT_MAPPED_OPTIONAL_PROP;
              }
              $prop = $field_item->get($prop_name);
              $result[$delta] = $prop instanceof PrimitiveInterface
                ? $prop->getCastedValue()
                : $prop->getValue();
            }
          }
          if ($cardinality === 1 || is_int($expr->delta)) {
            // Non-existent deltas on multiple-cardinality fields return NULL.
            return $result[$expr->delta ?? 0] ?? NULL;
          }
          return $result;
        })(),
        ReferenceFieldPropExpression::class => self::evaluate(
          self::evaluate($entity, $expr->referencer, $is_required),
          $expr->referenced,
          $is_required
        ),
        FieldObjectPropsExpression::class => array_combine(
          array_keys($expr->objectPropsToFieldProps),
          array_map(
            fn(FieldPropExpression|ReferenceFieldPropExpression $sub_expr) => self::evaluate($entity_or_field, $sub_expr, $is_required),
            $expr->objectPropsToFieldProps
          )
        ),
        default => throw new \LogicException('Unhandled expression type.'),
      };
    }
  }

}
