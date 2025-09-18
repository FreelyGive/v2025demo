<?php

declare(strict_types=1);

namespace Drupal\canvas\Exception;

use Drupal\canvas\Validation\ConstraintPropertyPathTranslatorTrait;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Defines an exception for a constraint violation.
 */
final class ConstraintViolationException extends \Exception {

  use ConstraintPropertyPathTranslatorTrait;

  public function __construct(protected ConstraintViolationListInterface $constraintViolationList, string $message = 'Validation errors exist') {
    parent::__construct("$message:\n $this->constraintViolationList");
  }

  public function renamePropertyPaths(array $map): self {
    $this->constraintViolationList = $this->translateConstraintPropertyPathsAndRoot($map, $this->constraintViolationList);
    return $this;
  }

  /**
   * Gets value of ConstraintViolationList.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   Value of ConstraintViolationList.
   */
  public function getConstraintViolationList(): ConstraintViolationListInterface {
    return $this->constraintViolationList;
  }

}
