<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Checks the validated string contains one of the keys of a sequence.
 *
 * @see \Drupal\canvas\Plugin\Validation\Constraint\SequenceKeysMustMatchConstraint
 */
#[Constraint(
  id: 'SequenceKeyExists',
  label: new TranslatableMarkup('String is a key of a sequence', [], ['context' => 'Validation']),
  type: ['string']
)]
class SequenceKeyExistsConstraint extends SequenceDependentConstraintBase {

  /**
   * The error message.
   *
   * @var string
   */
  public string $message = "The '@value' is not a key that exists on @property_path.";

}
