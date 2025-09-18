<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase;
use Drupal\canvas\PropExpressions\Component\ComponentPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;
use Drupal\canvas\PropSource\DynamicPropSource;
use Drupal\canvas\ShapeMatcher\FieldForComponentSuggester;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controllers exposing HTTP API for powering Canvas's Content Template editor UI.
 *
 * @internal This HTTP API is intended only for the Canvas UI. These controllers
 *   and associated routes may change at any time.
 *
 * @see \Drupal\canvas\ShapeMatcher\FieldForComponentSuggester
 */
final class ApiUiContentTemplateControllers extends ApiControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    private readonly FieldForComponentSuggester $fieldForComponentSuggester,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {}

  /**
   * Provides suggestions for a given Component based on entity type and bundle.
   *
   * @param string $content_entity_type_id
   *   A content entity type ID.
   * @param string $bundle
   *   A bundle of the given content entity type.
   * @param string $component_config_entity_id
   *   A Component config entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the suggestions for the component.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function suggestStructuredDataForPropShapes(string $content_entity_type_id, string $bundle, string $component_config_entity_id): JsonResponse {
    // @see \Drupal\Core\EventSubscriber\ExceptionJsonSubscriber
    $this->validateRequest($content_entity_type_id, $bundle, $component_config_entity_id);
    // @phpstan-ignore-next-line
    $source = Component::load($component_config_entity_id)->getComponentSource();
    assert($source instanceof GeneratedFieldExplicitInputUxComponentSourceBase);

    $suggestions = $this->fieldForComponentSuggester->suggest(
      $source->getSourceSpecificComponentId(),
      $source->getMetadata(),
      EntityDataDefinition::createFromDataType("entity:$content_entity_type_id:$bundle"),
    );

    return new JsonResponse(status: Response::HTTP_OK, data: array_combine(
      // Top-level keys: the prop names of the targeted component.
      array_map(
        fn (string $key): string => ComponentPropExpression::fromString($key)->propName,
        array_keys($suggestions),
      ),
      array_map(
        fn (array $instances): array => array_combine(
          // Second level keys: opaque identifiers for the suggestions to
          // populate the component prop.
          array_map(
            fn (StructuredDataPropExpressionInterface $expr): string => \hash('xxh64', (string) $expr),
            array_values($instances),
          ),
          // Values: objects with "label" and "source" keys, with:
          // - "label": the human-readable label that the Content Template UI
          //   should present to the human
          // - "source": the array representation of the DynamicPropSource that,
          //   if selected by the human, the client should use verbatim as the
          //   source to populate this component instance's prop.
          array_map(
            function (string $label, StructuredDataPropExpressionInterface $expr) {
              return [
                'label' => $label,
                'source' => (new DynamicPropSource($expr))->toArray(),
              ];
            },
            array_keys($instances),
            array_values($instances),
          ),
        ),
        array_column($suggestions, 'instances'),
      ),
    ));
  }

  /**
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  private function validateRequest(string $content_entity_type_id, string $bundle, string $component_config_entity_id): void {
    $component = Component::load($component_config_entity_id);
    if (NULL === $component) {
      throw new NotFoundHttpException("The component $component_config_entity_id does not exist.");
    }

    $source = $component->getComponentSource();
    if (!$source instanceof GeneratedFieldExplicitInputUxComponentSourceBase) {
      throw new BadRequestHttpException('Only components that define their inputs using JSON Schema and use fields to populate their inputs are currently supported.');
    }

    if ($this->entityTypeManager->getDefinition($content_entity_type_id, FALSE) === NULL) {
      throw new NotFoundHttpException(sprintf("The `%s` content entity type does not exist.", $content_entity_type_id));
    }

    if (!array_key_exists($bundle, $this->entityTypeBundleInfo->getBundleInfo($content_entity_type_id))) {
      throw new NotFoundHttpException(sprintf("The `%s` content entity type does not have a `%s` bundle.", $content_entity_type_id, $bundle));
    }
  }

  public function suggestPreviewContentEntities(string $entity_type_id, string $bundle): CacheableJsonResponse {
    $entity_query = ContentTemplate::getPreviewSuggestionQuery(
      $entity_type_id,
      $bundle,
      10,
    );

    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);

    $entity_ids = $entity_query->execute();
    assert(is_array($entity_ids));
    $entity_query_cacheability = (new CacheableMetadata())
      ->addCacheTags($entity_definition->getBundleListCacheTags($bundle))
      ->addCacheContexts($entity_storage->getEntityType()->getListCacheContexts());

    $entities = $entity_storage->loadMultiple($entity_ids);

    $entities = array_filter($entities, function ($entity) use ($entity_query_cacheability): bool {
      $access = $entity->access('view', return_as_object: TRUE);
      if ($access->isAllowed()) {
        $entity_query_cacheability->addCacheableDependency($access);
        $entity_query_cacheability->addCacheableDependency($entity);
      }
      return $access->isAllowed();
    });

    $entities_data = array_map(fn (EntityInterface $entity) => [
      'id' => $entity->id(),
      'label' => $entity->label(),
    ], $entities);
    $response = new CacheableJsonResponse($entities_data);
    $response->addCacheableDependency($entity_query_cacheability);
    return $response;
  }

  public function listViewModes(): JsonResponse {
    $data = [];

    // @todo Generalize to other content entity types in https://www.drupal.org/i/3498525
    $entity_type_id = 'node';
    $entity_view_modes = $this->entityDisplayRepository->getViewModeOptions($entity_type_id);

    foreach ($entity_view_modes as $view_mode => $view_mode_label) {
      // @see \Drupal\Core\Entity\Entity\EntityViewMode
      if ($view_mode === 'default') {
        continue;
      }

      $bundle_info = $this->bundleInfo->getBundleInfo($entity_type_id);
      $template_keys = $this->entityTypeManager->getStorage(ContentTemplate::ENTITY_TYPE_ID)->getQuery()
        // TRICKY: not checking access is acceptable because the route requires
        // "create" access for ContentTemplates. This is only exposing labels
        // (the "view label" operation) and whether a ContentTemplate exists.
        ->accessCheck(FALSE)
        ->condition('content_entity_type_id', $entity_type_id)
        ->condition('content_entity_type_view_mode', $view_mode)
        ->execute();
      foreach (array_keys($bundle_info) as $bundle) {
        $template_id = "$entity_type_id.$bundle.$view_mode";
        $data[$entity_type_id][$bundle][$view_mode] = [
          'label' => $view_mode_label,
          'hasTemplate' => in_array($template_id, $template_keys, TRUE),
        ];
      }
    }

    return new JsonResponse(data: $data, status: Response::HTTP_OK);
  }

}
