<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\canvas\Config\Entity\BetterConfigDependencyManager;

/**
 * This exists because existing Configuration System infrastructure falls short.
 *
 * - \Drupal\Core\Config\Entity\ConfigEntityDependency::getDependencies() only
 *   returns direct dependencies of a config entity.
 * - \Drupal\Core\Config\Entity\ConfigDependencyManager only has a method to
 *   recursively retrieve *dependent* config entities, not *dependencies*
 *
 * @todo Add the getAllDependencies() method to core's ConfigDependencyManager in https://www.drupal.org/project/drupal/issues/2724835, then remove this trait.
 *
 * @see \Drupal\Core\Config\Entity\ConfigEntityDependency::getDependencies()
 * @see \Drupal\Core\Config\Entity\ConfigDependencyManager::getDependentEntities()
 *
 * @phpstan-import-type ConfigDependenciesArray from \Drupal\canvas\Entity\VersionedConfigEntityInterface
 *
 * @internal
 */
trait BetterConfigDependencyManagerTrait {

  /**
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $config_entity
   *
   * @return ConfigDependenciesArray
   */
  protected function getAllDependencies(ConfigEntityInterface $config_entity) : array {
    $dep_manager = $this->getBetterConfigDependencyManager();
    $config_name = $config_entity->getConfigDependencyName();
    return array_filter([
      'config' => $dep_manager->getAllDependencies('config', $config_name),
      'module' => $dep_manager->getAllDependencies('module', $config_name),
      'theme' => $dep_manager->getAllDependencies('theme', $config_name),
      'content' => $dep_manager->getAllDependencies('content', $config_name),
    ]);
  }

  private function getBetterConfigDependencyManager(): BetterConfigDependencyManager {
    $active_storage = $this->container->get('config.storage');

    // @see \Drupal\Core\Config\ConfigManager::getConfigDependencyManager()
    $config_entity_data = array_filter(array_map(function (array $data) {
      // Only config entities have UUIDs.
      if (isset($data['uuid'])) {
        return $data;
      }
      return FALSE;
    }, $active_storage->readMultiple($active_storage->listAll())));

    $dep_manager = new BetterConfigDependencyManager();
    $dep_manager->setData($config_entity_data);
    return $dep_manager;
  }

}
