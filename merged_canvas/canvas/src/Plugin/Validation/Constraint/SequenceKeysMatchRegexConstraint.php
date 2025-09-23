<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Regex;

#[Constraint(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Sequence keys match regex', options: ['context' => 'Validation']),
)]
final class SequenceKeysMatchRegexConstraint extends Regex {

  public const string PLUGIN_ID = 'SequenceKeysMatchRegex';

}
