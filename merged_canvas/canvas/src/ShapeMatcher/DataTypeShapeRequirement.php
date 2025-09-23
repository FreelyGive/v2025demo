<?php

declare(strict_types=1);

namespace Drupal\canvas\ShapeMatcher;

/**
 * Describes a single shape requirement for a Drupal data type.
 *
 * @see \Drupal\Core\TypedData\Attribute\DataType
 * @see \Drupal\canvas\JsonSchemaInterpreter\DataTypeShapeRequirements
 */
final class DataTypeShapeRequirement implements \IteratorAggregate {

  /**
   * @param array<mixed> $constraintOptions
   */
  public function __construct(
    public readonly string $constraint,
    public readonly array $constraintOptions,
    // Restricting by interface makes sense in combination with \Drupal\Core\Validation\Plugin\Validation\Constraint\PrimitiveTypeConstraintValidator.
    public readonly ?string $interface = NULL,
  ) {
    if ($this->constraint === 'PrimitiveType' && $interface === NULL) {
      throw new \DomainException('The `PrimitiveType` constraint is meaningless without an interface restriction.');
    }
    if ($this->interface !== NULL && $this->constraint !== 'PrimitiveType') {
      throw new \DomainException('An interface restriction only makes sense when the `PrimitiveType` constraint is used.');
    }
  }

  public function getIterator(): \Traversable {
    return new \ArrayIterator([$this]);
  }

}
