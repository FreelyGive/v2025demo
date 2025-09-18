<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

final class StructuredDataPropExpression {

  use CompoundExpressionTrait;

  /**
   * Maps a string representation back to a structured data expression object.
   *
   * @param string $representation
   *   The string representation of a structured data expression object.
   *
   * @return \Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface
   */
  public static function fromString(string $representation): StructuredDataPropExpressionInterface {
    $root_expr = self::parseRootExpression($representation);

    // The first and last symbol of the root expression:
    // - The first determines whether this is an expression that requires an
    //   entity context or not.
    // - The last either is a `}` or not. If it is, this is an expression for a
    //   prop, that using JSON Schema terminology, is of `type: object`, i.e. is
    //   not for a scalar SDC prop, but an object SDC prop.
    $root_expr_symbol_first = mb_substr(self::withoutPrefix($root_expr), 0, 1);
    $root_expr_symbol_last = mb_substr($root_expr, -1, 1);

    // If (and only if) the root expression is not the full representation, then
    // a "look ahead" is needed to the next character — if that is `␜`, this
    // string representation of an expression MUST be expressing an entity
    // reference.
    // For example:
    // @code
    // ℹ︎image␟entity
    // @endcode
    // Would be a FieldTypePropExpression, whereas
    // @code
    // ℹ︎image␟entity␜␜entity:file␝filemime␞0␟value
    // @endcode
    // Would be a ReferenceFieldTypePropExpression.
    $root_expr_symbol_next = mb_substr($representation, mb_strlen($root_expr), 1);
    assert((mb_strlen($root_expr) < mb_strlen($representation) && !empty($root_expr_symbol_next)) || empty($root_expr_symbol_next), 'If the top-level expression is not the full string representation of the expression, then $tle_after MUST be not empty.');

    // Decision tree:
    // 1. Context: the first symbol determines the *context* for the expression.
    // 2. Kind:
    //    - The last symbol determines whether it is an *ObjectProps expression.
    //    - If the last symbol did NOT indicate this is an *ObjectProps
    //      expression, then the next symbol (if any) determines whether this is
    //      a Reference* expression.
    //    - If it was neither, then this is a simple prop expression.
    return match ($root_expr_symbol_first) {
      // Field instances (require a host entity as context).
      StructuredDataPropExpressionInterface::PREFIX_ENTITY_LEVEL => match (TRUE) {
        $root_expr_symbol_last === StructuredDataPropExpressionInterface::SUFFIX_OBJECT => FieldObjectPropsExpression::fromString($representation),
        $root_expr_symbol_next === StructuredDataPropExpressionInterface::PREFIX_ENTITY_LEVEL => ReferenceFieldPropExpression::fromString($representation),
        default => FieldPropExpression::fromString($representation)
      },
      // Field types (require no context).
      default => match (TRUE) {
        $root_expr_symbol_last === StructuredDataPropExpressionInterface::SUFFIX_OBJECT => FieldTypeObjectPropsExpression::fromString($representation),
        $root_expr_symbol_next === StructuredDataPropExpressionInterface::PREFIX_ENTITY_LEVEL => ReferenceFieldTypePropExpression::fromString($representation),
        default => FieldTypePropExpression::fromString($representation)
      },
    };
  }

}
