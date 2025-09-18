<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * StringSemantics constraint.
 */
final class StringSemanticsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof StringSemanticsConstraint) {
      throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\StringSemantics');
    }
    assert(in_array($constraint->semantic, [
      StringSemanticsConstraint::PROSE,
      StringSemanticsConstraint::MARKUP,
      StringSemanticsConstraint::STRUCTURED,
    ], TRUE));

    // No-op.
  }

}
