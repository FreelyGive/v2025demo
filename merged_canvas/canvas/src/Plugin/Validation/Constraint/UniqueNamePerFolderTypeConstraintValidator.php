<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\canvas\Entity\Folder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates the UniqueNamePerFolderTypeConstraint constraint.
 *
 * @internal
 */
final class UniqueNamePerFolderTypeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof UniqueNamePerFolderTypeConstraint) {
      throw new UnexpectedTypeException($constraint, UnexpectedTypeException::class);
    }

    if (!is_string($value)) {
      throw new UnexpectedValueException($value, 'string');
    }

    // @phpstan-ignore argument.type
    $configEntityTypeId = TypeResolver::resolveDynamicTypeName("[$constraint->configEntityTypeId]", $this->context->getObject());
    // @phpstan-ignore argument.type
    $id = TypeResolver::resolveDynamicTypeName("[$constraint->id]", $this->context->getObject());
    $folder = Folder::loadByNameAndConfigEntityTypeId($value, $configEntityTypeId);

    if ($folder instanceof Folder && $folder->id() !== $id) {
      $this->context->addViolation($constraint->notUnique, [
        '%value' => $value,
        '%configEntityTypeId' => $configEntityTypeId,
      ]);
    }
  }

}
