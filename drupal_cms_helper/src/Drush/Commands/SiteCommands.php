<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Drush\Commands;

use Composer\InstalledVersions;
use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\drupal_cms_helper\SiteExporter;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class SiteCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly SiteExporter $exporter,
    private readonly FileSystemInterface $fileSystem,
  ) {
    parent::__construct();
  }

  /**
   * Exports the site's configuration and content as a recipe.
   */
  #[CLI\Command(
    name: 'site:export',
  )]
  #[CLI\Option(
    name: 'destination',
    description: 'The destination directory where the site should be exported. Defaults to the location where Composer installs recipes.',
  )]
  public function export(array $options = ['destination' => NULL]): void {
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    $project_root = $this->fileSystem->realpath($project_root);
    $data = file_get_contents($project_root . DIRECTORY_SEPARATOR . 'composer.json');
    $data = Json::decode($data);

    $installer_paths = $data['extra']['installer-paths'] ?? [];
    foreach ($installer_paths as $path => $criteria) {
      if (in_array('type:' . Recipe::COMPOSER_PROJECT_TYPE, $criteria, TRUE)) {
        $path = ltrim($path, '.' . DIRECTORY_SEPARATOR);
        $options['destination'] ??= str_replace('{$name}', 'site_export', $project_root . DIRECTORY_SEPARATOR . $path);
      }
    }
    $this->exporter->export(
      $options['destination'] ?? throw new \RuntimeException('Could not determine where Composer installs recipes.'),
    );
    $this->io()->success('Recipe created at ' . $options['destination']);
  }

}
