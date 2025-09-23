<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\canvas\Plugin\Adapter\Adapter;
use Drupal\canvas\Plugin\Adapter\AdapterInterface;

/**
 * @phpstan-import-type JsonSchema from \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType
 */
final class AdapterManager extends DefaultPluginManager {

  /**
   * @param \Traversable<string, string> $namespaces
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Adapter',
      $namespaces,
      $module_handler,
      AdapterInterface::class,
      Adapter::class,
      'Drupal\canvas\Annotation\Adapter'
    );
    $this->alterInfo('canvas_adapter_manager_info');
    $this->setCacheBackend($cache_backend, 'canvas_adapters');
  }

  /**
   * @param JsonSchema $schema
   *
   * @return \Drupal\canvas\Plugin\Adapter\AdapterInterface[]
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDefinitionsByOutputSchema(array $schema): array {
    $adapters = [];

    foreach ($this->getDefinitions() as $id => $adapter) {
      $adapterInstance = $this->createInstance($id);
      if ($adapterInstance instanceof AdapterInterface && $adapterInstance->matchesOutputSchema($schema)) {
        $adapters[] = $adapterInstance;
      }
    }

    return $adapters;
  }

}
