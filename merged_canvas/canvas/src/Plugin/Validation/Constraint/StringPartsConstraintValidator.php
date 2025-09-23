<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the StringParts constraint.
 *
 * @todo Remove this when https://www.drupal.org/i/3324140 lands.
 */
class StringPartsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!is_string($value)) {
      throw new UnexpectedTypeException($value, 'string');
    }
    if (!$constraint instanceof StringPartsConstraint) {
      throw new UnexpectedTypeException($constraint, StringPartsConstraint::class);
    }

    assert($this->context->getObject() instanceof TypedDataInterface);
    $resolved_parts = array_map(
      fn (string $expression): mixed => TypeResolver::resolveExpression($expression, $this->context->getObject()),
      $constraint->parts
    );

    // Verify the required parts are present; if not, that's a logical error in
    // the config schema, not in concrete config.
    $missing_properties = array_intersect($constraint->parts, $resolved_parts);
    if (!empty($missing_properties)) {
      $this->context->buildViolation('This validation constraint is configured to inspect the properties %properties, but some do not exist: %missing_properties.')
        ->setParameter('%properties', implode(', ', $constraint->parts))
        ->setParameter('%missing_properties', implode(', ', $missing_properties))
        ->addViolation();
      return;
    }

    // Retrieve the parts of the expected string.
    $expected_string_parts = [];
    foreach ($constraint->parts as $index => $part) {
      $part_value = $resolved_parts[$index];
      if (!is_string($part_value)) {
        throw new \LogicException(sprintf('The "%s" property does not contain a string, but a %s: "%s".', $part, gettype($part_value), (string) $part_value));
      }
      $expected_string_parts[] = $part_value;
    }
    if (!empty($constraint->reservedCharacters) && !empty($constraint->reservedCharactersSubstitute)) {
      $expected_string_parts = str_replace($constraint->reservedCharacters, $constraint->reservedCharactersSubstitute, $expected_string_parts);
    }
    $expected_string = implode($constraint->separator, $expected_string_parts);

    if ($expected_string !== $value) {
      $expected_format = implode(
        $constraint->separator,
        array_map(function (string $v) {
          return sprintf('<%s>', $v);
        }, $constraint->parts)
      );
      $this->context->addViolation($constraint->message, [
        '@value' => $value,
        '@expected_string' => $expected_string,
        '@expected_format' => $expected_format,
      ]);
    }
  }

}
