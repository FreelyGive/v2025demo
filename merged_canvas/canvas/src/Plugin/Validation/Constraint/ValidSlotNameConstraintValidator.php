<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

/**
 * Validates the ValidSlotName constraint.
 */
final class ValidSlotNameConstraintValidator extends RegexValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof ValidSlotNameConstraint);
    if ($value === NULL) {
      return;
    }

    // The value could either be a string (the slot name) or a mapping that
    // defines a slot (see `type: canvas.slot_definition` in
    // canvas.schema.yml), in a sequence of slot definitions, in
    // which case the mapping's name should be the slot name.
    if (!is_string($value)) {
      $data = $this->context->getObject();
      assert($data instanceof TypedDataInterface);
      $value = $data->getName();
    }
    assert(is_string($value));

    if (in_array($value, $constraint::BAN_LIST, TRUE)) {
      $this->context->buildViolation('%value is not a valid slot name.')
        // TRICKY: match the weird formatting that RegexValidator uses 🤷‍♂️
        // @see \Symfony\Component\Validator\ConstraintValidator::formatValue()
        ->setParameter('%value', $this->formatValue($value))
        ->addViolation();
      return;
    }

    parent::validate($value, new Regex($constraint::VALID_NAME, '%value is not a valid slot name.'));
  }

}
