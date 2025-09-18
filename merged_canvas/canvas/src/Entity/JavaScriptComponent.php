<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\canvas\AutoSaveEntity;
use Drupal\canvas\ClientSideRepresentation;
use Drupal\canvas\EntityHandlers\JavascriptComponentStorage;
use Drupal\canvas\EntityHandlers\VisibleWhenDisabledCanvasConfigEntityAccessControlHandler;
use Drupal\canvas\Exception\ConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;

#[ConfigEntityType(
  id: self::ENTITY_TYPE_ID,
  label: new TranslatableMarkup('Code component'),
  label_singular: new TranslatableMarkup('code component'),
  label_plural: new TranslatableMarkup('code components'),
  label_collection: new TranslatableMarkup('Code components'),
  admin_permission: self::ADMIN_PERMISSION,
  handlers: [
    'storage' => JavascriptComponentStorage::class,
    'access' => VisibleWhenDisabledCanvasConfigEntityAccessControlHandler::class,
  ],
  entity_keys: [
    'id' => 'machineName',
    'label' => 'name',
    'status' => 'status',
  ],
  config_export: [
    'machineName',
    'name',
    'props',
    'required',
    'slots',
    'js',
    'css',
    'dataDependencies',
  ],
  constraints: [
    'JsComponentHasValidAndSupportedSdcMetadata' => NULL,
  ],
)]
final class JavaScriptComponent extends ConfigEntityBase implements CanvasAssetInterface, FolderItemInterface {

  use CanvasAssetLibraryTrait;
  use ConfigUpdaterAwareEntityTrait;

  public const string ENTITY_TYPE_ID = 'js_component';
  public const string ADMIN_PERMISSION = 'administer code components';
  private const string ASSETS_DIRECTORY = 'assets://astro-island/';

  /**
   * The component machine name.
   */
  protected string $machineName;

  /**
   * The human-readable label of the component.
   */
  protected ?string $name;

  /**
   * The props of the component.
   */
  protected ?array $props = [];

  /**
   * The required props of the component.
   *
   * @var string[]
   */
  protected ?array $required = [];

  /**
   * The slots of the component.
   */
  protected ?array $slots = [];

  /**
   * Data dependencies.
   */
  protected ?array $dataDependencies;

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->machineName;
  }

  /**
   * {@inheritdoc}
   *
   * This corresponds to `CodeComponent` in openapi.yml.
   *
   * @see docs/adr/0005-Keep-the-front-end-simple.md
   */
  public function normalizeForClientSide(): ClientSideRepresentation {
    // TRICKY: config entity properties may allow NULL, but only valid, saved
    // config entities are ever normalized: those that have passed validation
    // against config schema.
    assert(is_array($this->js));
    assert(is_array($this->css));
    return ClientSideRepresentation::create(
      values: [
        'machineName' => $this->id(),
        'name' => (string) $this->label(),
        'status' => $this->status(),
        'props' => $this->props,
        'required' => $this->required,
        'slots' => $this->slots,
        'sourceCodeJs' => $this->js['original'] ?? '',
        'sourceCodeCss' => $this->css['original'] ?? '',
        'compiledJs' => $this->js['compiled'] ?? '',
        'compiledCss' => $this->css['compiled'] ?? '',
        'dataDependencies' => $this->dataDependencies,
      ],
      preview: [
        '#markup' => '@todo Make something 🆒 in https://www.drupal.org/project/canvas/issues/3498889',
      ],
    )->addCacheableDependency($this);
  }

  /**
   * {@inheritdoc}
   *
   * This corresponds to `CodeComponent` in openapi.yml.
   *
   * @see docs/adr/0005-Keep-the-front-end-simple.md
   */
  public static function createFromClientSide(array $data): static {
    $entity = static::create(['machineName' => $data['machineName']]);
    $entity->updateFromClientSide($data);
    return $entity;
  }

  /**
   * {@inheritdoc}
   *
   * This corresponds to `CodeComponent` in openapi.yml.
   *
   * @see docs/adr/0005-Keep-the-front-end-simple.md
   */
  public function updateFromClientSide(array $data): void {
    foreach (array_intersect_key($data, array_flip([
      'machineName',
      'name',
      'status',
      'required',
      'props',
      'slots',
      'dataDependencies',
    ])) as $key => $value) {
      $this->set($key, $value);
    }

    if (array_key_exists('sourceCodeCss', $data) || array_key_exists('compiledCss', $data)) {
      $this->set('css', [
        'original' => $data['sourceCodeCss'] ?? '',
        'compiled' => $data['compiledCss'] ?? '',
      ]);
    }

    $violation_list = new EntityConstraintViolationList($this);
    if (array_key_exists('sourceCodeJs', $data) || array_key_exists('compiledJs', $data)) {
      if (!array_key_exists('importedJsComponents', $data)) {
        $violation_list->add(new ConstraintViolation(
          "The 'importedJsComponents' field is required when 'sourceCodeJs' or 'compiledJs' is provided",
          "The 'importedJsComponents' field is required when 'sourceCodeJs' or 'compiledJs' is provided",
          [],
          NULL,
          "importedJsComponents",
          NULL
        ));
        throw new ConstraintViolationException($violation_list);
      }
      foreach ($data['importedJsComponents'] as $key => $js_component_name) {
        // Test that the importedJsComponents are valid names.
        if (!preg_match('/^[a-z0-9_-]+$/', $js_component_name)) {
          $violation_list->add(new ConstraintViolation(
            "The 'importedJsComponents' contains an invalid component name.",
            "The 'importedJsComponents' contains an invalid component name.",
            [],
            NULL,
            "importedJsComponents",
            NULL
          ));
        }
      }
      if ($violation_list->count() > 0) {
        throw new ConstraintViolationException($violation_list);
      }
      // The client calculates imported JavaScript components dependencies. This
      // value is never returned to the client as it will always recalculate it
      // based off sourceCodeJs.
      $this->addJavaScriptComponentsDependencies($data['importedJsComponents']);
      $this->set('js', [
        'original' => $data['sourceCodeJs'] ?? '',
        'compiled' => $data['compiledJs'] ?? '',
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function refineListQuery(QueryInterface &$query, RefinableCacheableDependencyInterface $cacheability): void {
    // Nothing to do.
  }

  /**
   * Code components are not Twig-defined but still aim to match SDC closely.
   *
   * TRICKY: while `props` and `slots` are already individually validated
   * against the JSON schema, the overall structure must also be valid in a way
   * that the SDC's JSON schema does not actually validate: crucial parts are
   * validated only in PHP!
   *
   * @return array{machineName: string, extension_type: string, id: string, provider: string, name: string, props: array, slots?: array, library: array, path: string, template: string}
   *
   * @see core/assets/schemas/v1/metadata-full.schema.json
   * @see \Drupal\Core\Theme\Component\ComponentValidator::validateDefinition()
   * @see \Drupal\Tests\Core\Theme\Component\ComponentValidatorTest::loadComponentDefinitionFromFs()
   */
  public function toSdcDefinition(): array {
    $definition = [
      'machineName' => (string) $this->id(),
      'extension_type' => 'module',
      'id' => 'canvas:' . $this->id(),
      'provider' => 'canvas',
      'name' => (string) $this->label(),
      'props' => [
        'type' => 'object',
        'properties' => $this->props ?? [],
      ],
      // No equivalents exist nor can be generated; specify hard-coded values
      // that allow this to be considered a valid SDC definition.
      'library' => [],
      'path' => '',
      // This needs to be non empty.
      'template' => 'phony',
    ];
    // Slots are optional. Setting the `slots` key to an empty array is invalid.
    // @see \Drupal\canvas\Plugin\Validation\Constraint\JsComponentHasValidAndSupportedSdcMetadataConstraintValidator
    if ($this->slots) {
      foreach ($this->slots as $slot_name => $slot) {
        // Force empty slots to be an object; ComponentValidator casts non-
        // empty arrays to objects, but empty arrays trigger a false positive
        // validation error: "Array value found, but an object is required".
        // @todo Remove this after https://www.drupal.org/project/drupal/issues/3524163 is fixed in core.
        if ($slot === []) {
          $slot = new \stdClass();
        }
        $definition['slots'][$slot_name] = $slot;
      }
    }
    // Required properties are optional. Setting the `props.required` key to an
    // empty array is invalid.
    // @see \Drupal\canvas\Plugin\Validation\Constraint\JsComponentHasValidAndSupportedSdcMetadataConstraintValidator
    if ($this->required) {
      $definition['props']['required'] = $this->required;
    }
    return $definition;
  }

  /**
   * Sets value for props.
   *
   * @param array<string, array{type: string, format?: string, examples?: string|array<string>, title: string}> $props
   *   Value for Props.
   */
  public function setProps(array $props): self {
    $this->props = $props;
    return $this;
  }

  /**
   * Gets required props.
   *
   * @return array
   *   Required props.
   */
  public function getRequiredProps(): array {
    return $this->required ?? [];
  }

  /**
   * Gets component props.
   *
   * @return array|null
   *   Component props.
   */
  public function getProps(): ?array {
    return $this->props;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    static::getConfigUpdater()->updateJavaScriptComponent($this);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);
    // The files generated in CanvasAssetStorage::doSave() have a content-dependent
    // hash in their name. This has 2 consequences:
    // 1. Cached responses that referred to an older version, continue to work.
    // 2. New responses must use the newly generated files, which requires the
    //    asset library to point to those new files. Hence the library info must
    //    be recalculated.
    // @see \canvas_library_info_build()
    Cache::invalidateTags(['library_info']);
  }

  /**
   * Add the imported Javascript components as enforced dependencies.
   *
   * Enforced dependencies are not reset during dependency calculation.
   *
   * @param array<string> $imported_js_components
   *   The names of the JavaScript components to add as dependencies.
   *
   * @throws \Drupal\canvas\Exception\ConstraintViolationException
   *   Thrown if any of the JavaScript components do not exist.
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityBase::calculateDependencies
   */
  protected function addJavaScriptComponentsDependencies(array $imported_js_components): void {
    $violation_list = new EntityConstraintViolationList($this);
    foreach ($imported_js_components as $key => $js_component_name) {
      $js_component = JavaScriptComponent::load($js_component_name);
      if (!$js_component) {
        $violation_list->add(new ConstraintViolation(
          "The JavaScript component with the machine name '$js_component_name' does not exist.",
          "The JavaScript component with the machine name '$js_component_name' does not exist.",
          [],
          NULL,
          "importedJsComponents.$key",
          $js_component_name
        ));
      }
    }
    if ($violation_list->count() > 0) {
      throw new ConstraintViolationException($violation_list);
    }
    $imported_js_component_dependency_names = array_values(array_map(
      fn(string $component_name) => $this->getConfigPrefix() . ".$component_name",
      $imported_js_components
    ));
    $this->dependencies['enforced']['config'] ??= [];
    // Remove all the current JavaScript component enforced dependencies.
    $this->dependencies['enforced']['config'] = array_filter(
      $this->dependencies['enforced']['config'],
      fn(string $dependency) => !str_starts_with($dependency, $this->getConfigPrefix())
    );
    $this->dependencies['enforced']['config'] = array_unique(array_merge(
      $this->dependencies['enforced']['config'],
      $imported_js_component_dependency_names
    ));
    if (empty($this->dependencies['enforced']['config'])) {
      unset($this->dependencies['enforced']['config']);
    }
    if (empty($this->dependencies['enforced'])) {
      unset($this->dependencies['enforced']);
    }
  }

  protected function getConfigPrefix(): string {
    $entity_type = $this->getEntityType();
    assert($entity_type instanceof ConfigEntityTypeInterface);
    return $entity_type->getConfigPrefix();
  }

  public function getComponentUrl(FileUrlGeneratorInterface $generator, bool $isPreview): string {
    if (!$isPreview) {
      return $generator->generateString($this->getJsPath());
    }
    return Url::fromRoute('canvas.api.config.auto-save.get.js', [
      'canvas_config_entity_type_id' => self::ENTITY_TYPE_ID,
      'canvas_config_entity' => $this->id(),
    ])->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getAssetLibrary(bool $isPreview): string {
    // Inside the Canvas UI, always load the draft even if there isn't one. Let the
    // controller logic automatically serve the non-draft assets when a draft
    // disappears. This is necessary to allow for asset library dependencies,
    // and avoids race conditions.
    // @see \Drupal\canvas\Hook\LibraryHooks::libraryInfoBuild()
    // @see \Drupal\canvas\Controller\ApiConfigAutoSaveControllers::getCss()
    // @see \Drupal\canvas\Controller\ApiConfigAutoSaveControllers::getJs()
    return 'canvas/astro_island.' . $this->id() . ($isPreview ? '.draft' : '');
  }

  private static function shouldLoadAssetFromAutoSave(AutoSaveEntity $autoSave, bool $isPreview) : bool {
    return $isPreview && !$autoSave->isEmpty();
  }

  public function getComponentDependencies(AutoSaveEntity $autoSave, bool $isPreview): array {
    $instance = $this;
    if (self::shouldLoadAssetFromAutoSave($autoSave, $isPreview)) {
      \assert($autoSave->entity instanceof self);
      $instance = $autoSave->entity;
    }

    $js_dependencies = \array_filter(
      $instance->getDependencies()['config'] ?? [],
      static fn(string $dependency) => \str_starts_with($dependency, $instance->getConfigPrefix())
    );
    $js_component_ids = array_map(fn($dependency) => mb_substr($dependency, mb_strlen($this->getConfigPrefix()) + 1), $js_dependencies);
    return self::loadMultiple($js_component_ids);
  }

  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    if ($dependencies = $this->getDependencies()) {
      $cache_tags = array_merge($cache_tags, array_map(fn($dependency) => "config:$dependency", $dependencies['config'] ?? []));
    }
    return \array_values($cache_tags);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\canvas\Hook\ComponentSourceHooks::jsSettingsAlter()
   */
  public function getAssetLibraryDependencies(): array {
    return \array_map(static fn (string $dependency): string => \sprintf('canvas/canvasData.%s', $dependency), $this->dataDependencies['drupalSettings'] ?? []);
  }

}
