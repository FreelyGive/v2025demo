<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Traits;

/**
 * Avoids `public://cat.jpg` resolving to `/vfs://root/sites/…/cat.jpg`.
 *
 * @see \Drupal\Tests\system\Kernel\Theme\TwigIncludeTest::setUpFilesystem()
 */
trait VfsPublicStreamUrlTrait {

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem(): void {
    // Use a real file system and not VFS so that we can include files from the
    // site using @__main__ in a template.
    $public_file_directory = $this->siteDirectory . '/files';
    $private_file_directory = $this->siteDirectory . '/private';

    mkdir($this->siteDirectory, 0775);
    mkdir($this->siteDirectory . '/files', 0775);
    mkdir($this->siteDirectory . '/private', 0775);
    mkdir($this->siteDirectory . '/files/config/sync', 0775, TRUE);

    $this->setSetting('file_public_path', $public_file_directory);
    $this->setSetting('file_private_path', $private_file_directory);
    $this->setSetting('config_sync_directory', $this->siteDirectory . '/files/config/sync');
  }

}
