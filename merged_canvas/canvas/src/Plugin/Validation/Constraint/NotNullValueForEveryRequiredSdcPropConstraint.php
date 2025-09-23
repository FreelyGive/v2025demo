<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Validation constraint for checking required SDC prop values.
 */
#[Constraint(
  id: 'NotNullValueForEveryRequiredSdcProp',
  label: new TranslatableMarkup('Validates required SDC prop values are not null'),
  type: ['mapping']
)]
class NotNullValueForEveryRequiredSdcPropConstraint extends SymfonyConstraint {

  /**
   * The SDC plugin ID.
   *
   * @var string
   */
  public string $sdcPluginId;

  /**
   * The validation error message.
   *
   * @var string
   */
  public $message = 'The required SDC prop "%prop_title" (%prop_machine_name) must not be null.';

  /**
   * Returns the array of required options.
   *
   * @return array
   *   Array of required option names.
   */
  public function getRequiredOptions(): array {
    return ['sdcPluginId'];
  }

  /**
   * Returns the default option for this constraint.
   *
   * @return string|null
   *   The default option name or NULL if no default value.
   */
  public function getDefaultOption(): ?string {
    return 'sdcPluginId';
  }

}
