<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * For pointing to a prop in a field type (not considering any delta).
 */
final class ReferenceFieldTypePropExpression implements StructuredDataPropExpressionInterface {

  use CompoundExpressionTrait;

  public function __construct(
    public readonly FieldTypePropExpression $referencer,
    public readonly FieldPropExpression|ReferenceFieldPropExpression|FieldObjectPropsExpression $referenced,
  ) {}

  public function __toString(): string {
    return static::PREFIX
      . self::withoutPrefix((string) $this->referencer)
      . self::PREFIX_ENTITY_LEVEL
      . self::withoutPrefix((string) $this->referenced);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $field_item_list = NULL): array {
    assert($field_item_list === NULL || $field_item_list instanceof FieldItemListInterface);
    $dependencies = $this->referencer->calculateDependencies();
    if ($field_item_list !== NULL) {
      // ⚠️ Do not require values while calculating dependencies: this MUST not
      // fail.
      $referenced_content_entities = Evaluator::evaluate($field_item_list, $this->referencer, is_required: FALSE);
      $referenced_content_entities = match (gettype($referenced_content_entities)) {
        // Reference field containing nothing.
        'null' => [],
        // Reference field containing multiple references.
        'array' => $referenced_content_entities,
        // Reference field containing a single reference.
        default => [$referenced_content_entities],
      };
      $dependencies['content'] = [
        ...$dependencies['content'] ?? [],
        ...array_map(
          fn (FieldableEntityInterface $entity) => $entity->getConfigDependencyName(),
          $referenced_content_entities,
        ),
      ];
    }
    return NestedArray::mergeDeep($dependencies, $this->referenced->calculateDependencies());
  }

  public static function fromString(string $representation): static {
    $parts = explode(self::PREFIX_ENTITY_LEVEL . self::PREFIX_ENTITY_LEVEL, $representation, 2);
    $referencer = FieldTypePropExpression::fromString($parts[0]);
    $referenced = StructuredDataPropExpression::fromString(static::PREFIX . static::PREFIX_ENTITY_LEVEL . $parts[1]);
    assert($referenced instanceof FieldPropExpression || $referenced instanceof ReferenceFieldPropExpression || $referenced instanceof FieldObjectPropsExpression);
    return new static($referencer, $referenced);
  }

  public function isSupported(EntityInterface|FieldItemInterface|FieldItemListInterface $field): bool {
    assert($field instanceof FieldItemInterface || $field instanceof FieldItemListInterface);
    $actual_field_type = $field->getFieldDefinition()->getType();
    if ($actual_field_type !== $this->referencer->fieldType) {
      throw new \DomainException(sprintf("`%s` is an expression for field type `%s`, but the provided field item (list) is of type `%s`.", (string) $this, $this->referencer->fieldType, $actual_field_type));
    }
    return TRUE;
  }

  /**
   * @todo Consider adding such helpers to all StructuredDataPropExpressionInterface implementations?
   * */
  public function getFieldDefinition(): FieldDefinitionInterface {
    if (!$this->referenced instanceof FieldPropExpression) {
      throw new \LogicException('Not supported.');
    }
    // @phpstan-ignore-next-line
    return $this->referenced->entityType
      // @phpstan-ignore-next-line
      ->getPropertyDefinition($this->referenced->fieldName);
  }

}
