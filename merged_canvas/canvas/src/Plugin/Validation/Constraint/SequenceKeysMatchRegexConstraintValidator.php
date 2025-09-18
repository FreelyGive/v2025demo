<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the SequenceKeysMatchRegex constraint.
 */
final class SequenceKeysMatchRegexConstraintValidator extends RegexValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    // If the value isn't NULL, it needs to be an associative array.
    if ($value === NULL) {
      return;
    }
    if (!is_array($value)) {
      throw new UnexpectedTypeException($value, 'array');
    }
    if ($value && array_is_list($value)) {
      throw new UnexpectedTypeException($value, 'associative array');
    }

    foreach (array_keys($value) as $key) {
      parent::validate($key, $constraint);
    }
  }

}
