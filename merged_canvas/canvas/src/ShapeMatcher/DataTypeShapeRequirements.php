<?php

declare(strict_types=1);

namespace Drupal\canvas\ShapeMatcher;

/**
 * Describes a set of shape requirements for a Drupal data type.
 *
 * @see \Drupal\canvas\ShapeMatcher\DataTypeShapeRequirement
 */
final class DataTypeShapeRequirements implements \IteratorAggregate {

  /**
   * @param \Drupal\canvas\ShapeMatcher\DataTypeShapeRequirement[] $requirements
   */
  public function __construct(
    public readonly array $requirements,
  ) {
    foreach ($this->requirements as $requirement) {
      if (!$requirement instanceof DataTypeShapeRequirement) {
        throw new \LogicException();
      }
    }
  }

  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->requirements);
  }

}
