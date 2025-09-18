<?php

declare(strict_types=1);

namespace Drupal\canvas_test_validation\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldValueValidator;
use Symfony\Component\Validator\Constraint;

final class TestUniqueFieldConstraintValidator extends UniqueFieldValueValidator {

  // @phpstan-ignore-next-line
  public function validate($items, Constraint $constraint): void {
    assert($items instanceof FieldItemListInterface);
    // Avoid affecting all tests — require this string for the constraint to run.
    if (!str_contains($items->getString(), 'unique!')) {
      return;
    }
    parent::validate($items, $constraint);
  }

}
