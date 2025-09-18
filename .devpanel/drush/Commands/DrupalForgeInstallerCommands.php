<?php

namespace Drush\Commands;

use Composer\InstalledVersions;
use Drupal\Core\Recipe\Recipe;
use Drush\Attributes as CLI;
/**
 * Drush commands used during installation.
 */
final class DrupalForgeInstallerCommands extends DrushCommands {

  protected $contribRecipesPath;

  /**
   * Prepare Drush to be used while installation is in progress.
   */
  public function __construct() {
    // Tell Drush that we are in the process of installing.
    $GLOBALS['install_state']['theme'] = 'drupal_cms_installer_theme';
    // Set the contrib recipes path.
    $contrib_recipe_names = InstalledVersions::getInstalledPackagesByType(Recipe::COMPOSER_PROJECT_TYPE);
    $path = InstalledVersions::getInstallPath(reset($contrib_recipe_names));
    $this->contribRecipesPath = realpath(dirname($path));
  }

  /**
   * Command to get the contrib recipes path.
   */
  #[CLI\Command(name: 'drupalforge:contrib-recipes-path', aliases: ['contrib-recipes-path', 'crp'])]
  #[CLI\Usage(name: 'drupalforge:contrib-recipes-path', description: 'Emit the path to contrib recipes.')]
  public function getContribRecipesPath() {
    return $this->contribRecipesPath;
  }

}
