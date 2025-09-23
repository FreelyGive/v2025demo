<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Traits;

/**
 * @internal
 */
trait CiModulePathTrait {

  /**
   * Determines the tested module's path; for use in data providers.
   *
   * Data providers run before the container has booted, so cannot use core's
   * existing infrastructure for determining a module's path.
   *
   * @see \Drupal\Tests\ExtensionListTestTrait::getModulePath()
   */
  public static function getCiModulePath(): string {
    $module_dir = dirname(__DIR__, 4);
    $parts = explode(\DIRECTORY_SEPARATOR, $module_dir);
    $modules_index = \array_search('modules', $parts);
    if ($modules_index === FALSE) {
      // On CI, the project folder is installed outside the webroot and
      // symlinked inside it. In that case __DIR__ does not include modules in
      // a parent path. However, there is a convenient DRUPAL_PROJECT_FOLDER
      // environment variable that gives us the symlinked path. We can use that
      // to work out where the module is installed relative to the Drupal root.
      // We don't have access to the Drupal root from the kernel here because
      // we're in a static data provider and do not have access to the kernel.
      $module_dir = getenv('DRUPAL_PROJECT_FOLDER');
      if ($module_dir === FALSE) {
        throw new \Exception('Cannot detect the modules directory.');
      }
      $parts = explode(\DIRECTORY_SEPARATOR, $module_dir);
      $modules_index = \array_search('modules', $parts);
    }
    \assert($modules_index !== FALSE);
    // This should now be 'modules/custom/canvas',
    // 'modules/canvas' or 'modules/contrib/canvas'
    // depending on what folder this file is in.
    $path = '/' . \ltrim(\implode(\DIRECTORY_SEPARATOR, \array_slice($parts, $modules_index)), '/');
    return $path;
  }

}
