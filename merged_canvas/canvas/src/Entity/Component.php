<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\ClientSideRepresentation;
use Drupal\canvas\ComponentSource\ComponentSourceInterface;
use Drupal\canvas\ComponentSource\ComponentSourceManager;
use Drupal\canvas\Element\RenderSafeComponentContainer;
use Drupal\canvas\EntityHandlers\ContentCreatorVisibleCanvasConfigEntityAccessControlHandler;
use Drupal\canvas\Form\ComponentListBuilder;
use Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface;
use Drupal\canvas\Plugin\Canvas\ComponentSource\Fallback;
use Drupal\canvas\Plugin\VersionedConfigurationSubsetSingleLazyPluginCollection;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A config entity that exposes a component to the Drupal Canvas UI.
 *
 * Each component provided by a ComponentSource plugin that meets that source's
 * requirements gets a corresponding (enabled) Component config entity. Every
 * enabled Component config entity is available to Site Builders and Content
 * Creators to be placed in Canvas component trees.
 *
 * @see docs/components.md
 * @see \Drupal\canvas\ComponentSource\ComponentSourceInterface
 *
 * @phpstan-type ComponentConfigEntityId string
 */
#[ConfigEntityType(
  id: self::ENTITY_TYPE_ID,
  label: new TranslatableMarkup('Component'),
  label_singular: new TranslatableMarkup('component'),
  label_plural: new TranslatableMarkup('components'),
  label_collection: new TranslatableMarkup('Components'),
  admin_permission: self::ADMIN_PERMISSION,
  handlers: [
    'access' => ContentCreatorVisibleCanvasConfigEntityAccessControlHandler::class,
    'list_builder' => ComponentListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'status',
  ],
  links: [
    'collection' => '/admin/appearance/component',
    'enable' => '/admin/appearance/component/{id}/enable',
    'disable' => '/admin/appearance/component/{id}/disable',
    'audit' => '/admin/appearance/component/{id}/audit',
  ],
  config_export: [
    'label',
    'id',
    'source',
    'source_local_id',
    'provider',
    'category',
    'active_version',
    'versioned_properties',
  ],
  constraints: [
    'ImmutableProperties' => ['id', 'source', 'source_local_id'],
  ],
)]
final class Component extends VersionedConfigEntityBase implements ComponentInterface, CanvasHttpApiEligibleConfigEntityInterface, FolderItemInterface {

  public const string ADMIN_PERMISSION = 'administer components';

  public const string ENTITY_TYPE_ID = 'component';

  /**
   * The component entity ID.
   */
  protected string $id;

  /**
   * The human-readable label of the component.
   */
  protected ?string $label;

  /**
   * The source plugin ID.
   */
  protected string $source;

  /**
   * The ID identifying the component within a source.
   */
  protected string $source_local_id;

  /**
   * The provider of this component: a valid module or theme name, or NULL.
   *
   * NULL must be used to signal it's not provided by an extension. This is used
   * for "code components" for example — which are provided by entities.
   *
   * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent
   */
  protected ?string $provider;

  /**
   * The human-readable category of the component.
   */
  protected string|TranslatableMarkup|null $category;

  /**
   * Holds the plugin collection for the source plugin.
   */
  protected ?VersionedConfigurationSubsetSingleLazyPluginCollection $sourcePluginCollection = NULL;

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategory(): string|TranslatableMarkup {
    // TRICKY: this PHP class allows this value to be `NULL` to avoid
    // \Drupal\Core\Config\Entity\ConfigEntityBase::set() triggering a PHP Type
    // error. Fortunately, all Canvas config entities have strict config schema
    // validation. Thanks to validation, NULL is absent from the return type.
    assert($this->category !== NULL);
    return $this->category;
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentSource(): ComponentSourceInterface {
    return $this->sourcePluginCollection()->get($this->getComponentSourcePluginId());
  }

  /**
   * Determines the Component Source plugin ID for the active version.
   *
   * The special `fallback` version automatically causes the `fallback`
   * Component Source plugin to be used.
   *
   * Note: if a reintroduced component no longer has the same schema/shape for
   * its explicit input, a meaningful error message will inform the user that
   * the stored explicit input is not valid explicit input.
   *
   * @see \Drupal\canvas\Entity\Component::onDependencyRemoval()
   * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\Fallback
   * @see \Drupal\canvas\Element\RenderSafeComponentContainer
   */
  private function getComponentSourcePluginId(): string {
    return $this->active_version === ComponentInterface::FALLBACK_VERSION
      ? ComponentInterface::FALLBACK_VERSION
      : $this->source;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Config\Schema\SchemaIncompleteException
   *
   * @phpstan-ignore-next-line throws.unusedType
   */
  public function save() {
    return parent::save();
  }

  /**
   * Gets the unique (plugin) interfaces for passed Component config entity IDs.
   *
   * @param array<ComponentConfigEntityId> $ids
   *   A list of (unique) Component config entity IDs.
   *
   * @return string[]
   *   The corresponding list of PHP FQCNs. Depending on the component type,
   *   this may be one unique class per Component config entity (ID), or the
   *   same class for all.
   *   For example: all SDC-sourced Canvas Components use the same (plugin) class
   *   (and even interface) interface, but every Block plugin-sourced Canvas
   *   Components has a unique (plugin) class, and often even a unique (plugin)
   *   interface.
   *   @see \Drupal\Core\Theme\ComponentPluginManager::$defaults
   */
  public static function getClasses(array $ids): array {
    return array_values(array_unique(array_filter(array_map(
      static fn (Component $component): ?string => $component->getComponentSource()->getReferencedPluginClass(),
      Component::loadMultiple($ids)
    ))));
  }

  /**
   * Returns the source plugin collection.
   */
  private function sourcePluginCollection(): VersionedConfigurationSubsetSingleLazyPluginCollection {
    if (is_null($this->sourcePluginCollection)) {
      $source_plugin_id = $this->getComponentSourcePluginId();
      $source_plugin_configuration = match ($source_plugin_id) {
        ComponentInterface::FALLBACK_VERSION => [
          // Use the slot definitions from the fallback metadata from the last
          // active version when the Fallback ComponentSource plugin was
          // activated, to fall back to the version-specific slots, without
          // duplicating them into the Fallback component source-specific
          // settings.
          // TRICKY: race condition: when creating the fallback version, the
          // `last_active_version` setting won't exist yet.
          // @see ::setSettings()
          'slots' => array_key_exists('last_active_version', $this->getSettings())
            ? $this->versioned_properties[$this->getSettings()['last_active_version']]['fallback_metadata']['slot_definitions']
            : [],
          ...$this->getSettings(),
        ],
        default => [
          // The immutable plugin ID which is not part of the component source-
          // specific settings.
          'local_source_id' => $this->source_local_id,
          // The mutable plugin settings.
          ...$this->getSettings(),
        ],
      };
      $plugin_key_to_not_write_to_config = match ($source_plugin_id) {
        ComponentInterface::FALLBACK_VERSION => 'slots',
        default => 'local_source_id',
      };
      $this->sourcePluginCollection = new VersionedConfigurationSubsetSingleLazyPluginCollection(
        [$plugin_key_to_not_write_to_config],
        \Drupal::service(ComponentSourceManager::class),
        $source_plugin_id,
        $source_plugin_configuration
      );
    }
    return $this->sourcePluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return [
      'settings' => $this->sourcePluginCollection(),
    ];
  }

  /**
   * Works around the `ExtensionExists` constraint requiring a fixed type.
   *
   * @see \Drupal\Core\Extension\Plugin\Validation\Constraint\ExtensionExistsConstraintValidator
   * @see https://www.drupal.org/node/3353397
   */
  public static function providerExists(?string $provider): bool {
    if (is_null($provider)) {
      return TRUE;
    }
    $container = \Drupal::getContainer();
    return $container->get(ModuleHandlerInterface::class)->moduleExists($provider) || $container->get(ThemeHandlerInterface::class)->themeExists($provider);
  }

  /**
   * {@inheritdoc}
   *
   * Override the parent to enforce the string return type.
   *
   * @see \Drupal\Core\Entity\EntityStorageBase::create
   */
  public function uuid(): string {
    /** @var string */
    return parent::uuid();
  }

  /**
   * {@inheritdoc}
   *
   * This corresponds to `Component` in openapi.yml.
   *
   * @see ui/src/types/Component.ts
   * @see docs/adr/0005-Keep-the-front-end-simple.md
   */
  public function normalizeForClientSide(): ClientSideRepresentation {
    $info = $this->getComponentSource()->getClientSideInfo($this);

    $build = $info['build'];
    unset($info['build']);

    $component_config_entity_uuid = $this->uuid();
    // Wrap in a render-safe container.
    // @todo Remove all the wrapping-in-RenderSafeComponentContainer complexity and make ComponentSourceInterface::renderComponent() for that instead in https://www.drupal.org/i/3521041
    $build = [
      '#type' => RenderSafeComponentContainer::PLUGIN_ID,
      '#component' => $build + [
        // Wrap each rendered component instance in HTML comments that allow the
        // client side to identify it.
        // @see \Drupal\canvas\Plugin\DataType\ComponentTreeHydrated::renderify()
        '#prefix' => Markup::create("<!-- canvas-start-$component_config_entity_uuid -->"),
        '#suffix' => Markup::create("<!-- canvas-end-$component_config_entity_uuid -->"),
      ],
      '#component_context' => \sprintf('Preview rendering component %s.', $this->label()),
      '#component_uuid' => $component_config_entity_uuid,
      '#is_preview' => TRUE,
    ];
    return ClientSideRepresentation::create(
      values: $info + [
        'id' => $this->id(),
        'name' => (string) $this->label(),
        'library' => $this->computeUiLibrary()->value,
        'category' => (string) $this->getCategory(),
        'source' => (string) $this->getComponentSource()->getPluginDefinition()['label'],
        'version' => $this->getActiveVersion(),
      ],
      preview: $build,
    )->addCacheableDependency($this);
  }

  /**
   * Uses heuristics to compute the appropriate "library" in the Canvas UI.
   *
   * Each Component appears in a well-defined "library" in the Canvas UI. This is a
   * set of heuristics with a particular decision tree.
   *
   * @see https://www.drupal.org/project/canvas/issues/3498419#comment-15997505
   */
  private function computeUiLibrary(): LibraryEnum {
    $config = \Drupal::configFactory()->loadMultiple(['core.extension', 'system.theme']);
    $installed_modules = [
      'core',
      ...array_keys($config['core.extension']->get('module')),
    ];
    // @see \Drupal\Core\Extension\ThemeHandler::getDefault()
    $default_theme = $config['system.theme']->get('default');
    // We need to get the hierarchy of base themes from the current theme.
    $theme_with_ancestors = static::getDefaultThemeWithAncestors($default_theme);

    // 1. Is the component dynamic (consumes implicit inputs/context or has
    // logic)?
    if ($this->getComponentSource()->getPluginDefinition()['supportsImplicitInputs']) {
      return LibraryEnum::DynamicComponents;
    }

    // 2. Is the component provided by a module?
    if (in_array($this->provider, $installed_modules, TRUE)) {
      return $this->provider === 'canvas'
        // 2.B Is the providing module Canvas?
        ? LibraryEnum::Elements
        : LibraryEnum::ExtensionComponents;
    }

    // 3. Is the component provided by the default theme (or its base theme)?
    if (in_array($this->provider, $theme_with_ancestors, TRUE)) {
      return LibraryEnum::PrimaryComponents;
    }

    // 4. Is the component provided by neither a theme nor a module?
    if ($this->provider === NULL) {
      return LibraryEnum::PrimaryComponents;
    }

    throw new \LogicException('A Component is being normalized that belongs in no Canvas UI library.');
  }

  /**
   * {@inheritdoc}
   *
   * @see docs/config-management.md#3.1
   */
  public static function createFromClientSide(array $data): static {
    throw new \LogicException('Not supported: read-only for the client side, mutable only on the server side.');
  }

  /**
   * {@inheritdoc}
   *
   * @see docs/config-management.md#3.1
   */
  public function updateFromClientSide(array $data): void {
    throw new \LogicException('Not supported: read-only for the client side, mutable only on the server side.');
  }

  /**
   * {@inheritdoc}
   */
  public static function refineListQuery(QueryInterface &$query, RefinableCacheableDependencyInterface $cacheability): void {
    $container = \Drupal::getContainer();
    $theme_handler = $container->get(ThemeHandlerInterface::class);
    $installed_themes = array_keys($theme_handler->listInfo());
    $default_theme = $theme_handler->getDefault();

    // We need to get the hierarchy of base themes from the current theme.
    $theme_with_ancestors = static::getDefaultThemeWithAncestors($default_theme);

    // Omit Components provided by installed-but-not-default themes. This keeps
    // all other Components:
    // - module-provided ones
    // - default theme-provided
    // - provided by something else than an extension, such as an entity.
    $or_group = $query->orConditionGroup()
      ->condition('provider', operator: 'NOT IN', value: array_diff($installed_themes, $theme_with_ancestors))
      ->condition('provider', operator: 'IS NULL');
    $query->condition($or_group);

    // Reflect the conditions added to the query in the cacheability.
    $cacheability->addCacheTags([
      // The set of installed themes is stored in the `core.extension` config.
      'config:core.extension',
      // The default theme is stored in the `system.theme` config.
      'config:system.theme',
    ]);

    // @todo Ignore Components provided by ComponentSourceWithSwitchCasesInterface sources in https://www.drupal.org/project/canvas/issues/3525797
    // (Not ignoring them is a way to show these Components in the UI, which is
    // how we're bootstrapping the p13n component source functionality: it
    // allows the BE to be built ahead of the FE.)
  }

  /**
   * Computes the theme ancestry chain.
   *
   * @return array<string>
   */
  private static function getDefaultThemeWithAncestors(string $default_theme): array {
    $container = \Drupal::getContainer();
    $theme_initialization = $container->get(ThemeInitializationInterface::class);
    $theme_object = $theme_initialization->getActiveThemeByName($default_theme);
    $theme_ancestors = array_keys($theme_object->getBaseThemeExtensions());
    return [...$theme_ancestors, $default_theme];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    return $this->get('settings') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings): self {
    $this->set('settings', $settings);
    $this->sourcePluginCollection = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function loadVersion(string $version): static {
    $this->sourcePluginCollection = NULL;
    return parent::loadVersion($version);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\canvas\EntityHandlers\CanvasConfigEntityAccessControlHandler
   */
  public function onDependencyRemoval(array $dependencies): bool {
    // If only module and theme dependencies are being removed, there's nothing
    // to do: this Component cannot work any longer. So rely on the default
    // behavior of the config system: allow this to be deleted.
    // Note: The removal of module/theme dependencies is prevented by an
    // uninstall validator. So this should only be possible by using force.
    // @see \Drupal\canvas\ComponentDependencyUninstallValidator
    if (empty($dependencies['config'] ?? []) && empty($dependencies['content'] ?? [])) {
      return parent::onDependencyRemoval($dependencies);
    }

    // When it is affected, then if there's 0 component instances using it, still
    // there is nothing to do, because none of Drupal Canvas's config
    // entities are affected, nor are any Canvas fields on content entities.
    if (!\Drupal::service(ComponentAudit::class)->hasUsages($this)) {
      return parent::onDependencyRemoval($dependencies);
    }

    // However, if there's >=1 component instance for it, make this Component
    // use the `fallback` component source plugin to avoid deleting dependent Canvas
    // config entities and breaking Canvas component trees in content entities.
    $last_active_version = $this->getActiveVersion();
    $this->createVersion(ComponentInterface::FALLBACK_VERSION)
      ->setSettings([
        'last_active_version' => $last_active_version,
      ])
      // Disable this Component: prevent more instances getting created.
      ->disable();
    parent::onDependencyRemoval($dependencies);
    return TRUE;
  }

  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    assert($this->isLoadedVersionActiveVersion());
    $source = $this->getComponentSource();
    // Compute the appropriate `fallback_metadata` upon saving, except for the fallback plugin.
    if ($source instanceof Fallback) {
      return;
    }
    elseif ($source instanceof ComponentSourceWithSlotsInterface) {
      $this->versioned_properties[VersionedConfigEntityBase::ACTIVE_VERSION]['fallback_metadata']['slot_definitions'] = \array_map(self::cleanSlotDefinition(...), $source->getSlotDefinitions());
    }
    else {
      $this->versioned_properties[VersionedConfigEntityBase::ACTIVE_VERSION]['fallback_metadata']['slot_definitions'] = NULL;
    }
  }

  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    // For new Components, auto-create Folders based on their category.
    if (!$update) {
      $category = $this->getCategory();

      // If no category is set, there's no Folder to auto-create.
      if (empty($category)) {
        return;
      }
      $folder = Folder::loadByNameAndConfigEntityTypeId((string) $category, self::ENTITY_TYPE_ID);
      if (empty($folder)) {
        $folder = Folder::create([
          'name' => $category,
          'weight' => 0,
          'status' => TRUE,
          'configEntityTypeId' => self::ENTITY_TYPE_ID,
        ]);
      }
      $folder->addItems([$this->id])->save();
    }
  }

  public static function preDelete(EntityStorageInterface $storage, array $entities): void {
    // If the Component is deleted, remove it from the Folder it was in.
    foreach ($entities as $entity) {
      /** @var \Drupal\canvas\Entity\Component $entity */
      $category = $entity->getCategory();
      if (!empty($category)) {
        Folder::loadByNameAndConfigEntityTypeId((string) $category, self::ENTITY_TYPE_ID)?->removeItem($entity->id())?->save();
      }
    }
    parent::preDelete($storage, $entities);
  }

  /**
   * Validates the active version.
   *
   * To be used with the `Callback` constraint.
   *
   * @param string $active_version
   *   The Component's active version to validate.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validation execution context.
   *
   * @see \Symfony\Component\Validator\Constraints\CallbackValidator
   */
  public static function validateActiveVersion(string $active_version, ExecutionContextInterface $context): void {
    if ($active_version === ComponentInterface::FALLBACK_VERSION) {
      // No need to validate the fallback version.
      return;
    }

    // @phpstan-ignore-next-line
    $component = $context->getObject()->getParent();
    assert($component instanceof Mapping);
    assert($component->getDataDefinition()->getDataType() === 'canvas.component.*');
    // The version should be based on the source-specific settings for this
    // version, not on anything else (certainly not the fallback metadata.)
    $raw = $component->getValue();
    try {
      $source = \Drupal::service(ComponentSourceManager::class)
        ->createInstance($raw['source'], [
          'local_source_id' => $raw['source_local_id'],
          ...$raw['versioned_properties'][VersionedConfigEntityInterface::ACTIVE_VERSION]['settings'],
        ]);
      assert($source instanceof ComponentSourceInterface);
      $expected_version = $source->generateVersionHash();
    }
    catch (\Exception) {
      // Something more serious is wrong with this component, let existing
      // validation trap things like missing plugins or dependencies.
      return;
    }
    if ($expected_version !== $active_version) {
      $context->addViolation('The version @actual_version does not match the hash of the settings for this version, expected @expected_version.', [
        '@actual_version' => $active_version,
        '@expected_version' => $expected_version,
      ]);
    }
  }

  /**
   * Clean slot definitions to remove unsupported keys.
   *
   * @param array $definition
   *
   * @return array{title: string, description?: string, examples: string[]}
   *   Clean definitions.
   */
  private static function cleanSlotDefinition(array $definition): array {
    // Some SDC have additional keys in their slot definitions. Remove those
    // that aren't specified in the SDC metadata schema and in our config
    // schema.
    // @todo Remove when core enforces this - https://www.drupal.org/i/3522623
    /** @var array{title: string, description?: string, examples: string[]} */
    return \array_intersect_key($definition, array_flip([
      'title',
      'description',
      'examples',
    ]));
  }

}
