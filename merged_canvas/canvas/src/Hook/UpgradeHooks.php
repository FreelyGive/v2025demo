<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\canvas\CanvasConfigUpdater;
use Drupal\field\Entity\FieldConfig;

final class UpgradeHooks {

  public function __construct(
    private readonly CanvasConfigUpdater $configUpdater,
  ) {
  }

  #[Hook('field_config_presave')]
  public function fieldConfigPreSave(FieldConfig $field): void {
    $this->configUpdater->updateConfigEntityWithComponentTreeInputs($field);
  }

}
