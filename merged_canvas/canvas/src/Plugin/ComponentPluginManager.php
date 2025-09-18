<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin;

use Drupal\Component\Plugin\CategorizingPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Theme\Component\ComponentValidator;
use Drupal\Core\Theme\Component\SchemaCompatibilityChecker;
use Drupal\Core\Theme\ComponentNegotiator;
use Drupal\Core\Theme\ComponentPluginManager as CoreComponentPluginManager;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\ComponentIncompatibilityReasonRepository;
use Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent;

/**
 * Decorator that auto-creates/updates an Drupal Canvas Component entity per SDC.
 *
 * @see \Drupal\canvas\Entity\Component
 */
class ComponentPluginManager extends CoreComponentPluginManager implements CategorizingPluginManagerInterface {

  use CategorizingPluginManagerTrait;

  protected static bool $isRecursing = FALSE;

  protected array $reasons;

  public function __construct(
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $themeHandler,
    CacheBackendInterface $cacheBackend,
    ConfigFactoryInterface $configFactory,
    ThemeManagerInterface $themeManager,
    ComponentNegotiator $componentNegotiator,
    FileSystemInterface $fileSystem,
    SchemaCompatibilityChecker $compatibilityChecker,
    ComponentValidator $componentValidator,
    string $appRoot,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ComponentIncompatibilityReasonRepository $reasonRepository,
  ) {
    parent::__construct($module_handler, $themeHandler, $cacheBackend, $configFactory, $themeManager, $componentNegotiator, $fileSystem, $compatibilityChecker, $componentValidator, $appRoot);
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

    // TRICKY: Component::save() calls PropKeysConstraintValidator, which
    // will also call this plugin manager! Avoid recursively creating Component
    // config entities.
    if (self::$isRecursing) {
      return $definitions;
    }
    self::$isRecursing = TRUE;

    $components = $this->entityTypeManager->getStorage('component')->loadMultiple();
    $reasons = $this->reasonRepository->getReasons()[SingleDirectoryComponent::SOURCE_PLUGIN_ID] ?? [];
    $definition_ids = \array_map(static fn (string $plugin_id) => SingleDirectoryComponent::convertMachineNameToId($plugin_id), \array_keys($definitions));
    foreach ($definitions as $machine_name => $plugin_definition) {
      // Update all components, even those that do not meet the requirements.
      // (Because those components may already be in use!)
      $component_id = SingleDirectoryComponent::convertMachineNameToId($machine_name);
      if (array_key_exists($component_id, $components)) {
        $component_plugin = $this->createInstance($machine_name);
        $component = SingleDirectoryComponent::updateConfigEntity($component_plugin);
        if (isset($component_plugin->metadata->status) && $component_plugin->metadata->status === 'obsolete') {
          $reasons[$component_id][] = 'Component has "obsolete" status';
          $component->disable();
        }
        // An existing Component's SDC may be marked `noUi`.
        if (isset($component_plugin->metadata->noUi) && $component_plugin->metadata->noUi === TRUE) {
          $reasons[$component_id][] = 'Component flagged "noUi".';
          $component->disable();
        }
        // The above only works on Drupal core >=11.3.
        // @todo Remove in https://www.drupal.org/i/3537695
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        if ($component_plugin->getPluginDefinition()['noUi'] ?? FALSE) {
          $reasons[$component_id][] = 'Component flagged "noUi".';
          $component->disable();
        }
      }
      else {
        try {
          $component_plugin = $this->createInstance($machine_name);
          // Do not create `Component` config entities for SDCs marked `noUi`.
          if (isset($component_plugin->metadata->noUi) && $component_plugin->metadata->noUi === TRUE) {
            continue;
          }
          // The above only works on Drupal core >=11.3.
          // @todo Remove in https://www.drupal.org/i/3537695
          // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
          if ($component_plugin->getPluginDefinition()['noUi'] ?? FALSE) {
            continue;
          }
          SingleDirectoryComponent::componentMeetsRequirements($component_plugin);
          $component = SingleDirectoryComponent::createConfigEntity($component_plugin);
        }
        catch (ComponentDoesNotMeetRequirementsException $e) {
          $reasons[$component_id] = $e->getMessages();
          continue;
        }
      }
      try {
        $component->save();
      }
      catch (SchemaIncompleteException $exception) {
        if (!str_starts_with($exception->getMessage(), 'Schema errors for canvas.component.sdc.sdc_test_all_props.all-props with the following errors:')) {
          throw $exception;
        }
      }
    }
    $this->reasonRepository->updateReasons(SingleDirectoryComponent::SOURCE_PLUGIN_ID, \array_intersect_key($reasons, \array_flip($definition_ids)));
    self::$isRecursing = FALSE;

    return $definitions;
  }

  /**
   * @todo remove when https://www.drupal.org/project/drupal/issues/3474533 lands
   *
   * @param array $definition
   * @param string $plugin_id
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);
    $this->processDefinitionCategory($definition);
  }

  /**
   * @todo remove when https://www.drupal.org/project/drupal/issues/3474533 lands
   *
   * @param array $definition
   */
  protected function processDefinitionCategory(&$definition): void {
    $definition['category'] = $definition['group'] ?? $this->t('Other');
  }

}
