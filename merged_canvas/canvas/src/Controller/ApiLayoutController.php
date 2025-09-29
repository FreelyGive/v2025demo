<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\ClientDataToEntityConverter;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\canvas\Render\PreviewEnvelope;
use Drupal\canvas\Storage\ComponentTreeLoader;
use GuzzleHttp\Psr7\Query;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @phpstan-import-type ComponentConfigEntityId from \Drupal\canvas\Entity\Component
 * @phpstan-import-type SingleComponentInputArray from \Drupal\canvas\Plugin\DataType\ComponentInputs
 * @phpstan-type ComponentClientStructureArray array{nodeType: 'component', uuid: string, type: ComponentConfigEntityId, slots: array<int, mixed>}
 * @phpstan-type RegionClientStructureArray array{nodeType: 'region', id: string, name: string, components: array<int, ComponentClientStructureArray>}
 * @phpstan-type LayoutClientStructureArray array<int, RegionClientStructureArray>
 */
final class ApiLayoutController {

  use AutoSaveValidateTrait;
  use ClientServerConversionTrait;
  use EntityFormTrait;
  private array $regions;
  private array $regionsClientSideIds;

  public function __construct(
    private readonly AutoSaveManager $autoSaveManager,
    private readonly ThemeManagerInterface $themeManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FormBuilderInterface $formBuilder,
    private readonly ClientDataToEntityConverter $converter,
    private readonly ComponentTreeLoader $componentTreeLoader,
  ) {
    $theme = $this->themeManager->getActiveTheme()->getName();
    $theme_regions = system_region_list($theme);

    // The PageRegion config entities get a corresponding `nodeType: region` in
    // the client-side representation. Their IDs match that of the server-side
    // PageRegion config entities. With the exception of the special-cased
    // `content` region, because that is the only region guaranteed to exist
    // across all themes, and for which no PageRegion config entity is allowed
    // to exist.
    // @see \Drupal\system\Controller\SystemController::themesPage()
    $server_side_ids = array_map(
      fn (string $region_name): string => $region_name === CanvasPageVariant::MAIN_CONTENT_REGION
        ? CanvasPageVariant::MAIN_CONTENT_REGION
        : "$theme.$region_name",
      array_keys($theme_regions)
    );
    $this->regionsClientSideIds = array_combine($server_side_ids, array_keys($theme_regions));
    $this->regions = array_combine($server_side_ids, $theme_regions);
    assert(array_key_exists(CanvasPageVariant::MAIN_CONTENT_REGION, $this->regions));
  }

  /**
   * Returns JSON for the entity layout and fields that the user can edit.
   */
  public function get((ContentEntityInterface&EntityPublishedInterface)|ContentTemplate $entity, ?ContentEntityInterface $preview_entity = NULL): PreviewEnvelope {
    // @todo Start using $preview_entity in https://www.drupal.org/i/3541057
    assert(!$entity instanceof ContentTemplate || !is_null($preview_entity));
    $regions = PageRegion::loadForActiveTheme();

    $autoSaveData = $this->autoSaveManager->getAutoSaveEntity($entity);
    if (!$autoSaveData->isEmpty()) {
      $entity = $autoSaveData->entity;
      \assert($entity instanceof ContentEntityInterface || $entity instanceof ContentTemplate);
    }

    $model = [];
    // Build the content region.
    $tree = $this->componentTreeLoader->load($entity);
    $content_layout = $this->buildRegion(CanvasPageVariant::MAIN_CONTENT_REGION, $tree, $model, $preview_entity);
    $layout = [$content_layout];
    $is_new = AutoSaveManager::entityIsConsideredNew($entity);

    if ($regions) {
      \assert($model !== NULL);
      $this->addGlobalRegions($regions, $model, $layout);
      $layout_keyed_by_region = array_combine(array_map(static fn($region) => $region['id'], $layout), $layout);
      // Reorder the layout to match theme order.
      $layout = array_values(array_replace(
        array_intersect_key(array_flip($this->regionsClientSideIds), $layout_keyed_by_region),
        $layout_keyed_by_region
      ));
    }

    $data = [
      // Maps to the `tree` property of the Canvas field type.
      // @see \Drupal\canvas\Plugin\DataType\ComponentTreeStructure
      // @todo Settle on final names and get in sync.
      'layout' => $layout,
      // Maps to the `inputs` property of the Canvas field type.
      // @see \Drupal\canvas\Plugin\DataType\ComponentInputs
      // @todo Settle on final names and get in sync.
      // If the model is empty return an empty object to ensure it is encoded as
      // an object and not empty array.
      'model' => empty($model) ? new \stdClass() : $model,
      'isNew' => $is_new,
      'autoSaves' => $this->getAutoSaveHashes(array_merge([$entity], self::getEditableRegions())),
    ];
    if ($entity instanceof ContentEntityInterface && $entity instanceof EntityPublishedInterface) {
      $data['isPublished'] = $entity->isPublished();
      $data['entity_form_fields'] = $this->getFilteredEntityData($entity);
    }
    return new PreviewEnvelope($this->buildPreviewRenderable($entity, $preview_entity), $data);
  }

  private function buildRegion(string $id, ?ComponentTreeItemList $items = NULL, ?array &$model = NULL, ?FieldableEntityInterface $preview_entity = NULL): array {
    if ($items) {
      $built = $items->getClientSideRepresentation($preview_entity);
      $model += $built['model'];
      $components = $built['layout'];
    }
    else {
      $components = [];
    }

    return [
      'nodeType' => 'region',
      'id' => $this->regionsClientSideIds[$id],
      'name' => $this->regions[$id],
      'components' => $components,
    ];
  }

  private function getFilteredEntityData(FieldableEntityInterface $entity): array {
    // @todo Try to return this from the form controller instead.
    // @see https://www.drupal.org/project/canvas/issues/3496875
    // This mirrors a lot of the logic of EntityFormController::form. We want
    // the entity data in the same shape as form state for an entity form so
    // that if matches that of the form built by EntityFormController::form.
    // @see \Drupal\canvas\Controller\EntityFormController::form
    $form_object = $this->entityTypeManager->getFormObject($entity->getEntityTypeId(), 'default');
    $form_state = $this->buildFormState($form_object, $entity, 'default');
    $form = $this->formBuilder->buildForm($form_object, $form_state);
    // Filter out form values that are not accessible to the client.
    $values = self::filterFormValues($form_state->getValues(), $form, $entity);

    // If the user had previously submitted any invalid values, these will be
    // stored in their respective violations in the auto-save manager. We
    // restore invalid values so that if a user is attempting to rectify invalid
    // values the value shown matches what was previously entered.
    $violations = $this->autoSaveManager->getEntityFormViolations($entity);
    foreach ($violations as $violation) {
      $property_path = $violation->getPropertyPath();
      // @see \Drupal\canvas\ClientDataToEntityConverter::setEntityFields
      $parents = \explode('.', $property_path);
      NestedArray::setValue($values, $parents, $violation->getInvalidValue());
    }

    // Collapse form values into the respective element name, e.g.
    // ['title' => ['value' => 'Node title']] becomes
    // ['title[0][value]' => 'Node title'. This keeps the data sent in the same
    // shape as the 'name' attributes on each of the form elements built by the
    // form element and avoids needing to smooth out the idiosyncrasies of each
    // widget's structure.
    // @see \Drupal\canvas\Controller\EntityFormController::form
    return Query::parse(\http_build_query($values));
  }

  private function addGlobalRegions(array $regions, array &$model, array &$layout, bool $includeAllRegions = FALSE): void {
    // Only expose regions marked as editable in the `layout` for the client.
    foreach ($regions as $id => $region) {
      assert($region instanceof PageRegion);
      assert($region->status() === TRUE);
      if (!$region->access('edit') && !$includeAllRegions) {
        // If the user doesn't have access to a region, we don't need to include
        // it.
        continue;
      }

      // Use auto-save data for each PageRegion config entity if available.
      if ($draft_region = $this->autoSaveManager->getAutoSaveEntity($region)->entity) {
        \assert($draft_region instanceof PageRegion);
        // @phpstan-ignore-next-line parameterByRef.type
        $layout[] = $this->buildRegion($id, $draft_region->getComponentTree(), $model);
      }
      // Otherwise fall back to the currently live PageRegion config entity.
      // (Note: this automatically ignores auto-saves for PageRegions that were
      // editable at the time, but no longer are.)
      else {
        // @phpstan-ignore-next-line parameterByRef.type
        $layout[] = $this->buildRegion($id, $region->getComponentTree(), $model);
      }
    }
  }

  /**
   * PATCH request updates a single component instance in the auto-saved model and returns a preview.
   */
  public function patch(Request $request, FieldableEntityInterface|ContentTemplate $entity, ?ContentEntityInterface $preview_entity = NULL): PreviewEnvelope {
    assert(!$entity instanceof ContentTemplate || !is_null($preview_entity));
    $body = \json_decode($request->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
    if (!\array_key_exists('componentInstanceUuid', $body)) {
      throw new BadRequestHttpException('Missing componentInstanceUuid');
    }
    if (!\array_key_exists('componentType', $body)) {
      throw new BadRequestHttpException('Missing componentType');
    }
    if (!\array_key_exists('model', $body)) {
      throw new BadRequestHttpException('Missing model');
    }
    if (!\array_key_exists('autoSaves', $body)) {
      throw new BadRequestHttpException('Missing autoSaves');
    }
    if (!\array_key_exists('clientInstanceId', $body)) {
      throw new BadRequestHttpException('Missing clientInstanceId');
    }
    [
      'componentInstanceUuid' => $componentInstanceUuid,
      'componentType' => $componentTypeAndVersion,
      'model' => $model,
      'autoSaves' => $autoSaves,
      'clientInstanceId' => $clientInstanceId,
    ] = $body;

    if (!str_contains($componentTypeAndVersion, '@')) {
      throw new NotFoundHttpException(\sprintf('Missing version for component %s', $componentTypeAndVersion));
    }

    [$componentType, $version] = \explode('@', $componentTypeAndVersion);
    $component = $this->entityTypeManager->getStorage(Component::ENTITY_TYPE_ID)->load($componentType);
    \assert($component instanceof Component || $component === NULL);
    if ($component === NULL) {
      throw new NotFoundHttpException('No such component: ' . $componentType);
    }
    try {
      $component->loadVersion($version);
    }
    catch (\OutOfRangeException) {
      throw new NotFoundHttpException(\sprintf('No such version %s for component %s', $version, $componentType));
    }

    // @todo Currently ::validateAutoSaves() validates all page regions as well
    //   as `$entity` even though below we will only auto-save the entity
    //   containing the component, determine if here we should only validate
    //   that entity in https://drupal.org/i/3532056 or implement concurrent
    //   editing in https://drupal.org/i/3492065.
    $this->validateAutoSaves(array_merge([$entity], self::getEditableRegions()), $autoSaves, $clientInstanceId);

    // Determine which entity to PATCH.
    $entity = $this->getAutoSavedVersionIfAvailable([$entity])[$entity->id()];
    \assert($entity instanceof FieldableEntityInterface || $entity instanceof ContentTemplate);
    $regions = $this->getAutoSavedVersionIfAvailable(PageRegion::loadForActiveTheme());
    $entity_to_patch = $this->getEntityWithComponentInstance([$entity, ...$regions], $componentInstanceUuid);

    // Route-level access checks already verified `edit` access to $entity. Only
    // perform an additional `edit` access check if $entity_to_patch is not
    // $entity, but a PageRegion entity.
    if ($entity_to_patch instanceof PageRegion && !$entity_to_patch->access('edit')) {
      throw new AccessDeniedHttpException(sprintf('Access denied for region %s', $entity_to_patch->get('region')));
    }

    // Update the entity & auto-save it.
    $this->updateComponentInstance($entity_to_patch, $componentInstanceUuid, $model, $preview_entity);
    $this->autoSaveManager->saveEntity($entity_to_patch, $clientInstanceId);

    // Inform the UI of the updated reality.
    $data = $this->buildLayoutAndModel($entity, $regions, preview_entity: $preview_entity);
    assert(['layout', 'model'] === array_keys($data));
    if ($entity instanceof FieldableEntityInterface) {
      $data['entity_form_fields'] = $this->getFilteredEntityData($entity);
    }
    $data['autoSaves'] = $this->getAutoSaveHashes(array_merge([$entity], self::getEditableRegions()));
    return new PreviewEnvelope(
      $this->buildPreviewRenderable($entity, $preview_entity),
      additionalData: $data
    );
  }

  /**
   * POST request returns a preview, and updates the auto-saved layout, model and entity form fields.
   *
   * @todo Remove this in https://drupal.org/i/3492065
   */
  public function post(Request $request, FieldableEntityInterface|ContentTemplate $entity, ?ContentEntityInterface $preview_entity = NULL): PreviewEnvelope {
    assert(!$entity instanceof ContentTemplate || !is_null($preview_entity));
    $body = json_decode($request->getContent(), TRUE);
    if (!\array_key_exists('model', $body)) {
      throw new BadRequestHttpException('Missing model');
    }
    if (!\array_key_exists('layout', $body)) {
      throw new BadRequestHttpException('Missing layout');
    }
    if (!\array_key_exists('autoSaves', $body)) {
      throw new BadRequestHttpException('Missing autoSaves');
    }
    if (!\array_key_exists('clientInstanceId', $body)) {
      throw new BadRequestHttpException('Missing clientInstanceId');
    }
    [
      'layout' => $layout,
      'model' => $model,
      'autoSaves' => $autoSaves,
      'clientInstanceId' => $clientInstanceId,
    ] = $body;

    if ($entity instanceof FieldableEntityInterface) {
      if (!\array_key_exists('entity_form_fields', $body)) {
        throw new BadRequestHttpException('Missing entity_form_fields');
      }
      $entity_form_fields = $body['entity_form_fields'];
    }
    else {
      $entity_form_fields = NULL;
    }

    $this->validateAutoSaves(array_merge([$entity], self::getEditableRegions()), $autoSaves, $clientInstanceId);

    // Route-level access checks already verified `edit` access to $entity. But
    // any PageRegion entities present in the layout provided by the client
    // still need their `edit` access checked.
    $regions = PageRegion::loadForActiveThemeByClientSideId();
    $region_layouts = self::getRegionLayoutNodesKeyedByClientSideId($layout);
    \assert(\array_key_exists(CanvasPageVariant::MAIN_CONTENT_REGION, $region_layouts));
    // The main content region's component tree is for the edited entity.
    $main_content_layout = $region_layouts[CanvasPageVariant::MAIN_CONTENT_REGION];
    unset($region_layouts[CanvasPageVariant::MAIN_CONTENT_REGION]);
    $missing_regions = array_diff_key($region_layouts, $regions);
    if ($missing_regions) {
      throw new NotFoundHttpException('Unknown regions: ' . implode(', ', array_keys($missing_regions)));
    }
    foreach (array_keys($region_layouts) as $client_side_region_id) {
      // Check access to regions if any component was added or removed from them.
      if (!$regions[$client_side_region_id]->access('edit')) {
        throw new AccessDeniedHttpException(sprintf('Access denied for region %s', $client_side_region_id));
      }
    }

    // We want to work with the auto-save entity from this point so that any
    // previously saved values from e.g. another user are respected.
    $entity = $this->getAutoSavedVersionIfAvailable([$entity])[$entity->id()];
    $regions = $this->getAutoSavedVersionIfAvailable($regions);

    // Update the entity & auto-save it. This can update both:
    // - the component tree in the entity (using `layout` and `model`)
    // - the fields in the entity, if any (using `entity_form_fields`)
    $this->updateEntity($entity, $main_content_layout, $model, $entity_form_fields, $preview_entity);
    $this->autoSaveManager->saveEntity($entity, $clientInstanceId);

    // Update all PageRegions' component trees.
    foreach ($region_layouts as $client_side_region_id => $region_layout) {
      $regions[$client_side_region_id] = $regions[$client_side_region_id]->forAutoSaveData([
        'layout' => $region_layout['components'],
        'model' => self::extractModelForSubtree($region_layout, (array) $model),
      ], validate: FALSE);
      $this->autoSaveManager->saveEntity($regions[$client_side_region_id], $clientInstanceId);
    }

    return new PreviewEnvelope(
      $this->buildPreviewRenderable($entity, $preview_entity),
      additionalData: [
        'autoSaves' => $this->getAutoSaveHashes([$entity, ...self::getEditableRegions()]),
      ],
    );
  }

  private function buildPreviewRenderable(ContentTemplate|FieldableEntityInterface $entity, ?FieldableEntityInterface $preview_entity = NULL): array {
    $renderable = $entity instanceof ContentTemplate
      // @phpstan-ignore-next-line
      ? $entity->build($preview_entity, isPreview: TRUE)
      : $this->componentTreeLoader->load($entity)->toRenderable($entity, isPreview: TRUE);

    $build = [];
    if (isset($renderable[ComponentTreeItemList::ROOT_UUID])) {
      $build = $renderable[ComponentTreeItemList::ROOT_UUID];
    }

    $build['#prefix'] = !empty($build)
      ? Markup::create('<!-- canvas-region-start-content -->')
      : Markup::create('<!-- canvas-region-start-content --><div class="canvas--region-empty-placeholder"></div>');
    $build['#suffix'] = Markup::create('<!-- canvas-region-end-content -->');
    $build['#attached']['library'][] = 'canvas/preview';
    return $build;
  }

  public function getLabel((ContentEntityInterface&EntityPublishedInterface)|ContentTemplate $entity, ?ContentEntityInterface $preview_entity = NULL): string {
    if ($entity instanceof ContentTemplate) {
      assert($preview_entity !== NULL);
      return (string) $preview_entity->label();
    }
    // Get title from auto saved data if available.
    $autoSaveData = $this->autoSaveManager->getAutoSaveEntity($entity);
    if (!$autoSaveData->isEmpty()) {
      \assert($autoSaveData->entity instanceof EntityInterface);
      return (string) $autoSaveData->entity->label();
    }
    return (string) $entity->label();
  }

  private static function extractModelForSubtree(array $initial_layout_node, array $full_model): array {
    $node_model = [];
    if ($initial_layout_node['nodeType'] === 'component') {
      foreach ($initial_layout_node['slots'] as $slot) {
        $node_model = \array_merge($node_model, self::extractModelForSubtree($slot, $full_model));
      }
    }
    elseif ($initial_layout_node['nodeType'] === 'region' || $initial_layout_node['nodeType'] === 'slot') {
      foreach ($initial_layout_node['components'] as $component) {
        if (isset($full_model[$component['uuid']])) {
          $node_model[$component['uuid']] = $full_model[$component['uuid']];
        }
        $node_model = \array_merge($node_model, self::extractModelForSubtree($component, $full_model));
      }
    }
    return $node_model;
  }

  private function buildLayoutAndModel(FieldableEntityInterface|ContentTemplate $entity, array $regions, ?FieldableEntityInterface $preview_entity = NULL): array {
    $data = ['layout' => [], 'model' => []];
    // Build the content region.
    $tree = $this->componentTreeLoader->load($entity);
    $data['layout'] = [$this->buildRegion(CanvasPageVariant::MAIN_CONTENT_REGION, $tree, $data['model'], $preview_entity)];
    assert(is_array($data['model']));
    $this->addGlobalRegions($regions, $data['model'], $data['layout'], includeAllRegions: TRUE);
    $layout_keyed_by_region = array_combine(array_map(static fn($region) => $region['id'], $data['layout']), $data['layout']);
    // Reorder the layout to match theme order.
    $data['layout'] = array_values(array_replace(
      array_intersect_key(array_flip($this->regionsClientSideIds), $layout_keyed_by_region),
      $layout_keyed_by_region
    ));
    return $data;
  }

  /**
   * @return \Drupal\canvas\Entity\PageRegion[]
   *   The editable regions for the active theme.
   */
  private static function getEditableRegions(): array {
    return array_filter(PageRegion::loadForActiveTheme(), fn(PageRegion $region) => $region->access('update'));
  }

  /**
   * @param LayoutClientStructureArray $page_layout
   *   A complete page layout: for the "main content" region, plus PageRegions,
   *   if enabled.
   *
   * @return array<string, RegionClientStructureArray>
   *   Keys: client-side region IDs, values: the "region" layout node and its
   *   contents.
   */
  private static function getRegionLayoutNodesKeyedByClientSideId(array $page_layout): array {
    $keyed_region_nodes = [];
    foreach ($page_layout as $region_node) {
      assert($region_node['nodeType'] === 'region');
      $client_side_region_id = $region_node['id'];
      $keyed_region_nodes[$client_side_region_id] = $region_node;
    }
    return $keyed_region_nodes;
  }

  private function getAutoSavedVersionIfAvailable(array $entities): array {
    $result = [];
    foreach ($entities as $key => $stored_entity) {
      $autoSaveData = $this->autoSaveManager->getAutoSaveEntity($stored_entity);
      if (!$autoSaveData->isEmpty()) {
        \assert($autoSaveData->entity instanceof $stored_entity);
        $stored_entity = $autoSaveData->entity;
        // AutoSaveManager::getAutoSaveEntity calls ::create which makes the
        // entity appear new. There are some form widgets that check if the entity
        // is new when constructing their form element. The auto-save entity is
        // never new so we enforce that to avoid issues with form widgets.
        // @see \Drupal\path\Plugin\Field\FieldWidget\PathWidget::formElement
        $stored_entity->enforceIsNew(FALSE);
        // We also need to record the loaded revision ID as the auto-save manager
        // does not do this for us and some widgets make use of this information
        // to load a particular revision.
        // @see \Drupal\content_moderation\Plugin\Field\FieldWidget\ModerationStateWidget::formElement
        if ($stored_entity instanceof RevisionableInterface) {
          $stored_entity->updateLoadedRevisionId();
        }
      }
      // If keys are specified, use those (e.g. client-side IDs), otherwise re-
      // key by entity ID.
      $key = array_is_list($entities) ? $stored_entity->id() : $key;
      $result[$key] = $stored_entity;
    }
    return $result;
  }

  private function getEntityWithComponentInstance(array $entities, string $componentInstanceUuid): ComponentTreeEntityInterface|FieldableEntityInterface {
    foreach ($entities as $entity) {
      $tree = $this->componentTreeLoader->load($entity);
      if ($tree->getComponentTreeItemByUuid($componentInstanceUuid)) {
        return $entity;
      }
    }
    throw new NotFoundHttpException('No such component in model: ' . $componentInstanceUuid);
  }

  /**
   * Updates a single component instance in the given entity's component tree.
   *
   * @param \Drupal\canvas\Entity\ComponentTreeEntityInterface|FieldableEntityInterface $entity
   * @param string $componentInstanceUuid
   * @param array{source: SingleComponentInputArray, resolved: array<string, mixed>} $client_model
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $host_entity
   *
   * @return void
   */
  private function updateComponentInstance(ComponentTreeEntityInterface|FieldableEntityInterface $entity, string $componentInstanceUuid, array $client_model, ?FieldableEntityInterface $host_entity): void {
    $tree = $this->componentTreeLoader->load($entity);
    if ($item = $tree->getComponentTreeItemByUuid($componentInstanceUuid)) {
      $component = $item->getComponent();
      \assert($component instanceof Component);
      $item->setInput(
        $component->getComponentSource()->clientModelToInput(
          $componentInstanceUuid,
          $component,
          $client_model,
          $host_entity
        )
      );
      if ($entity instanceof ComponentTreeEntityInterface) {
        // This might be dangling item list so we should update explicitly.
        $entity->setComponentTree($tree->getValue());
      }
    }
  }

  /**
   * Updates the entire component tree in the given entity (+ fields if any).
   *
   * @param \Drupal\canvas\Entity\ContentTemplate|\Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity that is updated by reference: its fields (if any) and its
   *   component tree.
   * @param RegionClientStructureArray $layout
   * @param array<string, array{source: SingleComponentInputArray, resolved: array<string, mixed>}> $model
   * @param ?array $entity_form_fields
   *   Entity form fields. Required only if $entity is fieldable.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $preview_entity
   *   Preview entity. Required only if $entity is a ContentTemplates.
   */
  private function updateEntity(ContentTemplate|FieldableEntityInterface $entity, array $layout, array $model, ?array $entity_form_fields, ?FieldableEntityInterface $preview_entity): void {
    if ($entity instanceof FieldableEntityInterface) {
      \assert(!is_null($entity_form_fields));
      // If we are not auto-saving there is no reason to convert the
      // 'entity_form_fields'. This can cause access issue for just viewing the
      // preview. This runs the conversion as if the user had no access to edit
      // the entity fields which is all the that is necessary when not
      // auto-saving.
      $this->converter->convert([
        'layout' => $layout,
        'model' => $model,
        'entity_form_fields' => $entity_form_fields,
      ], $entity, validate: FALSE);
    }
    else {
      \assert(is_null($entity_form_fields));
      \assert(!is_null($preview_entity));
      // @todo Use \Drupal\canvas\ClientDataToEntityConverter here
      //   as well in https://drupal.org/i/3543197.
      // @todo Remove php-stan-ignore in https://drupal.org/i/3548273.
      // @phpstan-ignore-next-line argument.type
      $entity->setComponentTree(self::convertClientToServer($layout['components'], $model, $preview_entity, FALSE));
    }
  }

}
