<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiUsageControllers extends ApiControllerBase {

  /**
   * The maximum number of results to return per page.
   */
  public const int MAX_PER_PAGE = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ComponentAudit $componentAudit,
    private readonly PagerManagerInterface $pagerManager,
  ) {}

  /**
   * Checks if a specific component is in use and returns as a boolean.
   */
  public function component(Component $component): JsonResponse {
    return new JsonResponse(
      data: $this->componentAudit->hasUsages($component),
      status: Response::HTTP_OK
    );
  }

  /**
   * Checks if a component is in use and returns details of where it is used.
   *
   * @todo Add the ability to request the details for a specific version of a `Component`, rather than only the active version of the `Component`.
   * @todo Do not list every revision it is used in, but only the entities it is used in, along with the oldest and newest revision it occurs in, but not a unique array item per revision
   * @todo Add "editUrl" for every listed entity.
   */
  public function componentDetails(Component $component): JsonResponse {
    if ($this->componentAudit->hasUsages($component)) {
      $dependents = [];
      if ($content_dependents = $this->componentAudit->getContentRevisionsUsingComponent($component, [$component->getLoadedVersion()])) {
        foreach ($content_dependents as $content_dependent) {
          $dependents['content'][] = [
            'title' => $content_dependent->label(),
            'type' => $content_dependent->getEntityTypeId(),
            'bundle' => $content_dependent->bundle(),
            'id' => $content_dependent->id(),
            'revision_id' => $content_dependent->getRevisionId(),
          ];
        }
      }

      $config_entity_types = \array_keys(\array_filter(
        $this->entityTypeManager->getDefinitions(),
        static fn (EntityTypeInterface $type): bool => $type instanceof ConfigEntityTypeInterface && $type->entityClassImplements(ComponentTreeEntityInterface::class)
      ));
      foreach ($config_entity_types as $config_entity_type) {
        $config_dependents = $this->componentAudit->getConfigEntityDependenciesUsingComponent($component, $config_entity_type);
        if ($config_dependents) {
          foreach ($config_dependents as $config_dependent) {
            $dependents[$config_entity_type][] = [
              'title' => $config_dependent->label(),
              'id' => $config_dependent->id(),
            ];
          }
        }
      }

      return new JsonResponse(
        data: $dependents,
        status: Response::HTTP_OK
      );
    }
    return new JsonResponse(data: NULL, status: Response::HTTP_OK);
  }

  /**
   * Returns a paginated list of components and whether they are in use as a boolean.
   */
  public function componentsList(Request $request): JsonResponse {
    $storage = $this->entityTypeManager->getStorage(Component::ENTITY_TYPE_ID);
    $entity_query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->pager(self::MAX_PER_PAGE);
    $entities = $entity_query->execute();

    $usage_data = [];
    foreach ($entities as $entity) {
      $config_entity = $storage->load($entity);
      if ($config_entity instanceof Component) {
        $usage_data[$entity] = $this->componentAudit->hasUsages($config_entity);
      }
    }

    $base_url = Url::fromRoute('canvas.api.usage.component.list');
    $pager = $this->pagerManager->getPager();
    assert(!is_null($pager));
    $current_page = $pager->getCurrentPage();
    return new JsonResponse(
      data: [
        'data' => $usage_data,
        'links' => [
          'prev' => $current_page === 0
            ? NULL
            : $base_url->setRouteParameters($this->pagerManager->getUpdatedParameters([], 0, $current_page - 1))->toString(),
          'next' => $current_page + 1 === $pager->getTotalPages()
            ? NULL
            : $base_url->setRouteParameters($this->pagerManager->getUpdatedParameters([], 0, $current_page + 1))->toString(),
        ],
      ],
      status: Response::HTTP_OK
    );
  }

}
