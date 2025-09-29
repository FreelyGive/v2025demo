<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Canvas\ComponentSource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Plugin\Component as ComponentPlugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Component\ComponentValidator;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Theme\ExtensionType;
use Drupal\canvas\Attribute\ComponentSource;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\ComponentMetadataRequirementsChecker;
use Drupal\canvas\ComponentSource\ComponentSourceManager;
use Drupal\canvas\ComponentSource\UrlRewriteInterface;
use Drupal\canvas\Entity\Component as ComponentEntity;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\VersionedConfigEntityBase;
use Drupal\canvas\ShapeMatcher\FieldForComponentSuggester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Path;

/**
 * Defines a component source based on single-directory components.
 */
#[ComponentSource(
  id: self::SOURCE_PLUGIN_ID,
  label: new TranslatableMarkup('Single-Directory Components'),
  supportsImplicitInputs: FALSE,
)]
final class SingleDirectoryComponent extends GeneratedFieldExplicitInputUxComponentSourceBase implements UrlRewriteInterface {

  public const SOURCE_PLUGIN_ID = 'sdc';

  /**
   * Constructs a new SingleDirectoryComponent.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Theme\Component\ComponentValidator $componentValidator
   *   Component validator.
   * @param \Drupal\Core\Field\WidgetPluginManager $fieldWidgetPluginManager
   *   Field widget plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Theme\ComponentPluginManager $componentPluginManager
   *   Component manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler
   *   Theme handler.
   * @param \Drupal\canvas\ShapeMatcher\FieldForComponentSuggester $fieldForComponentSuggester
   *   Field for component suggester.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    ComponentValidator $componentValidator,
    WidgetPluginManager $fieldWidgetPluginManager,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly ComponentPluginManager $componentPluginManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ThemeHandlerInterface $themeHandler,
    // @phpstan-ignore-next-line property.onlyWritten
    private readonly FieldForComponentSuggester $fieldForComponentSuggester,
  ) {
    assert(array_key_exists('local_source_id', $configuration));
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $componentValidator,
      $fieldWidgetPluginManager,
      $entityTypeManager,
      $fieldForComponentSuggester,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(ComponentValidator::class),
      $container->get('plugin.manager.field.widget'),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(ComponentPluginManager::class),
      $container->get(ModuleHandlerInterface::class),
      $container->get(ThemeHandlerInterface::class),
      $container->get('Drupal\canvas\ShapeMatcher\FieldForComponentSuggester'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getComponentPlugin(): ComponentPlugin {
    // @todo this should probably use DefaultSingleLazyPluginCollection
    if ($this->componentPlugin === NULL) {
      // Statically cache the loaded plugin.
      $this->componentPlugin = $this->componentPluginManager->find($this->getSourceSpecificComponentId());
    }
    return $this->componentPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'local_source_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedPluginClass(): ?string {
    return $this->componentPluginManager->getDefinition($this->getSourceSpecificComponentId())['class'];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    $component = $this->getComponentPlugin();
    $provider = $component->getBaseId();
    if ($this->moduleHandler->moduleExists($provider)) {
      $dependencies['module'][] = $provider;
    }
    if ($this->themeHandler->themeExists($provider)) {
      $dependencies['theme'][] = $provider;
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentDescription(): TranslatableMarkup {
    try {
      $component = $this->getComponentPlugin();
      return new TranslatableMarkup('Single-directory component: %name', [
        '%name' => $this->getMetadata()->name ?? $component->getPluginId(),
      ]);
    }
    catch (\Exception) {
      return new TranslatableMarkup('Invalid/broken Single-directory component');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function renderComponent(array $inputs, string $componentUuid, bool $isPreview = FALSE): array {
    return [
      '#type' => 'component',
      '#component' => $this->getSourceSpecificComponentId(),
      '#props' => ($inputs[self::EXPLICIT_INPUT_NAME] ?? []) + [
        'canvas_uuid' => $componentUuid,
        'canvas_slot_ids' => \array_keys($this->getSlotDefinitions()),
        'canvas_is_preview' => $isPreview,
      ],
      '#attached' => [
        'library' => [
          'core/components.' . str_replace(':', '--', $this->getSourceSpecificComponentId()),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setSlots(array &$build, array $slots): void {
    $build['#slots'] = $slots;
  }

  /**
   * Converts an SDC plugin machine name into a config entity ID.
   *
   * The naming convention for SDC plugin components is [module/theme]:[component machine name]. Colon is invalid config entity name, so we replace it with '.'.
   *
   * @param string $machine_name
   *   The SDC plugin.
   *
   * @return string
   *   The config entity ID.
   *
   * @see \Drupal\Core\Plugin\Component::$machineName
   * @see https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/api-for-single-directory-components
   */
  public static function convertMachineNameToId(string $machine_name): string {
    assert(str_contains($machine_name, ':'));
    return 'sdc.' . str_replace(':', '.', $machine_name);
  }

  /**
   * Create a Component config entity for a Single Directory Component plugin.
   *
   * @param \Drupal\Core\Plugin\Component $component_plugin
   *   The SDC plugin.
   *
   * @return \Drupal\canvas\Entity\Component
   *   The component config entity.
   */
  public static function createConfigEntity(ComponentPlugin $component_plugin): ComponentEntity {
    assert(is_array($component_plugin->metadata->schema));
    $props = self::getPropsForComponentPlugin($component_plugin);
    assert(is_array($component_plugin->getPluginDefinition()));
    // Disabled if obsolete or flagged with noUi.
    $status = !(
      (isset($component_plugin->metadata->noUi) && $component_plugin->metadata->noUi === TRUE)
      // The above only works on Drupal core >=11.3.
      // @todo Remove in https://www.drupal.org/i/3537695
      || ($component_plugin->getPluginDefinition()['noUi'] ?? FALSE)
      || (isset($component_plugin->metadata->status) && $component_plugin->metadata->status === 'obsolete')
    );

    $settings = [
      'prop_field_definitions' => $props,
    ];
    $sdc_source = \Drupal::service(ComponentSourceManager::class)->createInstance(self::SOURCE_PLUGIN_ID, [
      'local_source_id' => $component_plugin->getPluginId(),
      ...$settings,
    ]);
    assert($sdc_source instanceof self);
    $version = $sdc_source->generateVersionHash();
    return ComponentEntity::create([
      'id' => self::convertMachineNameToId($component_plugin->getPluginId()),
      'label' => $component_plugin->getPluginDefinition()['name'] ?? $component_plugin->getPluginId(),
      'category' => $component_plugin->getPluginDefinition()['category'],
      'source' => self::SOURCE_PLUGIN_ID,
      'provider' => $component_plugin->getPluginDefinition()['provider'],
      'source_local_id' => $component_plugin->getPluginId(),
      'active_version' => $version,
      'versioned_properties' => [
        VersionedConfigEntityBase::ACTIVE_VERSION => ['settings' => $settings],
      ],
      'status' => $status,
    ]);
  }

  /**
   * Update the Component config entity for a Single Directory Component plugin.
   *
   * @param \Drupal\Core\Plugin\Component $component_plugin
   *   The SDC plugin.
   *
   * @return \Drupal\canvas\Entity\Component
   *   The component config entity.
   */
  public static function updateConfigEntity(ComponentPlugin $component_plugin): ComponentEntity {
    $component = ComponentEntity::load(self::convertMachineNameToId($component_plugin->getPluginId()));
    assert($component instanceof ComponentEntity);
    assert(is_array($component_plugin->metadata->schema));

    $settings = [
      'prop_field_definitions' => self::getPropsForComponentPlugin($component_plugin),
    ];
    $sdc_source = \Drupal::service(ComponentSourceManager::class)->createInstance(self::SOURCE_PLUGIN_ID, [
      'local_source_id' => $component_plugin->getPluginId(),
      ...$settings,
    ]);
    assert($sdc_source instanceof self);
    $version = $sdc_source->generateVersionHash();
    $definition = $component_plugin->getPluginDefinition();
    \assert(\is_array($definition));
    $component
      // These 3 can change over time:
      // - label and category (unversioned)
      // - settings (versioned)
      ->set('label', $definition['name'] ?? $component_plugin->getPluginId())
      ->set('category', $definition['category'])
      ->createVersion($version)
      ->deleteVersionIfExists(ComponentInterface::FALLBACK_VERSION)
      ->setSettings($settings);
    return $component;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSourceLabel(): TranslatableMarkup {
    $component_plugin = $this->getComponentPlugin();
    assert(is_array($component_plugin->getPluginDefinition()));

    // The 'extension_type' key is guaranteed to be set.
    // @see \Drupal\Core\Theme\ComponentPluginManager::alterDefinition()
    $extension_type = $component_plugin->getPluginDefinition()['extension_type'];
    assert($extension_type instanceof ExtensionType);
    return match ($extension_type) {
      ExtensionType::Module => $this->t('Module component'),
      ExtensionType::Theme => $this->t('Theme component'),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): void {
    self::componentMeetsRequirements($this->getComponentPlugin());
  }

  public static function componentMeetsRequirements(ComponentPlugin $component_plugin): void {
    $definition = $component_plugin->getPluginDefinition();
    \assert(\is_array($definition));

    if (isset($definition['status']) && $definition['status'] === 'obsolete') {
      throw new ComponentDoesNotMeetRequirementsException(['Component has "obsolete" status']);
    }

    // Special case exception for 'all-props' SDC.
    // (This is used to develop support for more prop shapes.)
    if ($definition['id'] === 'sdc_test_all_props:all-props') {
      return;
    }

    $required = $definition['props']['required'] ?? [];
    ComponentMetadataRequirementsChecker::check($definition['id'], $component_plugin->metadata, $required);
  }

  /**
   * {@inheritdoc}
   */
  public function rewriteExampleUrl(string $url): string {
    $parsed_url = parse_url($url);
    \assert(\is_array($parsed_url));
    if (array_intersect_key($parsed_url, array_flip(['scheme', 'host']))) {
      return $url;
    }

    \assert(isset($parsed_url['path']));
    $path = ltrim($parsed_url['path'], '/');
    $template_path = $this->getComponentPlugin()->getTemplatePath();
    \assert(\is_string($template_path));
    $referenced_asset_path = Path::canonicalize(dirname($template_path) . '/' . $path);
    if (is_file($referenced_asset_path)) {
      // SDC example values pointing to assets included in the SDC.
      // For example, an "avatar" SDC that shows an image, and:
      // - the example value is `avatar.png`
      // - the SDC contains a file called `avatar.png`
      // - this returns `/path/to/drupal/path/to/sdc/avatar.png`, resulting in a
      //   working preview.
      return \base_path() . $referenced_asset_path;
    }

    // SDC example values pointing to sample locations, not actual assets.
    // For example, a "call to action" SDC that points to a destination, and:
    // - the example value is `adopt-a-llama`
    // - this returns `/path/to/drupal/adopt-a-llama`, resulting in a
    //   reasonable preview, even though there is unlikely to be a page on the
    //   site with the `adapt-a-llama` path.
    return \base_path() . $path;
  }

}
