<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * For versioned config entities with versionable subset of plugin configuration.
 *
 * @see \Drupal\canvas\Entity\VersionedConfigEntityBase::preSave()
 * @internal
 */
final class VersionedConfigurationSubsetSingleLazyPluginCollection extends DefaultSingleLazyPluginCollection {

  /**
   * Constructs a new VersionedConfigurationSubsetSingleLazyPluginCollection object.
   *
   * @param string[] $omittedKeys
   *   The keys of the key-value pairs in $configuration that should be omitted
   *   from the (versioned) settings of the containing versioned config entity.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The manager to be used for instantiating plugins.
   * @param string $instance_id
   *   The ID of the plugin instance.
   * @param array $configuration
   *   An array of configuration.
   */
  public function __construct(
    public readonly array $omittedKeys,
    PluginManagerInterface $manager,
    $instance_id,
    array $configuration,
  ) {
    parent::__construct($manager, $instance_id, $configuration);
  }

}
