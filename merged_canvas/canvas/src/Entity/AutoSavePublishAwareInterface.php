<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an interface for entities that have specific actions on auto-save publish.
 */
interface AutoSavePublishAwareInterface extends EntityInterface {

  public function autoSavePublish(): self;

}
