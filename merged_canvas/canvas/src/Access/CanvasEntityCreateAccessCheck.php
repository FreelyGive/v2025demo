<?php

declare(strict_types=1);

namespace Drupal\canvas\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityCreateAccessCheck;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Entity\StagedConfigUpdate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Defines an access checker for entity creation allowing dynamic entity types.
 *
 * @todo Remove when https://www.drupal.org/project/drupal/issues/3516775 lands in Drupal core and Canvas requires a version that includes it.
 */
final class CanvasEntityCreateAccessCheck extends EntityCreateAccessCheck implements AccessInterface {

  protected $requirementsKey = '_canvas_entity_create_access';

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account, ?Request $request = NULL): AccessResultInterface {
    [$entity_type, $bundle] = explode(':', $route->getRequirement($this->requirementsKey) . ':');

    // Allow dynamic entity types.
    $parameters = $route_match->getParameters();
    if ($parameters->has($entity_type)) {
      $entity_type = $parameters->get($entity_type);
    }

    // The bundle argument can contain request argument placeholders like
    // {name}, loop over the raw variables and attempt to replace them in the
    // bundle name. If a placeholder does not exist, it won't get replaced.
    if ($bundle && str_contains($bundle, '{')) {
      foreach ($route_match->getRawParameters()->all() as $name => $value) {
        $bundle = str_replace('{' . $name . '}', $value, $bundle);
      }
      // If we were unable to replace all placeholders, deny access.
      if (str_contains($bundle, '{')) {
        return AccessResult::neutral(sprintf("Could not find '%s' request argument, therefore cannot check create access.", $bundle));
      }
    }

    $create_access_context = [];
    // Pass the target config (e.g. `node.type.article` or `system.site`) as
    // context for the create access logic of staged config updates.
    if ($entity_type === StagedConfigUpdate::ENTITY_TYPE_ID) {
      $create_access_context['target'] = (json_decode($request?->getContent() ?? '{}', TRUE) ?? [])['data']['target'] ?? FALSE;
    }

    return $this->entityTypeManager->getAccessControlHandler($entity_type)->createAccess($bundle, $account, $create_access_context, TRUE);
  }

}
