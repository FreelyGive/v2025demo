<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\canvas\CanvasConfigUpdater;

trait ConfigUpdaterAwareEntityTrait {

  protected static function getConfigUpdater(): CanvasConfigUpdater {
    return \Drupal::service(CanvasConfigUpdater::class);
  }

}
