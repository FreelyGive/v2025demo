<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @internal
 */
final class UriTemplateWithVariablesConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Validator\Exception\UnexpectedTypeException
   *   Thrown when the given constraint is not supported by this validator.
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof UriTemplateWithVariablesConstraint) {
      throw new UnexpectedTypeException($constraint, UriTemplateWithVariablesConstraint::class);
    }

    if ($value === NULL) {
      return;
    }
    elseif (!is_string($value)) {
      throw new UnexpectedValueException($value, 'string');
    }

    $expected_placeholders = array_map(fn (string $name): string => "{$name}", $constraint->requiredVariables);

    foreach ($expected_placeholders as $required_placeholder) {
      if (!str_contains($value, $required_placeholder)) {
        $this->context->buildViolation("Missing `$value` placeholder.")
          ->setInvalidValue($value)
          ->addViolation();
      }
    }
  }

}
