<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * @phpstan-type ConfigDependenciesArray array{content?: array<int, string>, config?: array<int, string>, module?: array<int, string>}
 */
interface VersionedConfigEntityInterface extends ConfigEntityInterface {

  public const string ACTIVE_VERSION = 'active';

  public function getActiveVersion(): string;

  public function getLoadedVersion(): string;

  public function isLoadedVersionActiveVersion(): bool;

  public function loadVersion(string $version): static;

  public function createVersion(string $version): static;

  public function deleteVersion(string $version): static;

  public function deleteVersionIfExists(string $version): static;

  public function resetToActiveVersion(): static;

  /**
   * @return array<string>
   */
  public function getVersions(): array;

  /**
   * Gets the version-specific configuration dependencies.
   *
   * @param string $version
   *
   * @return ConfigDependenciesArray
   *   An array of dependencies, keyed by $type.
   *
   * @see ::getDependencies()
   * @see \Drupal\Core\Config\Entity\ConfigDependencyManager
   */
  public function getVersionSpecificDependencies(string $version): array;

}
