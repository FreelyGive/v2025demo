<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the SequenceKeyExists constraint.
 */
final class SequenceKeyExistsConstraintValidator extends SequenceDependentConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if ($value === NULL) {
      // This should be enforced by other validation.
      return;
    }
    if (!is_string($value)) {
      throw new UnexpectedTypeException($value, 'string');
    }
    if (!$constraint instanceof SequenceKeyExistsConstraint) {
      throw new UnexpectedTypeException($constraint, SequenceKeyExistsConstraint::class);
    }

    $existing_sequence_keys = $this->getSequenceKeys($constraint);

    if (!in_array($value, $existing_sequence_keys, TRUE)) {
      $this->context->addViolation($constraint->message, [
        '@value' => $value,
        '@property_path' => $constraint->propertyPathToSequence,
      ]);
    }
  }

}
