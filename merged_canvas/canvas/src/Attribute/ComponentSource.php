<?php

declare(strict_types=1);

namespace Drupal\canvas\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an attribute for a component source.
 *
 * @see \Drupal\canvas\ComponentSource\ComponentSourceInterface
 * @see \Drupal\canvas\ComponentSource\ComponentSourceManager
 * @see \Drupal\canvas\ComponentSource\ComponentSourceBase
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ComponentSource extends Plugin {

  /**
   * @param string $id
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   * @param class-string|null $deriver
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly bool $supportsImplicitInputs,
    public readonly ?string $deriver = NULL,
  ) {
  }

}
