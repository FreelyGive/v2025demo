<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

trait CompoundExpressionTrait {

  /**
   * Gets the representation without the structured data prefix `ℹ`.
   *
   * @param string $representation
   *   A string.
   *
   * @return string
   *   The same string without the `ℹ` prefix.
   */
  private static function withoutPrefix(string $representation): string {
    assert(mb_substr($representation, 0, 2) === StructuredDataPropExpressionInterface::PREFIX);
    return mb_substr($representation, mb_strlen(StructuredDataPropExpressionInterface::PREFIX));
  }

  /**
   * Gets the root expression from the given string representation expression.
   *
   * The root expression is the one that starts at position zero, and without
   * any composition. For example, the Reference* expressions are compound:
   * ReferenceFieldPropExpression uses FieldPropExpression, and
   * ReferenceFieldTypePropExpression uses FieldTypePropExpression.
   *
   * For example, for the expression
   * @code
   * ℹ︎image␟entity␜␜entity:file␝uri␞0␟{stream_wrapper_uri↠value,public_url↠url}
   * @endcode
   *
   * The top-level expression is
   * @code
   * ℹ︎image␟entity
   * @endcode
   *
   * And for
   * @code
   * ℹ︎␜entity:file␝uri␞0␟{stream_wrapper_uri↠value,public_url↠url}
   * @endcode
   *
   * it is that entire expression.
   *
   * @return string
   *   A substring of $expression_representation, representing the root
   *   expression.
   */
  private static function parseRootExpression(string $expression_representation): string {
    // Every expression representation MUST contains a property prefix (`␟`).
    $property_prefix_pos = mb_strpos($expression_representation, StructuredDataPropExpressionInterface::PREFIX_PROPERTY_LEVEL);
    assert(is_int($property_prefix_pos) && $property_prefix_pos < mb_strlen($expression_representation) - 1);

    // In case of an *ObjectProps expression, the first character after the
    // property prefix (`␟`) will be an open curly brace (`{`). Consequently
    // the corresponding matching closing brace (`}`) must be found, and is part
    // of this expression.
    // @code
    // ℹ︎␜entity:node␝title␞0␟{label↠value}
    // @endcode
    if (mb_substr($expression_representation, $property_prefix_pos + 1, 1) === StructuredDataPropExpressionInterface::PREFIX_OBJECT) {
      // Find the matching closing brace: simply the first one.
      // @todo If nested object expressions must one day be supported, this logic will need to be updated, because the closing brace will not be the first one anymore.
      $closing_brace_pos = mb_strpos($expression_representation, StructuredDataPropExpressionInterface::SUFFIX_OBJECT, $property_prefix_pos);
      return mb_substr($expression_representation, 0, $closing_brace_pos + 1);
    }

    // In case of a Reference* expression, the next character will be an entity
    // prefix (`␜`).
    $entity_prefix_pos = mb_strpos($expression_representation, StructuredDataPropExpressionInterface::PREFIX_ENTITY_LEVEL, $property_prefix_pos);
    return $entity_prefix_pos === FALSE
      // No entity prefix present : this already is the top-level expression:
      // @code
      // ℹ︎image␟entity
      // @endcode
      ? $expression_representation
      // Entity prefix is present, for example:
      // @code
      // ℹ︎image␟entity␜␜entity:file␝filemime␞0␟value
      // @endcode
      // Which means the top-level is:
      // @code
      // ℹ︎image␟entity
      // @endcode
      : mb_substr($expression_representation, 0, $entity_prefix_pos);
  }

}
