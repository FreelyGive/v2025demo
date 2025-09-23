<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Choice;

#[Constraint(
  id: 'ValidStructuredDataPropExpression',
  label: new TranslatableMarkup('Validates a field prop expression', [], ['context' => 'Validation']),
)]
final class ValidStructuredDataPropExpressionConstraint extends Choice {}
