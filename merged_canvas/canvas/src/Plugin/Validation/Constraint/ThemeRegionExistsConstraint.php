<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * @internal
 */
#[Constraint(
  id: 'ThemeRegionExists',
  label: new TranslatableMarkup('@todo', [], ['context' => 'Validation']),
  type: ['string']
)]
class ThemeRegionExistsConstraint extends SymfonyConstraint {

  public string $message = "Region '@region' does not exist in theme '@theme'.";

  /**
   * The machine name of the theme for which this must be a valid region.
   */
  public string $theme;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['theme'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return 'theme';
  }

}
