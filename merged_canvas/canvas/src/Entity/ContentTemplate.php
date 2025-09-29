<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\canvas\ClientSideRepresentation;
use Drupal\canvas\EntityHandlers\VisibleWhenDisabledCanvasConfigEntityAccessControlHandler;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewModeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Storage\ComponentTreeLoader;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;

/**
 * Defines a template for content entities in a particular view mode.
 *
 * This MUST be a new config entity type, because doing something like Layout
 * Builder's `LayoutBuilderEntityViewDisplay` is impossible if Canvas wants to
 * provide a smooth upgrade path from LB, thanks to
 * `\Drupal\layout_builder\Hook\LayoutBuilderHooks::entityTypeAlter()` -- only
 * one module can do that!
 *
 * @phpstan-import-type ExposedSlotDefinitions from \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
 */
#[ConfigEntityType(
  id: self::ENTITY_TYPE_ID,
  label: new TranslatableMarkup('Content template'),
  label_collection: new TranslatableMarkup('Content templates'),
  label_singular: new TranslatableMarkup('content template'),
  label_plural: new TranslatableMarkup('content templates'),
  entity_keys: [
    'id' => 'id',
  ],
  handlers: [
    'access' => VisibleWhenDisabledCanvasConfigEntityAccessControlHandler::class,
  ],
  admin_permission: self::ADMIN_PERMISSION,
  constraints: [
    'ImmutableProperties' => [
      'id',
      'content_entity_type_id',
      'content_entity_type_bundle',
      'content_entity_type_view_mode',
    ],
  ],
  config_export: [
    'id',
    'content_entity_type_id',
    'content_entity_type_bundle',
    'content_entity_type_view_mode',
    'component_tree',
    'exposed_slots',
  ],
)]
final class ContentTemplate extends ComponentTreeConfigEntityBase implements CanvasHttpApiEligibleConfigEntityInterface, EntityViewDisplayInterface, AutoSavePublishAwareInterface {

  use ConfigUpdaterAwareEntityTrait;

  public const string ENTITY_TYPE_ID = 'content_template';

  public const string ADMIN_PERMISSION = 'administer content templates';

  /**
   * ID, composed of content entity type ID + bundle + view mode.
   *
   * @see \Drupal\canvas\Plugin\Validation\Constraint\StringPartsConstraint
   */
  protected ?string $id;

  /**
   * Entity type to be displayed.
   *
   * @var string|null
   */
  protected ?string $content_entity_type_id;

  /**
   * Bundle to be displayed.
   *
   * @var string|null
   */
  protected ?string $content_entity_type_bundle;

  /**
   * View or mode to be displayed.
   *
   * @var string|null
   */
  protected ?string $content_entity_type_view_mode;

  /**
   * The exposed slots.
   *
   * @var ?array<string, array{'component_uuid': string, 'slot_name': string, 'label': string}>
   */
  protected ?array $exposed_slots = [];

  /**
   * Disabled by default.
   *
   * @var bool
   */
  protected $status = FALSE;

  /**
   * Tries to load a template for a particular entity, in a specific view mode.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   An entity, presumably the one being viewed.
   * @param string $view_mode
   *   The view mode in which we're viewing the entity.
   *
   * @return self|null
   *   A template for the given entity in the given view mode, or NULL if one
   *   does not exist.
   */
  public static function loadForEntity(FieldableEntityInterface $entity, string $view_mode): ?self {
    $id = implode('.', [
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $view_mode,
    ]);
    return self::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->content_entity_type_id . '.' . $this->content_entity_type_bundle . '.' . $this->content_entity_type_view_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    $this->id = $this->id();
    parent::preSave($storage);
    self::getConfigUpdater()->updateConfigEntityWithComponentTreeInputs($this);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup {
    $entity_type = $this->entityTypeManager()
      ->getDefinition($this->getTargetEntityTypeId());
    assert($entity_type instanceof EntityTypeInterface);

    $bundle_info = \Drupal::service(EntityTypeBundleInfoInterface::class)
      ->getBundleInfo($entity_type->id());
    $bundle = $this->getTargetBundle();

    $variables = [
      '@entities' => $entity_type->getCollectionLabel(),
      '@mode' => $this->getViewMode()->label(),
    ];

    if ($entity_type->getBundleEntityType()) {
      $variables['@entities'] = $entity_type->getPluralLabel();
      $variables['@bundle'] = $bundle_info[$bundle]['label'] ?? throw new \RuntimeException("The '$bundle' bundle of the {$entity_type->id()} entity type has no label.");
      return new TranslatableMarkup('@bundle @entities — @mode view', $variables);
    }
    return new TranslatableMarkup('@entities — @mode view', $variables);
  }

  /**
   * Gets the view mode that this template is for.
   *
   * @return \Drupal\Core\Entity\EntityViewModeInterface
   *   The view mode entity.
   */
  private function getViewMode(): EntityViewModeInterface {
    $view_mode = $this->entityTypeManager()
      ->getStorage('entity_view_mode')
      ->load($this->getTargetEntityTypeId() . '.' . $this->getMode());
    assert($view_mode instanceof EntityViewModeInterface);
    return $view_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): static {
    parent::calculateDependencies();

    $this->addDependencies($this->getComponentTree()->calculateDependencies());

    // Ensure we depend on the associated view mode.
    $view_mode = $this->getViewMode();
    $this->addDependency($view_mode->getConfigDependencyKey(), $view_mode->getConfigDependencyName());

    return $this;
  }

  /**
   * Returns information about the slots exposed by this template.
   *
   * @return array<string, array{'component_uuid': string, 'slot_name': string, 'label': string}>
   */
  public function getExposedSlots(): array {
    return $this->get('exposed_slots') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentTree(?FieldableEntityInterface $parent = NULL): ComponentTreeItemList {
    $item = $this->createDanglingComponentTreeItemList($parent);
    $item->setValue(\array_values($this->component_tree ?? []));
    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function createCopy($view_mode): never {
    throw new \BadMethodCallException(__METHOD__ . '() is not implemented yet.');
  }

  /**
   * {@inheritdoc}
   */
  public function getComponents(): array {
    // A linear list of "components", where each component is a field formatter,
    // doesn't make sense when using Canvas.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getComponent($name): null {
    // @see ::getComponents()
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setComponent($name, array $options = []): never {
    throw new \LogicException(__FUNCTION__ . '() does not make sense for content templates. The calling could should be updated to check for this.');
  }

  /**
   * {@inheritdoc}
   */
  public function removeComponent($name): never {
    throw new \LogicException(__FUNCTION__ . '() does not make sense for content templates. The calling could should be updated to check for this.');
  }

  /**
   * {@inheritdoc}
   */
  public function getHighestWeight(): null {
    // @see ::getComponents()
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderer($field_name): null {
    // @see ::getComponents()
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId(): string {
    return (string) $this->content_entity_type_id;
  }

  public function getTargetEntityDataDefinition(): EntityDataDefinitionInterface {
    return EntityDataDefinition::create(
      $this->getTargetEntityTypeId(),
      $this->getTargetBundle(),
    );
  }

  public function createEmptyTargetEntity(): FieldableEntityInterface {
    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage($this->getTargetEntityTypeId());
    $content_entity_type = $entity_type_manager->getDefinition($this->getTargetEntityTypeId());
    assert($content_entity_type instanceof ContentEntityTypeInterface);

    $empty_target_entity = $content_entity_type->hasKey('bundle')
      ? $storage->create([
        $content_entity_type->getKey('bundle') => $this->getTargetBundle(),
      ])
      : $storage->create();
    assert($empty_target_entity instanceof FieldableEntityInterface);

    return $empty_target_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getMode(): string {
    return (string) $this->content_entity_type_view_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalMode(): never {
    throw new \BadMethodCallException(__METHOD__ . '() is not implemented yet.');
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetBundle(): string {
    return (string) $this->content_entity_type_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetBundle($bundle): static {
    return $this->set('bundle', $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function build(FieldableEntityInterface $entity, bool $isPreview = FALSE): array {
    // The entity should not be able to expose its own full, independently
    // renderable component tree -- if it can, why is it even using a template?
    if ($entity instanceof ComponentTreeEntityInterface) {
      throw new \LogicException('Content templates cannot be applied to entities that have their own component trees.');
    }

    // When no exposed slots exist, no Canvas field is required.
    // @todo Consider always requiring a Canvas field again after 1.0, once exposed slot support is added to the UI.
    if (empty($this->getExposedSlots())) {
      return $this->getComponentTree($entity)->toRenderable($this, $isPreview);
    }

    // @todo Prior to supporting multiple exposed slots, https://www.drupal.org/i/3526189
    //   must be investigated and a decision needs to be made.
    assert(count($this->getExposedSlots()) === 1);
    $canvas_field_name = \Drupal::service(ComponentTreeLoader::class)
      ->getCanvasFieldName($entity);
    $sub_tree_item_list = $entity->get($canvas_field_name);
    \assert($sub_tree_item_list instanceof ComponentTreeItemList);
    return $this->getComponentTree($entity)
      ->injectSubTreeItemList($this->getExposedSlots(), $sub_tree_item_list)
      ->toRenderable($this, $isPreview);
  }

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $entities): array {
    return array_map($this->build(...), $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    // Normally, this would be a collection of field formatter instances, but
    // that doesn't make sense when using Canvas.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = FALSE;
    $tree = $this->getComponentTree();

    foreach ($dependencies as $type => $dependencies_of_type) {
      foreach ($dependencies_of_type as $dependency) {
        if ($dependency instanceof ConfigEntityInterface) {
          $dependency = $dependency->getConfigDependencyName();
        }
        foreach ($tree as $item) {
          \assert($item instanceof ComponentTreeItem);
          $changed |= $item->updatePropSourcesOnDependencyRemoval($type, $dependency);
        }
      }
    }
    if ($changed) {
      $this->setComponentTree($tree->getValue());
    }

    $changed |= parent::onDependencyRemoval($dependencies);
    return (bool) $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value): self {
    if ($property_name === 'component_tree') {
      // Ensure predictable order of tree items.
      $value = self::generateComponentTreeKeys($value);
    }
    return parent::set($property_name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function autoSavePublish(): self {
    $this->setStatus(TRUE);
    return $this;
  }

  public function normalizeForClientSide(): ClientSideRepresentation {
    $entity_type_manager = $this->entityTypeManager();
    $content_entity_type = $entity_type_manager->getDefinition($this->content_entity_type_id);
    $storage = $entity_type_manager->getStorage($this->getTargetEntityTypeId());

    // Determine the preview entity (if any), and ensure correct cacheability:
    // - for the query in ::getSuggestedPreviewEntity()
    // - for the access checking here
    $preview_entity = $this->getSuggestedPreviewEntity();
    $preview_entity_cacheability = (new CacheableMetadata())
      ->addCacheContexts($storage->getEntityType()->getListCacheContexts())
      ->addCacheTags($storage->getEntityType()->getBundleListCacheTags($this->getTargetBundle()));
    if ($preview_entity !== NULL) {
      $preview_entity_access = $preview_entity->access('view', return_as_object: TRUE);
      $preview_entity_cacheability->addCacheableDependency($preview_entity_access);
      if (!$preview_entity_access->isAllowed()) {
        // Do not return preview entity ID if not viewable.
        $preview_entity = NULL;
      }
    }

    return ClientSideRepresentation::create(
      values: [
        'entityType' => $this->content_entity_type_id,
        'bundle' => $this->content_entity_type_bundle,
        'viewMode' => $this->content_entity_type_view_mode,
        'viewModeLabel' => $this->getViewMode()->label(),
        'label' => $this->label(),
        'status' => $this->status,
        'id' => $this->id(),
        'suggestedPreviewEntityId' => $preview_entity ? (int) $preview_entity->id() : NULL,
      ],
      preview: NULL,
    )
      ->addCacheableDependency($preview_entity_cacheability)
      // Cacheability metadata for the suggested preview entity.
      ->addCacheTags($content_entity_type->getListCacheContexts())
      // @phpstan-ignore-next-line argument.type
      ->addCacheTags($content_entity_type->getBundleListCacheTags($this->content_entity_type_bundle));
  }

  public static function createFromClientSide(array $data): static {
    ['entityType' => $entity_type, 'bundle' => $bundle, 'viewMode' => $view_mode] = $data;
    return self::create([
      'id' => "$entity_type.$bundle.$view_mode",
      'content_entity_type_id' => $entity_type,
      'content_entity_type_bundle' => $bundle,
      'content_entity_type_view_mode' => $view_mode,
      'component_tree' => [],
      'status' => FALSE,
    ]);
  }

  public function updateFromClientSide(array $data): void {
    // This config entity is updated indirectly, using the editor frame.
    // @see \Drupal\canvas\Controller\ApiLayoutController::patch()
    throw new \LogicException();
  }

  public static function refineListQuery(QueryInterface &$query, RefinableCacheableDependencyInterface $cacheability): void {
    // Nothing to do.
  }

  public static function getPreviewSuggestionQuery(string $entity_type_id, string $bundle, int $limit): QueryInterface {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_definition = $entity_type_manager->getDefinition($entity_type_id);

    $id_key = $entity_definition->getKey('id');
    assert(is_string($id_key));
    $entity_query = $entity_type_manager->getStorage($entity_type_id)->getQuery()
      ->accessCheck(TRUE)
      ->range(0, $limit);
    if ($entity_definition->hasKey('bundle')) {
      $bundle_key = $entity_definition->getKey('bundle');
      assert(is_string($bundle_key));
      $entity_query->condition($bundle_key, $bundle);
    }
    // @todo Remove conditionality in https://www.drupal.org/i/3498525
    if ($entity_definition->hasKey('published')) {
      $published_key = $entity_definition->getKey('published');
      assert(is_string($published_key));
      $entity_query->condition($published_key, TRUE);
    }
    // @todo Remove conditionality in https://www.drupal.org/i/3498525
    if ($entity_definition->entityClassImplements(EntityChangedInterface::class)) {
      $entity_query->sort('changed', 'DESC');
    }
    else {
      $entity_query->sort($id_key, 'DESC');
    }
    return $entity_query;
  }

  private function getSuggestedPreviewEntity(): ?ContentEntityInterface {
    assert($this->content_entity_type_id !== NULL);

    $query = self::getPreviewSuggestionQuery(
      $this->getTargetEntityTypeId(),
      $this->getTargetBundle(),
      1
    );
    $results = $query->execute();
    assert(is_array($results));

    if (empty($results)) {
      return NULL;
    }

    $entity = $this->entityTypeManager()
      ->getStorage($this->getTargetEntityTypeId())
      ->load(reset($results));
    assert($entity instanceof ContentEntityInterface);
    return $entity;
  }

}
