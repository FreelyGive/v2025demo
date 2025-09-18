<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\Plugin\Validation\Constraint\ConfigExistsConstraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Adds dynamic replacement pattern support to the `ConfigExists` constraint.
 *
 * @todo Remove this when core supports dynamic replacements in `ConfigExists`,
 *   in https://www.drupal.org/project/drupal/issues/3518273.
 */
#[Constraint(
  id: 'BetterConfigExists',
  label: new TranslatableMarkup('Config exists, only better', [], ['context' => 'Validation'])
)]
final class BetterConfigExistsConstraint extends ConfigExistsConstraint {
}
