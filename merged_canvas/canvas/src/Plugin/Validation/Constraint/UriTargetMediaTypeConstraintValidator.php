<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UriTargetMediaTypeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof UriTargetMediaTypeConstraint) {
      throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\UriTargetMediaTypeConstraint');
    }
    assert(self::isValidWildCard($value) || self::isValid($value));

    // No-op.
  }

  /**
   * Validates wildcard MIME type: specifying only the media type.
   *
   * Example: `image/*`, `video/*`.
   */
  public static function isValidWildCard(string $mimetype): bool {
    return preg_match('/\w+\/\*/', $mimetype) === 1;
  }

  /**
   * Validates MIME type: type, subtype and optionally a suffix.
   *
   * Example: `image/avif`, `application/json`.
   */
  public static function isValid(string $mimetype): bool {
    return preg_match('/\w+\/\*/', $mimetype) === 1;
  }

}
