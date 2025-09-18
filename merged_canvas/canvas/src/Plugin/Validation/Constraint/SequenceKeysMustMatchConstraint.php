<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Checks the validated sequence contains the same keys as another sequence.
 *
 * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\SequenceKeyExistsConstraint
 */
#[Constraint(
  id: "SequenceKeysMustMatch",
  label: new TranslatableMarkup("Sequence keys must match.", [], ['context' => 'Validation']),
  type: "sequence",
)]
final class SequenceKeysMustMatchConstraint extends SequenceDependentConstraintBase {}
