<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin;

use Drupal\Core\Block\BlockManager as CoreBlockManager;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Block\MainContentBlockPluginInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\ComponentIncompatibilityReasonRepository;
use Drupal\canvas\ComponentSource\ComponentSourceManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\VersionedConfigEntityBase;
use Drupal\canvas\Plugin\Canvas\ComponentSource\BlockComponent;
use Psr\Log\LoggerInterface;

/**
 * Decorator that auto-creates/updates an Drupal Canvas Component entity per Block plugin.
 *
 * @see \Drupal\canvas\Entity\Component
 * @see docs/components.md#3.2
 */
final class BlockManager extends CoreBlockManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    LoggerInterface $logger,
    protected readonly TypedConfigManagerInterface $configTyped,
    private readonly ComponentIncompatibilityReasonRepository $reasonRepository,
    private readonly ComponentSourceManager $componentSourceManager,
  ) {
    parent::__construct($namespaces, $cache_backend, $module_handler, $logger);
  }

  /**
   * {@inheritdoc}
   */
  protected function setCachedDefinitions($definitions): array {
    parent::setCachedDefinitions($definitions);

    // Do not auto-create/update Canvas configuration when syncing config/deploying.
    // @todo Introduce a "Canvas development mode" similar to Twig's: https://www.drupal.org/node/3359728
    // @phpstan-ignore-next-line
    if (\Drupal::isConfigSyncing()) {
      return $definitions;
    }

    foreach ($definitions as $id => $definition) {
      if ($id === 'broken') {
        continue;
      }
      // The node syndicate block does not qualify anyway, and it has been
      // deprecated: avoid flooding Canvas's tests with this news.
      // @see https://www.drupal.org/node/3519248
      if ($id === 'node_syndicate_block') {
        continue;
      }

      // @todo is this a not going to become performance bottle neck on BlockPlugin heavy sites?
      $block = $this->createInstance($id);
      assert($block instanceof BlockPluginInterface);
      // The main content is rendered in a fixed position.
      // @see \Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant::build()
      if ($block instanceof MainContentBlockPluginInterface) {
        continue;
      }

      $component_id = BlockComponent::componentIdFromBlockPluginId($id);
      $component = Component::load($component_id);
      $settings = [
        // We are using strict config schema validation, so we need to provide
        // valid default settings for each block.
        'default_settings' => [
            // The generic block plugin settings: all block plugins have at least
            // this.
            // @see `type: block_settings`
            // @see `type: block.settings.*`
            // @todo Simplify when core simplifies `type: block_settings` in
            //   https://www.drupal.org/i/3426278
          'id' => $id,
          'label' => (string) $definition['admin_label'],
          // @todo Change this to FALSE once https://drupal.org/i/2544708 is
          //   fixed.
          'label_display' => '0',
          'provider' => $definition['provider'],
        ] + $block->defaultConfiguration(),
      ];

      $block_source = $this->componentSourceManager->createInstance(BlockComponent::SOURCE_PLUGIN_ID, [
        'local_source_id' => $id,
        ...$settings,
      ]);
      assert($block_source instanceof BlockComponent);
      $version = $block_source->generateVersionHash();
      if (!$component instanceof Component) {
        $component = Component::create([
          'id' => $component_id,
          'provider' => $definition['provider'],
          'source' => BlockComponent::SOURCE_PLUGIN_ID,
          'status' => TRUE,
          'versioned_properties' => [VersionedConfigEntityBase::ACTIVE_VERSION => ['settings' => $settings]],
          'active_version' => $version,
          'source_local_id' => $id,
        ]);
      }
      else {
        $component->createVersion($version)
          ->deleteVersionIfExists(ComponentInterface::FALLBACK_VERSION);
      }
      $component
        // These 3 can change over time:
        // - label and category (unversioned)
        // - settings (versioned)
        ->set('label', (string) $definition['admin_label'])
        ->set('category', (string) $definition['category'])
        ->setSettings($settings);

      try {
        $component->getComponentSource()->checkRequirements();
        $component->save();
      }
      catch (ComponentDoesNotMeetRequirementsException $e) {
        $this->reasonRepository->storeReasons(BlockComponent::SOURCE_PLUGIN_ID, $component_id, $e->getMessages());

        // Existing component trees may depend on this Component config entity.
        // Avoid breaking those dependencies (which for some config entities
        // would result in their deletion), but disallow creating more instances
        // of this Component, by disabling it.
        // (Existing instances of this component may fail to render, but robust
        // error handling must graciously handle that.)
        // @see \Drupal\canvas\Element\RenderSafeComponentContainer
        if (!$component->isNew()) {
          $component->disable()->save();
        }
      }
    }

    return $definitions;
  }

}
