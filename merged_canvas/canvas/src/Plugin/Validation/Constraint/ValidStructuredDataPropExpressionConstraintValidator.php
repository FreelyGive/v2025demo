<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\ChoiceValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates that the given expression is parseable/makes sense.
 *
 * Note that this does NOT verify the contents of the expression, only the
 * structure: it does not validate whether field types & names exist, etc.
 */
final class ValidStructuredDataPropExpressionConstraintValidator extends ChoiceValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof ValidStructuredDataPropExpressionConstraint);
    if ($value === NULL) {
      return;
    }

    if (!is_string($value)) {
      throw new UnexpectedValueException($value, 'string');
    }

    try {
      // The expression must be parseable.
      $parsed = StructuredDataPropExpression::fromString($value);
      // If parseable, its class must be one of the allowed ones.
      $class_name = array_slice(explode('\\', get_class($parsed)), -1)[0];
      parent::validate($class_name, $constraint);
    }
    catch (\Throwable) {
      $this->context->buildViolation('%value is not a valid prop expression.')
        ->setParameter('%value', $value)
        ->addViolation();
    }
  }

}
