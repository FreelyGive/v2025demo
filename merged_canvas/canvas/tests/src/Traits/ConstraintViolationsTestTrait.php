<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Symfony\Component\Validator\ConstraintViolationListInterface;

trait ConstraintViolationsTestTrait {

  /**
   * Transforms a constraint violation list object to an assertable array.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   Validation constraint violations.
   *
   * @return array
   *   An array with property paths as keys and violation messages as values.
   *
   * @see \Drupal\Tests\ckeditor5\Kernel\CKEditor5ValidationTestTrait::violationsToArray()
   */
  private static function violationsToArray(ConstraintViolationListInterface $violations): array {
    $actual_violations = [];
    foreach ($violations as $violation) {
      if (!isset($actual_violations[$violation->getPropertyPath()])) {
        $actual_violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
      }
      else {
        // Transform value from string to array.
        if (is_string($actual_violations[$violation->getPropertyPath()])) {
          $actual_violations[$violation->getPropertyPath()] = (array) $actual_violations[$violation->getPropertyPath()];
        }
        // And append.
        // @phpstan-ignore-next-line
        $actual_violations[$violation->getPropertyPath()][] = (string) $violation->getMessage();
      }
    }
    return $actual_violations;
  }

}
