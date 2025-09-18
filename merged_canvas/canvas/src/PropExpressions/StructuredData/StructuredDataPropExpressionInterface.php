<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\canvas\PropExpressions\PropExpressionInterface;
use Drupal\canvas\PropSource\ContentAwareDependentInterface;

interface StructuredDataPropExpressionInterface extends PropExpressionInterface, ContentAwareDependentInterface {

  // Structured data contains information, hence a prefix that conveys that..
  const PREFIX = 'ℹ︎';

  // All prefixes for denoting the pieces inside structured data expressions.
  // @see https://github.com/SixArm/usv
  const PREFIX_ENTITY_LEVEL = '␜';
  const PREFIX_FIELD_LEVEL = '␝';
  const PREFIX_FIELD_ITEM_LEVEL = '␞';
  const PREFIX_PROPERTY_LEVEL = '␟';

  const PREFIX_OBJECT = '{';
  const SUFFIX_OBJECT = '}';
  const SYMBOL_OBJECT_MAPPED_FOLLOW_REFERENCE = '↝';
  const SYMBOL_OBJECT_MAPPED_USE_PROP = '↠';
  const SYMBOL_OBJECT_MAPPED_OPTIONAL_PROP = '␀';

  /**
   * Assesses whether the given evaluation context is supported.
   *
   * @param \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Field\FieldItemInterface|\Drupal\Core\Field\FieldItemListInterface $entity_or_field
   *   Possibilities are:
   *   - An entity when the expression starts in an entity.
   *   - A field item list when the expression starts in a multiple-cardinality
   *     field type.
   *   - A field item when the expression starts in a single-cardinality field
   *     type.
   *
   * @return bool
   */
  public function isSupported(EntityInterface|FieldItemInterface|FieldItemListInterface $entity_or_field): bool;

}
