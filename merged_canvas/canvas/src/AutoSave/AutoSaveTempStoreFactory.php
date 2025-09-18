<?php

declare(strict_types=1);

namespace Drupal\canvas\AutoSave;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\TempStore\SharedTempStoreFactory;

/**
 * Defines a factory for the auto-save tempstore.
 */
final class AutoSaveTempStoreFactory extends SharedTempStoreFactory {

  /**
   * {@inheritdoc}
   */
  public function get($collection, $owner = NULL, ?int $expire = NULL): AutoSaveTempStore {
    // Use the currently authenticated user ID or the active user ID unless
    // the owner is overridden.
    if ($owner === NULL) {
      $owner = $this->currentUser->id();
      if ($this->currentUser->isAnonymous()) {
        $owner = $this->requestStack->getSession()->get('core.tempstore.shared.owner', Crypt::randomBytesBase64());
      }
    }

    // Store the data for this collection in the database.
    $storage = $this->storageFactory->get("tempstore.shared.$collection");
    return new AutoSaveTempStore($storage, $this->lockBackend, $owner, $this->requestStack, $this->currentUser, $expire ?? $this->expire);
  }

}
