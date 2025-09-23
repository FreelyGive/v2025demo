<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * A list of constraint violations for an entity.
 *
 * We cannot use \Drupal\Core\Entity\EntityConstraintViolationList because it
 * only supports FieldableEntityInterface, and we need to support
 * \Drupal\Core\Config\Entity\ConfigEntityInterface also.
 *
 * @todo Remove this once https://www.drupal.org/project/drupal/issues/2300677 ships in Drupal core.
 */
final class EntityConstraintViolationList extends ConstraintViolationList {

  public function __construct(public readonly EntityInterface $entity, iterable $violations = []) {
    parent::__construct($violations);
  }

  public static function fromCoreConstraintViolationList(EntityConstraintViolationListInterface $violation_list): self {
    return new static($violation_list->getEntity(), $violation_list);
  }

}
