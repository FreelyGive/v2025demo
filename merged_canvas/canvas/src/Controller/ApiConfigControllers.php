<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\canvas\AssetRenderer;
use Drupal\canvas\ClientSideRepresentation;
use Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface;
use Drupal\canvas\EntityHandlers\VisibleWhenDisabledCanvasConfigEntityAccessControlHandler;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Controllers exposing HTTP API for interacting with Canvas's Config entity types.
 *
 * @internal This HTTP API is intended only for the Canvas UI. These controllers
 *   and associated routes may change at any time.
 *
 * @see \Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface
 * @see \Drupal\canvas\ClientSideRepresentation
 */
final class ApiConfigControllers extends ApiControllerBase {

  use ComponentTreeItemListInstantiatorTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly RendererInterface $renderer,
    private readonly AssetRenderer $assetRenderer,
    #[Autowire(param: 'renderer.config')]
    private readonly array $rendererConfig,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly AccessManagerInterface $accessManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Returns a list of enabled Canvas config entities in client representation.
   *
   * This controller provides a critical response for the Canvas UI. Therefore it
   * should hence be as fast and cacheable as possible. High-cardinality cache
   * contexts (such as 'user' and 'session') result in poor cacheability.
   * Fortunately, these cache contexts only are present for the markup used for
   * previewing Canvas Components. So Canvas chooses to sacrifice accuracy of the
   * preview slightly to be able to guarantee strong cacheability and fast
   * responses.
   */
  public function list(string $canvas_config_entity_type_id): CacheableJsonResponse {
    $canvas_config_entity_type = $this->entityTypeManager->getDefinition($canvas_config_entity_type_id);
    assert($canvas_config_entity_type instanceof ConfigEntityTypeInterface);

    // Load the queried config entities: a list of all of them.
    $storage = $this->entityTypeManager->getStorage($canvas_config_entity_type_id);
    $query = $storage->getQuery()->accessCheck(TRUE);
    // Load only enabled Canvas config entities if the Canvas config entity type:
    // - specifies the `status` property as a lookup key
    // - does not use the special "visible when disabled" access control handler
    if (in_array('status', $canvas_config_entity_type->getLookupKeys(), TRUE) && $canvas_config_entity_type->getHandlerClass('access') !== VisibleWhenDisabledCanvasConfigEntityAccessControlHandler::class) {
      $query->condition('status', TRUE);
    }

    // If the Canvas config entity type has a weight, sort by it.
    if ($canvas_config_entity_type->hasKey('weight')) {
      $query->sort('weight');
    }

    $query_cacheability = (new CacheableMetadata())
      ->addCacheContexts($canvas_config_entity_type->getListCacheContexts())
      ->addCacheTags($canvas_config_entity_type->getListCacheTags());
    $canvas_config_entity_type->getClass()::refineListQuery($query, $query_cacheability);
    /** @var array<\Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface> $config_entities */
    $config_entities = $storage->loadMultiple($query->execute());
    // As config entities do not use sql-storage, we need explicit access check
    // per https://www.drupal.org/node/3201242.
    $access_cacheability = new CacheableMetadata();
    $config_entities = array_filter($config_entities, function (CanvasHttpApiEligibleConfigEntityInterface $config_entity) use ($access_cacheability): bool {
      $access = $config_entity->access('view', return_as_object: TRUE);
      $access_cacheability->addCacheableDependency($config_entity);
      return $access->isAllowed();
    });

    $normalizations = [];
    $normalizations_cacheability = new CacheableMetadata();
    foreach ($config_entities as $key => &$entity) {
      $representation = $this->normalize($entity);
      $normalizations[$key] = $representation->values;
      $normalizations_cacheability->addCacheableDependency($representation);
    }

    // Set a minimum cache time of one hour, because this is only a preview.
    // (Cache tag invalidations will still result in an immediate update.)
    $max_age = $normalizations_cacheability->getCacheMaxAge();
    if ($max_age !== Cache::PERMANENT) {
      $normalizations_cacheability->setCacheMaxAge(max($max_age, 3600));
    }

    // Ignore the cache tags for individual Canvas config entities, because this
    // response lists them, so the list cache tag is sufficient and the rest is
    // pointless noise.
    // @see \Drupal\Core\Entity\EntityTypeInterface::getListCacheTags()
    $total_cacheability = (new CacheableMetadata())
      ->addCacheableDependency($query_cacheability)
      ->addCacheableDependency($access_cacheability)
      ->addCacheableDependency($normalizations_cacheability);
    $total_cacheability->setCacheTags(array_filter(
      $total_cacheability->getCacheTags(),
      fn (string $tag): bool =>
        // Support both Canvas config entity types provided by the main Canvas module…
        !str_starts_with($tag, 'config:canvas.' . $canvas_config_entity_type_id)
        // … and by optional submodules.
        && !str_starts_with($tag, 'config:canvas_personalization.' . $canvas_config_entity_type_id),
    ));

    return (new CacheableJsonResponse($normalizations))
      ->addCacheableDependency($total_cacheability);
  }

  /**
   * Transforms ::list() result for the ContentTemplate listing.
   */
  public function listContentTemplatesAsHierarchy(): CacheableJsonResponse {
    // Reuse the default ::list(), and transform the result from a flat list to
    // a hierarchy, with labels for each level.
    $response = $this->list(ContentTemplate::ENTITY_TYPE_ID);
    // @phpstan-ignore-next-line argument.type
    $flat_json = json_decode($response->getContent(), TRUE);

    // @todo Generalize beyond nodes in https://www.drupal.org/i/3498525
    $supported_entity_type_ids = ['node'];
    $individual_bundle_entity_cache_tag_prefixes_to_ignore = [];

    // 1. Create the hierarchy:
    // - all supported content entity types with their bundle collection label
    // - all bundles for each supported content entity type with their label
    $hierarchical_json = [];
    $additional_cacheability = new CacheableMetadata();
    // Update whenever the list of entity types changes, not just when the set
    // of ContentTemplates config entities changes.
    // @todo Uncomment in https://www.drupal.org/i/3498525
    // $additional_cacheability->addCacheTags(['entity_types']);
    // Update whenever the list of bundles changes. Bundles may be defined via
    // hook_entity_bundle_info()  or via a bundle entity type.
    // @see \Drupal\Core\Entity\EntityTypeBundleInfo::getAllBundleInfo()
    $additional_cacheability->addCacheTags(['entity_bundles']);
    foreach ($supported_entity_type_ids as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      assert($entity_type instanceof EntityTypeInterface);
      $bundle_entity_type_id = $entity_type->getBundleEntityType();

      $label = $entity_type->getCollectionLabel();
      if ($bundle_entity_type_id) {
        $bundle_entity_type = $this->entityTypeManager->getDefinition($bundle_entity_type_id);
        assert($bundle_entity_type instanceof EntityTypeInterface);
        $label = $bundle_entity_type->getCollectionLabel();
      }
      $bundle_info = $this->bundleInfo->getBundleInfo($entity_type_id);
      $bundles = [];
      foreach ($bundle_info as $bundle_key => $info) {
        /** @var \Drupal\Core\Access\AccessResultInterface $edit_fields_access_check */
        $edit_fields_access_check = $this->accessManager->checkNamedRoute("entity.$entity_type_id.field_ui_fields", [$bundle_entity_type_id => $bundle_key], $this->currentUser, TRUE);
        assert($edit_fields_access_check instanceof AccessResultInterface);
        $edit_fields_access = $edit_fields_access_check->isAllowed();
        /** @var \Drupal\Core\Access\AccessResultInterface $delete_access_check */
        $delete_access_check = $this->accessManager->checkNamedRoute("entity.$bundle_entity_type_id.delete_form", [$bundle_entity_type_id => $bundle_key], $this->currentUser, TRUE);
        assert($delete_access_check instanceof AccessResultInterface);
        $delete_access = $delete_access_check->isAllowed();

        $bundles[$bundle_key] = [
          'label' => $info['label'],
          'viewModes' => [],
        ];
        if ($delete_access) {
          $delete_url = Url::fromRoute("entity.$bundle_entity_type_id.delete_form", [$bundle_entity_type_id => $bundle_key])
            ->toString();
          $bundles[$bundle_key]['deleteUrl'] = $delete_url;
        }
        else {
          $bundles[$bundle_key]['deleteUrl'] = NULL;
        }
        if ($edit_fields_access) {
          $edit_fields_url = Url::fromRoute("entity.$entity_type_id.field_ui_fields", [$bundle_entity_type_id => $bundle_key])
            ->toString();
          $bundles[$bundle_key]['editFieldsUrl'] = $edit_fields_url;
        }
        else {
          $bundles[$bundle_key]['editFieldsUrl'] = NULL;
        }

        $additional_cacheability
          ->addCacheableDependency($delete_access_check)
          ->addCacheableDependency($edit_fields_access_check);
      }

      $hierarchical_json[$entity_type_id] = [
        'label' => $label,
        'bundles' => $bundles,
      ];
      // For bundleless content entity types, omit the label.
      // For example: the `User` content entity type does not have any bundles,
      // so listing "User" twice as the label is pointless.
      if (array_keys($hierarchical_json[$entity_type_id]['bundles']) === [$entity_type_id]) {
        $hierarchical_json[$entity_type_id]['bundles'][$entity_type_id]['label'] = NULL;
      }
      if ($bundle_entity_type_id) {
        // @phpstan-ignore-next-line variable.undefined
        assert($bundle_entity_type instanceof ConfigEntityTypeInterface);
        $additional_cacheability->addCacheTags($bundle_entity_type->getListCacheTags());
        $individual_bundle_entity_cache_tag_prefixes_to_ignore[] = sprintf("config:%s.", $bundle_entity_type->getConfigPrefix());
      }
    }

    // 2. Populate the hierarchy, which makes the nested `viewModes` key no
    // longer contain the empty array, for those that have ContentTemplates.
    foreach ($flat_json as $template_id => $normalization) {
      // Place the original normalization in its place in the hierarchy;
      // determine hierarchy using the ID.
      // @see \Drupal\canvas\Entity\ContentTemplate::id()
      [$entity_type_id, $bundle, $view_mode] = explode('.', $template_id);
      $hierarchical_json[$entity_type_id]['bundles'][$bundle]['viewModes'][$view_mode] = $normalization;
    }

    // Ignore the cache tags for individual bundle config entities (surfaced by
    // access checks on their "edit fields" and "delete" URLs), because this
    // response lists them, so the list cache tag is sufficient and the rest is
    // pointless noise.
    // @see \Drupal\Core\Entity\EntityTypeInterface::getListCacheTags()
    $additional_cacheability->setCacheTags(array_filter(
      $additional_cacheability->getCacheTags(),
      function (string $tag) use ($individual_bundle_entity_cache_tag_prefixes_to_ignore) {
        foreach ($individual_bundle_entity_cache_tag_prefixes_to_ignore as $prefix) {
          return !str_starts_with($tag, $prefix);
        }
        return FALSE;
      }
    ));

    // Overwrite data, and add to the already existing cacheability.
    return $response->setData($hierarchical_json)
      ->addCacheableDependency($additional_cacheability);
  }

  public function get(Request $request, CanvasHttpApiEligibleConfigEntityInterface $canvas_config_entity): CacheableJsonResponse {
    $canvas_config_entity_type = $canvas_config_entity->getEntityType();
    assert($canvas_config_entity_type instanceof ConfigEntityTypeInterface);
    $representation = $this->normalize($canvas_config_entity);
    return (new CacheableJsonResponse(status: 200, data: $representation->values))
      ->addCacheableDependency($canvas_config_entity)
      ->addCacheableDependency($representation);
  }

  public function post(string $canvas_config_entity_type_id, Request $request): JsonResponse {
    $canvas_config_entity_type = $this->entityTypeManager->getDefinition($canvas_config_entity_type_id);
    assert($canvas_config_entity_type instanceof ConfigEntityTypeInterface);

    // Create an in-memory config entity.
    $decoded = self::decode($request);
    try {
      $canvas_config_entity = $canvas_config_entity_type->getClass()::createFromClientSide($decoded);
      assert($canvas_config_entity instanceof CanvasHttpApiEligibleConfigEntityInterface);
      $this->validate($canvas_config_entity);
    }
    catch (ConstraintViolationException $e) {
      throw $e->renamePropertyPaths([
        'component_tree.inputs' => 'model',
        'component_tree' => 'layout',
      ]);
    }

    // Save the Canvas config entity, respond with a 201 if success. Else 409.
    try {
      $canvas_config_entity->save();
    }
    catch (EntityStorageException $e) {
      throw new ConflictHttpException($e->getMessage());
    }

    $representation = $this->normalize($canvas_config_entity);
    return new JsonResponse(status: 201, data: $representation->values, headers: [
      'Location' => Url::fromRoute(
        'canvas.api.config.get',
        [
          'canvas_config_entity_type_id' => $canvas_config_entity->getEntityTypeId(),
          'canvas_config_entity' => $canvas_config_entity->id(),
        ])
        ->toString(TRUE)
        ->getGeneratedUrl(),
    ]);
  }

  public function delete(CanvasHttpApiEligibleConfigEntityInterface $canvas_config_entity): JsonResponse {
    // @todo First validate that there is no other entity depending on this. If there is, respond with a 400, 409, 412 or 422 (TBD).
    // @todo Permissions take into account config dependencies, but we might have content dependencies depending on it too. See https://www.drupal.org/project/canvas/issues/3516839
    // @see https://www.drupal.org/project/drupal/issues/3423459
    $canvas_config_entity->delete();
    return new JsonResponse(status: 204, data: NULL);
  }

  public function patch(Request $request, CanvasHttpApiEligibleConfigEntityInterface $canvas_config_entity): JsonResponse {
    $decoded = self::decode($request);
    $canvas_config_entity->updateFromClientSide($decoded);
    try {
      $this->validate($canvas_config_entity);
    }
    catch (ConstraintViolationException $e) {
      throw $e->renamePropertyPaths([
        'component_tree.inputs' => 'model',
        'component_tree' => 'layout',
      ]);
    }

    // Save the Canvas config entity, respond with a 200.
    $canvas_config_entity->save();
    $canvas_config_entity_type = $canvas_config_entity->getEntityType();
    assert($canvas_config_entity_type instanceof ConfigEntityTypeInterface);
    $representation = $this->normalize($canvas_config_entity);
    return new JsonResponse(status: 200, data: $representation->values);
  }

  private function validate(CanvasHttpApiEligibleConfigEntityInterface $canvas_config_entity): void {
    $violations = $canvas_config_entity->getTypedData()->validate();
    if ($violations->count()) {
      throw new ConstraintViolationException($violations);
    }
  }

  /**
   * Normalizes this config entity, ensuring strong cacheability.
   *
   * Strong cacheability is "ensured" by accepting imperfect previews, when
   * those previews are highly dynamic.
   */
  private function normalize(CanvasHttpApiEligibleConfigEntityInterface $entity): ClientSideRepresentation {
    // TRICKY: some components may (erroneously!) bubble cacheability even
    // when just constructing a render array. For maximum ecosystem
    // compatibility, account for this, and catch the bubbled cacheability.
    // @see \Drupal\views\Plugin\Block\ViewsBlock::build()
    $get_representation = function (CanvasHttpApiEligibleConfigEntityInterface $entity): ClientSideRepresentation {
      $context = new RenderContext();
      $representation = $this->renderer->executeInRenderContext(
        $context,
        fn () => $entity->normalizeForClientSide()->renderPreviewIfAny($this->renderer, $this->assetRenderer),
      );
      assert($representation instanceof ClientSideRepresentation);
      if (!$context->isEmpty()) {
        $leaked_cacheability = $context->pop();
        $representation->addCacheableDependency($leaked_cacheability);
      }
      return $representation;
    };

    $representation = $get_representation($entity);

    // Use core's `renderer.config` container parameter to determine which cache
    // contexts are considered poorly cacheable.
    $problematic_cache_contexts = array_intersect(
      $representation->getCacheContexts(),
      $this->rendererConfig['auto_placeholder_conditions']['contexts']
    );

    // If problematic cache contexts are present or if the markup is empty,
    // attempt to re-render in a way that the Component preview is strongly
    // cacheable while still sufficiently accurate.
    if (!empty($problematic_cache_contexts) || empty($representation->values['default_markup'])) {
      $ignorable_cache_contexts = ['session', 'user'];

      if (array_diff($problematic_cache_contexts, $ignorable_cache_contexts)) {
        throw new \LogicException(sprintf('No PHP API exists yet to allow specifying a technique to avoid the `%s` cache context(s) while still generating an acceptable preview', implode(',', $problematic_cache_contexts)));
      }

      try {
        $this->accountSwitcher->switchTo(new AnonymousUserSession());
        $representation = $get_representation($entity);
        // Ignore these cache contexts if they still exist, because it's been
        // re-rendered as the anonymous user. If they still exist, they are safe
        // to ignore for preview purposes.
        $representation->removeCacheContexts($ignorable_cache_contexts);
      }
      finally {
        $this->accountSwitcher->switchBack();
      }
    }

    return $representation;
  }

}
