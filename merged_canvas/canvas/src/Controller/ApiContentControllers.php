<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Resource\CanvasResourceLink;
use Drupal\canvas\Resource\CanvasResourceLinkCollection;
use Drupal\canvas\CanvasUriDefinitions;
use http\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * HTTP API for interacting with Canvas-eligible Content entity types.
 *
 * @internal This HTTP API is intended only for the Canvas UI. These controllers
 *   and associated routes may change at any time.
 *
 * @todo https://www.drupal.org/i/3498525 should generalize this to all eligible content entity types
 */
final class ApiContentControllers {

  /**
   * The maximum number of entity search results to return.
   */
  private const int MAX_SEARCH_RESULTS = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
    private readonly AutoSaveManager $autoSaveManager,
    private readonly SelectionPluginManagerInterface $selectionManager,
    private readonly RouteProviderInterface $routeProvider,
    private readonly LanguageManagerInterface $languageManager,
    #[Autowire(service: 'transliteration')]
    private readonly TransliterationInterface $transliteration,
  ) {}

  public function post(Request $request, string $entity_type): JsonResponse {
    // Get the request body content
    $content = $request->getContent();
    $body = json_decode($content, TRUE);
    $entity = NULL;

    // Try to load the entity instance.
    if (isset($body['entity_id'])) {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($body['entity_id']);
      if (!$entity instanceof ContentEntityInterface || !$entity->access('view')) {
        return new JsonResponse(['error' => 'Cannot find entity to duplicate.'], Response::HTTP_NOT_FOUND);
      }
    }

    // If entity is provided, duplicate it, otherwise create a new entity.
    if ($entity) {
      $new = $this->duplicate($entity);
    }
    else {
      // Note: this intentionally does not catch content entity type storage
      // handler exceptions: the generic Canvas API exception subscriber handles them.
      // @see \Drupal\canvas\EventSubscriber\ApiExceptionSubscriber
      $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type);
      $new = $this->entityTypeManager->getStorage($entity_type)->create([
        'title' => static::defaultTitle($entity_type_definition),
        'status' => FALSE,
      ]);
      $new->save();
    }

    return new JsonResponse([
      'entity_type' => $entity_type,
      'entity_id' => $new->id(),
    ], RESPONSE::HTTP_CREATED);
  }

  /**
   * Deletes entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $canvas_page
   *   Entity to delete.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(ContentEntityInterface $canvas_page): JsonResponse {
    $canvas_page->delete();
    return new JsonResponse(status: Response::HTTP_NO_CONTENT);
  }

  /**
   * Returns a list of Canvas Page content entities, with only high-level metadata.
   *
   * TRICKY: there are reasons Canvas has its own internal HTTP API rather than
   * using Drupal core's JSON:API. As soon as this method is updated to return
   * all fields instead of just high-level metadata, those reasons may start to
   * outweigh the downsides of adding a dependency on JSON:API.
   *
   * @see https://www.drupal.org/project/canvas/issues/3500052#comment-15966496
   */
  public function list(string $entity_type, Request $request): CacheableJsonResponse {
    if ($entity_type !== Page::ENTITY_TYPE_ID) {
      throw new BadRequestHttpException('Only the `canvas_page` content entity type is supported right now, will be generalized in a child issue of https://www.drupal.org/project/canvas/issues/3498525.');
    }
    $storage = $this->entityTypeManager->getStorage($entity_type);

    $query_cacheability = (new CacheableMetadata())
      ->addCacheContexts($storage->getEntityType()->getListCacheContexts())
      ->addCacheTags($storage->getEntityType()->getListCacheTags());

    // Prepare search term and determine if we're performing a search
    $search = $request->query->get('search', default: NULL);
    $query_cacheability->addCacheContexts(['url.query_args:search']);

    // Get the (ordered) list of content entity IDs to load, either:
    // - without a search term: get the N newest content entities
    if ($search === NULL) {
      $content_entity_type = $this->entityTypeManager->getDefinition($entity_type);
      assert($content_entity_type instanceof ContentEntityTypeInterface);
      $revision_created_field_name = $content_entity_type->getRevisionMetadataKey('revision_created');
      // @todo Ensure this is one of the required characteristics in https://www.drupal.org/project/canvas/issues/3498525.
      assert(is_string($revision_created_field_name));

      $entity_query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->sort($revision_created_field_name, direction: 'DESC')
        ->range(0, self::MAX_SEARCH_RESULTS);

      $ids = $this->executeQueryInRenderContext($entity_query, $query_cacheability);
    }
    // - with a search term: get the N best matches using the entity reference
    //   selection plugin, get all auto-save matches, and combine both
    else {
      assert(is_string($search));
      $search = trim($search);
      $ids = $this->filterAndMergeIds(
        // TRICKY: covered by the "list cacheability" at the top.
        $this->getMatchingStoredEntityIds($entity_type, $search),
        $this->getMatchingAutoSavedEntityIds($entity_type, $search, $query_cacheability)
      );
    }

    /** @var \Drupal\Core\Entity\EntityPublishedInterface[] $content_entities */
    $content_entities = $storage->loadMultiple($ids);
    $content_list = [];
    foreach ($content_entities as $id => $content_entity) {
      $content_list[$id] = $this->normalize($content_entity, $query_cacheability);
    }

    $json_response = new CacheableJsonResponse($content_list);
    // @todo add cache contexts for query params when introducing pagination in https://www.drupal.org/i/3502691.
    $json_response->addCacheableDependency($query_cacheability);

    return $json_response;
  }

  /**
   * Normalizes content entity.
   *
   * @param \Drupal\Core\Entity\EntityPublishedInterface $content_entity
   *   The content entity to prepare data for.
   * @param \Drupal\Core\Cache\CacheableMetadata $url_cacheability
   *   The cacheability metadata object to add URL dependencies to.
   *
   * @return array
   *   An associative array containing the normalized entity.
   */
  private function normalize(EntityPublishedInterface $content_entity, CacheableMetadata $url_cacheability): array {
    $generated_url = $content_entity->toUrl()->toString(TRUE);

    $autoSaveData = $this->autoSaveManager->getAutoSaveEntity($content_entity);
    // Expose available entity operations.
    $linkCollection = $this->getEntityOperations($content_entity);
    $autoSaveEntity = $autoSaveData->isEmpty() ? NULL : $autoSaveData->entity;

    // @todo Dynamically use the entity 'path' key to determine which field is
    //   the path in https://drupal.org/i/3503446.
    $autoSavePath = NULL;
    if ($autoSaveEntity instanceof FieldableEntityInterface && $autoSaveEntity->hasField('path')) {
      $autoSavePath = $autoSaveEntity->get('path')->first()?->getValue()['alias'] ?? \sprintf('/%s', \ltrim($autoSaveEntity->toUrl()->getInternalPath(), '/'));
    }

    $url_cacheability->addCacheableDependency($generated_url)
      ->addCacheableDependency($linkCollection)
      ->addCacheableDependency($autoSaveData);

    return [
      'id' => (int) $content_entity->id(),
      'title' => $content_entity->label(),
      'status' => $content_entity->isPublished(),
      // The processed path, which has gone through outbound path processors. It
      // may not be the same as the entity's canonical link template.
      'path' => $generated_url->getGeneratedUrl(),
      // The internal path, which has not been processed and is the entity's
      // canonical link template.
      'internalPath' => '/' . $content_entity->toUrl()->getInternalPath(),
      'autoSaveLabel' => $autoSaveEntity?->label(),
      'autoSavePath' => $autoSavePath,
      // @see https://jsonapi.org/format/#document-links
      'links' => $linkCollection->asArray(),
    ];
  }

  /**
   * Gets N first saved ("live") entity IDs matching the search term.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $search
   *   The (transliterated) search term to match against entities.
   *
   * @return array
   *   An array of entity IDs that match the search term.
   */
  private function getMatchingStoredEntityIds(string $entity_type_id, string $search): array {
    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $selection_handler */
    $selection_handler = $this->selectionManager->getInstance([
      'target_type' => $entity_type_id,
      'handler' => 'default',
    ]);
    assert($selection_handler instanceof SelectionInterface);
    $matching_data = $selection_handler->getReferenceableEntities(
      $search,
      'CONTAINS',
      self::MAX_SEARCH_RESULTS
    );

    return array_keys(NestedArray::mergeDeepArray($matching_data, TRUE));
  }

  /**
   * Gets N first auto-saved ("draft") entity IDs matching the search term.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $search
   *   The search term to match against entities.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The cacheability of the given query, to be refined to match the
   *   refinements made to the query.
   *
   * @return array
   *   An array of entity IDs that match the search criteria.
   */
  private function getMatchingAutoSavedEntityIds(string $entity_type_id, string $search, RefinableCacheableDependencyInterface $cacheability): array {
    $cacheability->addCacheTags([AutoSaveManager::CACHE_TAG]);
    $auto_saved_entities_of_type = \array_filter($this->autoSaveManager->getAllAutoSaveList(TRUE), static fn (array $entry): bool => $entry['entity_type'] === $entity_type_id);

    // Transliterate the search term using the negotiated content language.
    $cacheability->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $transliterated_search = $this->transliteration->transliterate(mb_strtolower($search), $langcode);

    // Check if the transliterated search term is contained by any of the auto-
    // saved entities of this type.
    $matching_unsaved_ids = [];
    foreach ($auto_saved_entities_of_type as ['entity' => $entity]) {
      \assert($entity instanceof EntityInterface);
      $transliterated_label = $this->transliteration->transliterate(mb_strtolower((string) $entity->label()), $langcode);
      if (str_contains($transliterated_label, $transliterated_search)) {
        $matching_unsaved_ids[] = $entity->id();
      }
    }

    return $matching_unsaved_ids;
  }

  /**
   * Filters and merges entity IDs based on search results.
   *
   * @param array $matching_ids
   *   The array of entity IDs that match the search term.
   * @param array $matching_unsaved_ids
   *   The array of unsaved entity IDs that match the search term.
   *
   * @return array
   *   The filtered and merged array of entity IDs.
   */
  private static function filterAndMergeIds(array $matching_ids, array $matching_unsaved_ids): array {
    // Sort by newest first (keys will be numeric IDs) and limit to max results
    $ids = array_unique(array_merge($matching_ids, $matching_unsaved_ids));
    arsort($ids);
    $ids = array_slice($ids, 0, self::MAX_SEARCH_RESULTS, TRUE);
    return $ids;
  }

  /**
   * Duplicates entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to duplicate.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Newly created entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function duplicate(ContentEntityInterface $entity): ContentEntityInterface {
    $duplicate = $entity->createDuplicate();

    // Get temp data of original entity.
    if ($entity = $this->autoSaveManager->getAutoSaveEntity($entity)->entity) {
      // Before merging temp data remove path value to avoid collision.
      // @todo Remove hardcoded field name when https://www.drupal.org/project/canvas/issues/3503446 lands.
      $duplicate = $entity->createDuplicate();
      \assert($duplicate instanceof ContentEntityInterface);
    }

    // Update title and status.
    $entity_type = $duplicate->getEntityType();
    $entity_key = $entity_type->getKey('label') ?? 'title';
    // @phpstan-ignore-next-line
    $duplicate->set($entity_key, $duplicate->label() . AutoSaveManager::ENTITY_DUPLICATE_SUFFIX);
    assert($duplicate instanceof EntityPublishedInterface);
    $duplicate->setUnpublished();
    $duplicate->save();

    // Delete temp data for the duplicate, it should not have it at this point.
    // Everything is saved.
    $this->autoSaveManager->delete($duplicate);

    return $duplicate;
  }

  /**
   * Executes the query in a render context, to catch bubbled cacheability.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to execute to get the return results.
   * @param \Drupal\Core\Cache\CacheableMetadata $query_cacheability
   *   The value object to carry the query cacheability.
   *
   * @return array
   *   Returns IDs of entities.
   *
   * @see \Drupal\jsonapi\Controller\EntityResource::executeQueryInRenderContext()
   */
  private function executeQueryInRenderContext(QueryInterface $query, CacheableMetadata $query_cacheability) : array {
    $context = new RenderContext();
    $results = $this->renderer->executeInRenderContext($context, function () use ($query) {
      return $query->execute();
    });
    if (!$context->isEmpty()) {
      $query_cacheability->addCacheableDependency($context->pop());
    }
    return $results;
  }

  public static function defaultTitle(EntityTypeInterface $entity_type): TranslatableMarkup {
    return new TranslatableMarkup('Untitled @singular_entity_type_label', ['@singular_entity_type_label' => $entity_type->getSingularLabel()]);
  }

  public function getEntityOperations(EntityPublishedInterface $content_entity): CanvasResourceLinkCollection {
    $links = new CanvasResourceLinkCollection([]);
    // Link relation type => route name.
    $possible_operations = [
      CanvasUriDefinitions::LINK_REL_DELETE => ['route_name' => 'canvas.api.content.delete', 'op' => 'delete'],
      CanvasUriDefinitions::LINK_REL_EDIT => ['route_name' => 'canvas.boot.entity', 'op' => 'update'],
      // Setting the homepage is a staged configuration update, the UI will
      // call `canvas.api.config.post` but for the access check
      // use the content entity's access.
      // Conceptually, this is an operation on the content entity, so expose it
      // as a non-standard link operation.
      CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => ['route_name' => 'canvas.boot.entity', 'op' => 'update'],
      CanvasUriDefinitions::LINK_REL_DUPLICATE => ['route_name' => 'canvas.api.content.create', 'op' => 'create'],
    ];
    foreach ($possible_operations as $link_rel => ['route_name' => $route_name, 'op' => $entity_operation]) {
      $access = $content_entity->access(operation: $entity_operation, return_as_object: TRUE);
      if ($entity_operation === 'create') {
        $access = $this->entityTypeManager->getAccessControlHandler($content_entity->getEntityTypeId())
          ->createAccess(entity_bundle: $content_entity->bundle(), return_as_object: TRUE);
      }
      assert($access instanceof AccessResult);
      if ($access->isAllowed()) {
        $links = $links->withLink(
          $link_rel,
          new CanvasResourceLink($access, $this->getUrlFromRoute($content_entity, $route_name), $link_rel)
        );
      }
      else {
        $links->addCacheableDependency($access);
      }
    }
    return $links;
  }

  /**
   * Gets the url for an operation route given the content entity.
   *
   * Ideally, we would have standardized routes, and we wouldn't need a helper,
   * nor to compile the routes.
   * This might be achievable when we complete https://www.drupal.org/i/3498525.
   */
  private function getUrlFromRoute(EntityInterface $content_entity, string $route_name): Url {
    // @todo https://www.drupal.org/i/3498525 should standardize the
    //   route params. We need this helper for now.
    $match = fn($param) => match($param) {
      'entity_type' => $content_entity->getEntityTypeId(),
      'entity' => $content_entity->id(),
      $content_entity->getEntityTypeId() => $content_entity->id(),
      default => throw new InvalidArgumentException('We cannot map this route parameter'),
    };
    $route = $this->routeProvider->getRouteByName($route_name);
    $route_parameters = $route->compile()->getVariables();
    $params = [];
    foreach ($route_parameters as $param) {
      $params[$param] = $match($param);
    }
    return Url::fromRoute($route_name, $params);
  }

}
