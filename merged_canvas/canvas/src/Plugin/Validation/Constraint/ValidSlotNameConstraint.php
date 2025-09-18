<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates a slot's machine name.
 */
#[Constraint(
  id: 'ValidSlotName',
  label: new TranslatableMarkup('Validates a slot name', [], ['context' => 'Validation']),
)]
final class ValidSlotNameConstraint extends SymfonyConstraint {

  /**
   * The regular expression used to validate a slot name.
   *
   * Valid examples: valid, valid-name, valid_name.
   * Invalid examples: a, aa, -, _, -invalid, _invalid, invalid-, invalid_.
   */
  public const string VALID_NAME = '/^[a-zA-Z0-9]+([a-zA-Z0-9_-]+)[a-zA-Z0-9]+$/';

  /**
   * A list of names that the regex above considers valid, but are banned.
   *
   * @var string[]
   */
  public const array BAN_LIST = [
    // @see https://react.dev/reference/react/Children
    'children',
  ];

}
