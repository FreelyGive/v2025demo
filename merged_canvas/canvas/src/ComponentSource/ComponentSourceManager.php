<?php

declare(strict_types=1);

namespace Drupal\canvas\ComponentSource;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\canvas\Attribute\ComponentSource;

/**
 * Defines a plugin manager for component source plugins.
 *
 * @see \Drupal\canvas\Attribute\ComponentSource
 * @see \Drupal\canvas\ComponentSource\ComponentSourceInterface
 * @see \Drupal\canvas\ComponentSource\ComponentSourceBase
 */
final class ComponentSourceManager extends DefaultPluginManager {

  /**
   * @param \Traversable<string, string> $namespaces
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Canvas/ComponentSource',
      $namespaces,
      $module_handler,
      ComponentSourceInterface::class,
      ComponentSource::class
    );
    $this->alterInfo('canvas_component_source');
    $this->setCacheBackend($cache_backend, 'canvas_component_source');
  }

}
