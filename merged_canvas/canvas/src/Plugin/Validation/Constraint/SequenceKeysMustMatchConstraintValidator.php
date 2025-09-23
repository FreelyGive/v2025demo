<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Validation\Plugin\Validation\Constraint\ValidKeysConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the SequenceKeysMustMatch constraint.
 */
final class SequenceKeysMustMatchConstraintValidator extends SequenceDependentConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!is_array($value)) {
      throw new UnexpectedTypeException($value, 'sequence');
    }
    if (!$constraint instanceof SequenceKeysMustMatchConstraint) {
      throw new UnexpectedTypeException($constraint, SequenceKeysMustMatchConstraint::class);
    }

    $expected_sequence_keys = $this->getSequenceKeys($constraint);

    $missing_keys = array_diff($expected_sequence_keys, array_keys($value));
    $invalid_keys = array_diff(array_keys($value), $expected_sequence_keys);
    if (empty($missing_keys) && empty($invalid_keys)) {
      return;
    }

    // Reuse the messages from the ValidKeysConstraint when missing or invalid
    // keys are found.
    $valid_keys_constraint = new ValidKeysConstraint('<infer>');
    foreach ($missing_keys as $key) {
      $this->context->addViolation($valid_keys_constraint->missingRequiredKeyMessage, ['@key' => $key]);
    }
    foreach ($invalid_keys as $key) {
      $this->context->buildViolation($valid_keys_constraint->invalidKeyMessage)
        ->setParameter('@key', $key)
        ->atPath((string) $key)
        ->setInvalidValue($key)
        ->addViolation();
    }
  }

}
