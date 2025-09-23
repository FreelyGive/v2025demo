<?php

declare(strict_types=1);

namespace Drupal\canvas_test_invalid_field\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CanvasTestInvalidField constraint.
 */
class CanvasTestInvalidFieldConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if ($value === NULL) {
      return;
    }

    if ($value->value === 'invalid constraint') {
      assert($constraint instanceof CanvasTestInvalidFieldConstraint);
      $this->context->addViolation($constraint->message);
    }
  }

}
