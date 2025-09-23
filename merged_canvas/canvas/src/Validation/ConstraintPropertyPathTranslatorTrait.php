<?php

declare(strict_types=1);

namespace Drupal\canvas\Validation;

use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Defines a trait for translating property paths for constraint violations.
 *
 * @internal
 */
trait ConstraintPropertyPathTranslatorTrait {

  protected static function translateConstraintPropertyPathsAndRoot(array $map, ConstraintViolationListInterface $violations, mixed $newRoot = NULL): ConstraintViolationListInterface {
    foreach ($map as $prefix_original => $prefix_new) {
      foreach ($violations as $key => $v) {
        if (str_starts_with($v->getPropertyPath(), $prefix_original)) {
          $violations[$key] = new ConstraintViolation(
            $v->getMessage(),
            $v->getMessageTemplate(),
            $v->getParameters(),
            $newRoot ?? $v->getRoot(),
            preg_replace('/^' . preg_quote($prefix_original, '/') . '/', $prefix_new, $v->getPropertyPath()),
            $v->getInvalidValue(),
            $v->getPlural(),
            $v->getCode(),
            $v->getConstraint(),
            $v->getCause(),
          );
        }
      }
    }
    return $violations;
  }

}
