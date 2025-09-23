<?php

declare(strict_types=1);

namespace Drupal\canvas\Config\Entity;

use Drupal\Core\Config\Entity\ConfigDependencyManager;

/**
 * @internal
 * @todo Add the getAllDependencies() method to core's ConfigDependencyManager in https://www.drupal.org/project/drupal/issues/2724835, then remove this trait.
 * @see \Drupal\Tests\canvas\Traits\BetterConfigDependencyManagerTrait
 */
final class BetterConfigDependencyManager extends ConfigDependencyManager {

  /**
   * Gets the configuration entity's complete dependencies of the supplied type.
   *
   * Complete dependencies: both direct and indirect.
   *
   * @param string $type
   *   The type of dependency to return. Either 'module', 'theme', 'config' or
   *   'content'.
   * @param string $config_name
   *   A configuration object name.
   *
   * @return string[]
   *   The list of dependencies of the supplied type.
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityDependency::getDependencies()
   */
  public function getAllDependencies(string $type, string $config_name): array {
    $direct_dependencies = $this->data[$config_name]->getDependencies($type);
    $indirect_dependencies = [];
    foreach ($this->data[$config_name]->getDependencies('config') as $dependency_config_name) {
      $indirect_dependencies = [
        ...$indirect_dependencies,
        ...$this->getAllDependencies($type, $dependency_config_name),
      ];
    }
    return array_values(array_unique([...$direct_dependencies, ...$indirect_dependencies]));
  }

}
