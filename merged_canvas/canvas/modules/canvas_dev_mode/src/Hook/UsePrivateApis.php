<?php

declare(strict_types=1);

namespace Drupal\canvas_dev_mode\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * @file
 * Hook implementations that make private APIs available ahead of being ready.
 *
 * ⚠️ Installing this module and developing against private (alpha) APIs does
 * mean you agree to chasing API changes until they become public!
 */
readonly final class UsePrivateApis {

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter', order: Order::Last)]
  public function configSchemaInfoAlter(array &$definitions): void {
    // Allow any ComponentSource plugin to be used.
    // @see \Drupal\canvas\ComponentSource\ComponentSourceInterface
    // @todo Remove this constraint after https://www.drupal.org/i/3520484#stable is done.
    unset($definitions['canvas.component.*']['mapping']['source']['constraints']['Choice']);
  }

}
