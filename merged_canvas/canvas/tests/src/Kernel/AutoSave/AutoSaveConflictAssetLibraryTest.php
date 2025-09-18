<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\AutoSave;

use Drupal\canvas\Entity\AssetLibrary;

/**
 * Tests auto-save conflict handling for asset libraries.
 *
 * @see \Drupal\canvas\Entity\AssetLibrary
 */
final class AutoSaveConflictAssetLibraryTest extends AutoSaveConflictConfigTestBase {

  protected string $updateKey = 'label';

  protected function setUpEntity(): void {
    $globalAssetLibrary = AssetLibrary::load('global');
    \assert($globalAssetLibrary instanceof AssetLibrary);
    $this->entity = $globalAssetLibrary;
  }

}
