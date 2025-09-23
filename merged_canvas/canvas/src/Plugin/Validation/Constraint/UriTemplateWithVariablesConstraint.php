<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Validates a URI template has certain variables', [], ['context' => 'Validation']),
)]
final class UriTemplateWithVariablesConstraint extends SymfonyConstraint {

  public const string PLUGIN_ID = 'UriTemplateWithVariables';

  /**
   * The variables that are required to be present in the URI template.
   *
   * @var string[]
   */
  public array $requiredVariables = [];

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions() : array {
    return ['requiredVariables'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption() : ?string {
    return NULL;
  }

}
