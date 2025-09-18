<?php

declare(strict_types=1);

namespace Drupal\canvas\EntityHandlers;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Entity\AssetLibrary;

/**
 * Defines an access control handler for Asset library entities.
 */
final class AssetLibraryAccessControlHandler extends ContentCreatorVisibleCanvasConfigEntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    if ($operation === 'delete' && $entity->id() === AssetLibrary::GLOBAL_ID) {
      return AccessResult::forbidden('The global asset library cannot be deleted');
    }
    return parent::checkAccess($entity, $operation, $account);
  }

}
