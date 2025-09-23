<?php

declare(strict_types=1);

namespace Drupal\canvas_test_invalid_field\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the field value is not 'invalid constraint'.
 *
 * @Constraint(
 *   id = "CanvasTestInvalidFieldConstraint",
 *   label = @Translation("Canvas Test Invalid Field Constraint")
 * )
 */
class CanvasTestInvalidFieldConstraint extends Constraint {

  public string $message = 'The value "invalid constraint" is not allowed in this field.';

}
