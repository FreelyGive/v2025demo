<?php

declare(strict_types=1);

namespace Drupal\canvas_test_validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldConstraint;

#[Constraint(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Validates fields are unique', [], ['context' => 'Validation']),
)]
class TestUniqueFieldConstraint extends UniqueFieldConstraint {

  public const string PLUGIN_ID = 'canvas_test_validation_unique_field';

  public function validatedBy(): string {
    return TestUniqueFieldConstraintValidator::class;
  }

}
