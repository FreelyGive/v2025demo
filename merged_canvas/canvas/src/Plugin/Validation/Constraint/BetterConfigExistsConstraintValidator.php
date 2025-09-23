<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\Plugin\Validation\Constraint\ConfigExistsConstraintValidator;
use Drupal\Core\Config\Schema\TypeResolver;
use Symfony\Component\Validator\Constraint;

/**
 * Validates the `BetterConfigExists` constraint.
 *
 * @todo Remove this when core supports dynamic replacements in `ConfigExists`,
 *    in https://www.drupal.org/project/drupal/issues/3518273.
 */
final class BetterConfigExistsConstraintValidator extends ConfigExistsConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $name, Constraint $constraint): void {
    assert($constraint instanceof BetterConfigExistsConstraint);
    // Ignore this line because core has incorrect parameter documentation.
    // @phpstan-ignore-next-line
    $constraint->prefix = TypeResolver::resolveDynamicTypeName($constraint->prefix, $this->context->getObject());
    parent::validate($name, $constraint);
  }

}
