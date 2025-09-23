<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Canvas\ComponentSource;

use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\Component as ComponentPlugin;
use Drupal\Core\Render\Component\Exception\InvalidComponentException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\canvas\Attribute\ComponentSource;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\AutoSaveEntity;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\ComponentMetadataRequirementsChecker;
use Drupal\canvas\ComponentSource\ComponentSourceManager;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\Component as ComponentEntity;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\ComponentSource\UrlRewriteInterface;
use Drupal\canvas\Entity\VersionedConfigEntityBase;
use Drupal\canvas\Render\ImportMapResponseAttachmentsProcessor;
use Drupal\canvas\Version;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a component source based on Canvas JavaScript Component config entities.
 */
#[ComponentSource(
  id: self::SOURCE_PLUGIN_ID,
  label: new TranslatableMarkup('Code Components'),
  supportsImplicitInputs: FALSE,
)]
final class JsComponent extends GeneratedFieldExplicitInputUxComponentSourceBase implements UrlRewriteInterface {

  public const SOURCE_PLUGIN_ID = 'js';

  public const EXAMPLE_VIDEO_HORIZONTAL = '/ui/assets/videos/mountain_wide.mp4';
  public const EXAMPLE_VIDEO_VERTICAL = '/ui/assets/videos/bird_vertical.mp4';

  protected ExtensionPathResolver $extensionPathResolver;
  protected AutoSaveManager $autoSaveManager;
  protected FileUrlGeneratorInterface $fileUrlGenerator;
  protected Version $version;
  protected AssetQueryStringInterface $assetQueryString;
  protected ?JavaScriptComponent $jsComponent = NULL;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->extensionPathResolver = $container->get(ExtensionPathResolver::class);
    $instance->autoSaveManager = $container->get(AutoSaveManager::class);
    $instance->fileUrlGenerator = $container->get(FileUrlGeneratorInterface::class);
    $instance->version = $container->get(Version::class);
    $instance->assetQueryString = $container->get(AssetQueryStringInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getComponentPlugin(): ComponentPlugin {
    if ($this->componentPlugin === NULL) {
      // Statically cache the loaded plugin.
      $this->componentPlugin = self::buildEphemeralSdcPluginInstance($this->getJavaScriptComponent());
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
    // This component source doesn't use plugin classes.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getJavaScriptComponent(): JavaScriptComponent {
    if ($this->jsComponent === NULL) {
      $js_component_storage = $this->entityTypeManager->getStorage('js_component');
      assert($js_component_storage instanceof ConfigEntityStorageInterface);
      $js_component = $js_component_storage->load($this->getSourceSpecificComponentId());
      assert($js_component instanceof JavaScriptComponent);
      $this->jsComponent = $js_component;
    }
    return $this->jsComponent;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    // @todo Add the global asset library in https://www.drupal.org/project/canvas/issues/3499933.
    $dependencies['config'][] = $this->getJavaScriptComponent()->getConfigDependencyName();
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentDescription(): TranslatableMarkup {
    try {
      $js_component = $this->getJavaScriptComponent();
      return new TranslatableMarkup('Code component: %name', [
        '%name' => $js_component->label(),
      ]);
    }
    catch (\Exception) {
      return new TranslatableMarkup('Invalid/broken code component');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function renderComponent(array $inputs, string $componentUuid, bool $isPreview = FALSE): array {
    $component = $this->getJavaScriptComponent();

    $autoSave = $this->autoSaveManager->getAutoSaveEntity($component);
    $component_url = $component->getComponentUrl($this->fileUrlGenerator, $isPreview);

    $build = [];
    $base_path = \base_path();
    $build['#attached']['library'][] = $component->getAssetLibrary($isPreview);

    $canvas_path = $this->extensionPathResolver->getPath('module', 'canvas');
    // Build base import map
    $import_maps[ImportMapResponseAttachmentsProcessor::GLOBAL_IMPORTS] = [
      'preact' => \sprintf('%s%s/ui/lib/astro-hydration/dist/preact.module.js', $base_path, $canvas_path),
      'preact/hooks' => \sprintf('%s%s/ui/lib/astro-hydration/dist/hooks.module.js', $base_path, $canvas_path),
      'react/jsx-runtime' => \sprintf('%s%s/ui/lib/astro-hydration/dist/jsx-runtime-default.js', $base_path, $canvas_path),
      'react' => \sprintf('%s%s/ui/lib/astro-hydration/dist/compat.module.js', $base_path, $canvas_path),
      'react-dom' => \sprintf('%s%s/ui/lib/astro-hydration/dist/compat.module.js', $base_path, $canvas_path),
      'react-dom/client' => \sprintf('%s%s/ui/lib/astro-hydration/dist/compat.module.js', $base_path, $canvas_path),
      'clsx' => \sprintf('%s%s/ui/lib/astro-hydration/dist/clsx.js', $base_path, $canvas_path),
      'class-variance-authority' => \sprintf('%s%s/ui/lib/astro-hydration/dist/class-variance-authority.js', $base_path, $canvas_path),
      'tailwind-merge' => \sprintf('%s%s/ui/lib/astro-hydration/dist/tailwind-merge.js', $base_path, $canvas_path),
      '@/lib/FormattedText' => \sprintf('%s%s/ui/lib/astro-hydration/dist/FormattedText.js', $base_path, $canvas_path),
      'next-image-standalone' => \sprintf('%s%s/ui/lib/astro-hydration/dist/next-image-standalone.js', $base_path, $canvas_path),
      '@/lib/utils' => \sprintf('%s%s/ui/lib/astro-hydration/dist/utils.js', $base_path, $canvas_path),
      '@drupal-api-client/json-api-client' => \sprintf('%s%s/ui/lib/astro-hydration/dist/jsonapi-client.js', $base_path, $canvas_path),
      'drupal-jsonapi-params' => \sprintf('%s%s/ui/lib/astro-hydration/dist/jsonapi-params.js', $base_path, $canvas_path),
      '@/lib/jsonapi-utils' => \sprintf('%s%s/ui/lib/astro-hydration/dist/jsonapi-utils.js', $base_path, $canvas_path),
      '@/lib/drupal-utils' => \sprintf('%s%s/ui/lib/astro-hydration/dist/drupal-utils.js', $base_path, $canvas_path),
      'swr' => \sprintf('%s%s/ui/lib/astro-hydration/dist/swr.js', $base_path, $canvas_path),
    ];
    // We need a cache-busting query string for the browser to not use cached
    // files after installing an update.
    $version = $this->version->getVersion();
    // If version is 0.0.0, use the AssetQueryStringInterface service to improve
    // DX: avoid the need to do a hard refresh or wipe the browser cache.
    $query_string = $version === '0.0.0' ? $this->assetQueryString->get() : $version;
    foreach ($import_maps[ImportMapResponseAttachmentsProcessor::GLOBAL_IMPORTS] as &$asset) {
      $asset .= '?' . $query_string;
    }

    // For scoped dependencies we don't need cache-busting query strings, as
    // those are already busted by its content-dependent filename: when the
    // code component changes, so does the filename.
    // @see \Drupal\canvas\Entity\CanvasAssetLibraryTrait::getJsPath()
    $scoped_map = $this->getScopedDependencies($component, $autoSave, $isPreview);
    if (count($scoped_map) > 0) {
      $import_maps[ImportMapResponseAttachmentsProcessor::SCOPED_IMPORTS] = $scoped_map;
    }

    $build['#attached']['library'] = \array_merge($build['#attached']['library'], $this->getDependencyLibraries($component, $autoSave, $isPreview));

    if (\count($build['#attached']['library']) === 0) {
      unset($build['#attached']['library']);
    }
    // Resource hints.
    $resource_hints = [
      'preact/signals' => \sprintf('%s%s/ui/lib/astro-hydration/dist/signals.module.js', $base_path, $canvas_path),
      '@/lib/preload-helper' => \sprintf('%s%s/ui/lib/astro-hydration/dist/preload-helper.js', $base_path, $canvas_path),
    ];
    foreach ($resource_hints as $url) {
      $build['#attached']['html_head_link'][] = [
        [
          'rel' => 'modulepreload',
          'fetchpriority' => 'high',
          'href' => $url . '?' . $query_string,
        ],
      ];
    }
    if ($isPreview && !$autoSave->isEmpty()) {
      \assert($autoSave->entity instanceof JavaScriptComponent);
      $component = $autoSave->entity;
    }
    if ($isPreview) {
      $build['#cache']['tags'][] = AutoSaveManager::CACHE_TAG;
      // Always attach the draft asset library when loading the preview: avoid
      // race conditions; let the controller handle it for us.
      // @see \Drupal\canvas\Controller\ApiConfigAutoSaveControllers::getCss()
      $build['#attached']['library'][] = 'canvas/asset_library.' . AssetLibrary::GLOBAL_ID . '.draft';
    }
    else {
      $build['#attached']['library'][] = 'canvas/asset_library.' . AssetLibrary::GLOBAL_ID;
    }

    $valid_props = $component->getProps() ?? [];

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($component)
      ->applyTo($build);

    return $build + [
      '#type' => 'astro_island',
      '#uuid' => $componentUuid,
      '#import_maps' => $import_maps,
      '#name' => $component->label(),
      '#component_url' => $component_url,
      '#props' => (\array_intersect_key($inputs[self::EXPLICIT_INPUT_NAME] ?? [], $valid_props)) + [
        'canvas_uuid' => $componentUuid,
        'canvas_slot_ids' => \array_keys($this->getSlotDefinitions()),
        'canvas_is_preview' => $isPreview,
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
   * Returns the source label for this component.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The source label.
   */
  protected function getSourceLabel(): TranslatableMarkup {
    return $this->t('Code component');
  }

  /**
   * Creates the Component config entity for a "code component" config entity.
   *
   * @param \Drupal\canvas\Entity\JavaScriptComponent $js_component
   *   An Canvas "code component" config entity.
   *
   * @return \Drupal\canvas\Entity\ComponentInterface
   *   The component config entity.
   *
   * @throws \Drupal\canvas\ComponentDoesNotMeetRequirementsException
   *    When the component does not meet requirements.
   */
  public static function createConfigEntity(JavaScriptComponent $js_component): ComponentInterface {
    try {
      // Create a new instance and bypass the statically cached componentPlugin
      // property.
      $ephemeral_sdc_component = self::buildEphemeralSdcPluginInstance($js_component);
    }
    catch (InvalidComponentException $e) {
      throw new ComponentDoesNotMeetRequirementsException([$e->getMessage()]);
    }
    ComponentMetadataRequirementsChecker::check((string) $js_component->id(), $ephemeral_sdc_component->metadata, $js_component->getRequiredProps());
    $props = self::getPropsForComponentPlugin($ephemeral_sdc_component);
    $settings = [
      'prop_field_definitions' => $props,
    ];
    $js_source = \Drupal::service(ComponentSourceManager::class)->createInstance(self::SOURCE_PLUGIN_ID, [
      'local_source_id' => (string) $js_component->id(),
      ...$settings,
    ]);
    assert($js_source instanceof self);
    // The JS Component config entity may not be saved yet. Set it on the source
    // plugin so that it doesn't try to load it.
    $js_source->setJavaScriptComponent($js_component);
    $version = $js_source->generateVersionHash();
    return ComponentEntity::create([
      'id' => self::SOURCE_PLUGIN_ID . '.' . $js_component->id(),
      'label' => $js_component->label(),
      // @todo Update in https://www.drupal.org/project/canvas/issues/3541364. This causes a "@todo" Folder to be created.
      'category' => '@todo',
      'provider' => NULL,
      'source' => self::SOURCE_PLUGIN_ID,
      'source_local_id' => $js_component->id(),
      'active_version' => $version,
      'versioned_properties' => [
        VersionedConfigEntityBase::ACTIVE_VERSION => ['settings' => $settings],
      ],
      'status' => $js_component->status(),
    ]);
  }

  /**
   * Updates the Component config entity for a "code component" config entity.
   *
   * @param \Drupal\canvas\Entity\JavaScriptComponent $js_component
   *   An Canvas "code component" config entity.
   *
   * @return \Drupal\canvas\Entity\ComponentInterface
   *   The component config entity.
   *
   * @throws \Drupal\canvas\ComponentDoesNotMeetRequirementsException
   *    When the component does not meet requirements.
   */
  public static function updateConfigEntity(JavaScriptComponent $js_component, ComponentInterface $component): ComponentInterface {
    $label_key = $component->getEntityType()->getKey('label');
    assert(is_string($label_key));
    $component->set($label_key, $js_component->label());
    $component->setStatus($js_component->status());
    try {
      // Create a new instance and bypass the statically cached componentPlugin
      // property.
      $ephemeral_sdc_component = self::buildEphemeralSdcPluginInstance($js_component);
    }
    catch (InvalidComponentException $e) {
      throw new ComponentDoesNotMeetRequirementsException([$e->getMessage()]);
    }
    ComponentMetadataRequirementsChecker::check((string) $js_component->id(), $ephemeral_sdc_component->metadata, $js_component->getRequiredProps());
    $settings = [
      'prop_field_definitions' => self::getPropsForComponentPlugin($ephemeral_sdc_component),
    ];
    $js_source = \Drupal::service(ComponentSourceManager::class)->createInstance(self::SOURCE_PLUGIN_ID, [
      'local_source_id' => (string) $js_component->id(),
      ...$settings,
    ]);
    assert($js_source instanceof self);
    $version = $js_source->generateVersionHash();
    $component
      ->createVersion($version)
      ->deleteVersionIfExists(ComponentInterface::FALLBACK_VERSION)
      ->setSettings($settings);
    return $component;
  }

  /**
   * Generate a component ID given a Javascript Component ID.
   *
   * @param string $javaScriptComponentId
   *   Component ID.
   *
   * @return string
   *   Generated component ID.
   */
  public static function componentIdFromJavascriptComponentId(string $javaScriptComponentId): string {
    return \sprintf('%s.%s', self::SOURCE_PLUGIN_ID, $javaScriptComponentId);
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): void {
    $js_component = $this->getJavaScriptComponent();
    try {
      // Create a new instance and bypass the statically cached componentPlugin
      // property.
      $ephemeral_sdc_component = self::buildEphemeralSdcPluginInstance($js_component);
    }
    catch (InvalidComponentException $e) {
      throw new ComponentDoesNotMeetRequirementsException([$e->getMessage()]);
    }
    ComponentMetadataRequirementsChecker::check((string) $js_component->id(), $ephemeral_sdc_component->metadata, $js_component->getRequiredProps());
  }

  /**
   * Any valid JavaScript Component config entity can be mapped to SDC metadata.
   *
   * Bypasses the statically cached componentPlugin property. Should be called
   * during config entity creation and updating to ensure a fresh version is
   * generated. For run-time code, use ::getComponentPlugin instead.
   *
   * @see \Drupal\canvas\Plugin\Validation\Constraint\JsComponentHasValidAndSupportedSdcMetadataConstraintValidator::validate
   */
  private static function buildEphemeralSdcPluginInstance(JavaScriptComponent $component): ComponentPlugin {
    $definition = $component->toSdcDefinition();
    return new ComponentPlugin(
      [
        'app_root' => '',
        'enforce_schemas' => TRUE,
      ],
      $definition['id'],
      $definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function rewriteExampleUrl(string $url): string {
    // Allow any fully qualified URL.
    $parsed_url = parse_url($url);
    \assert(\is_array($parsed_url));
    if (array_intersect_key($parsed_url, array_flip(['scheme', 'host']))) {
      return $url;
    }

    // Allow the example URL to be one of the hardcoded relative URLs, and
    // rewrite them to operational root-relative URLs.
    // Only allow precise matches for both DX and security reasons.
    $example_videos = [
      self::EXAMPLE_VIDEO_HORIZONTAL,
      self::EXAMPLE_VIDEO_VERTICAL,
    ];
    if (in_array($url, $example_videos, TRUE)) {
      $file_path = $this->extensionPathResolver->getPath('module', 'canvas') . $url;
      return Url::fromUri('base:/' . $file_path)->toString();
    }

    throw new \InvalidArgumentException('Default images for Javascript Components must be a fully-qualified URL with both scheme and host.');
  }

  private function getScopedDependencies(JavaScriptComponent $component, AutoSaveEntity $autoSave, bool $isPreview, array $seen = []): array {
    $scoped_dependencies = [];
    $component_url = $component->getComponentUrl($this->fileUrlGenerator, $isPreview);
    foreach ($component->getComponentDependencies($autoSave, $isPreview) as $js_component_dependency_name => $js_component_dependency) {
      if (\in_array($js_component_dependency_name, $seen, TRUE)) {
        // Recursion or already processed by another dependency.
        continue;
      }
      $seen[] = $js_component_dependency_name;
      assert($js_component_dependency instanceof JavaScriptComponent);
      $dependencyAutoSave = $this->autoSaveManager->getAutoSaveEntity($js_component_dependency);
      $dependency_component_url = $js_component_dependency->getComponentUrl($this->fileUrlGenerator, $isPreview);
      $scoped_dependencies[$component_url]["@/components/{$js_component_dependency_name}"] = $js_component_dependency->getComponentUrl($this->fileUrlGenerator, $isPreview);
      $scoped_dependencies = array_merge($scoped_dependencies, $this->getScopedDependencies($js_component_dependency, $dependencyAutoSave, $isPreview, $seen));
      if (isset($scoped_dependencies[$dependency_component_url])) {
        // The dependencies of my dependencies are also my dependencies, so says the logic.
        $scoped_dependencies[$component_url] = array_merge($scoped_dependencies[$component_url], $scoped_dependencies[$dependency_component_url]);
      }
    }
    return $scoped_dependencies;
  }

  private function getDependencyLibraries(JavaScriptComponent $component, AutoSaveEntity $autoSave, bool $isPreview, array $seen = []): array {
    $libraries = [];
    foreach ($component->getComponentDependencies($autoSave, $isPreview) as $js_component_dependency_name => $js_component_dependency) {
      if (\in_array($js_component_dependency_name, $seen, TRUE)) {
        // Recursion or already processed by another dependency.
        continue;
      }
      $seen[] = $js_component_dependency_name;
      assert($js_component_dependency instanceof JavaScriptComponent);
      $dependencyAutoSave = $this->autoSaveManager->getAutoSaveEntity($js_component_dependency);
      $libraries[] = $js_component_dependency->getAssetLibrary($isPreview);
      $libraries = array_merge($libraries, $this->getDependencyLibraries($js_component_dependency, $dependencyAutoSave, $isPreview, $seen));
    }
    return $libraries;
  }

  public function setJavaScriptComponent(?JavaScriptComponent $jsComponent): static {
    $this->jsComponent = $jsComponent;
    return $this;
  }

}
