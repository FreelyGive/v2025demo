<?php

declare(strict_types=1);

namespace Drupal\canvas\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface;

/**
 * Defines access check ensuring Canvas config entity is eligible for API usage.
 */
final class CanvasHttpApiEligibleConfigEntityAccessCheck implements AccessInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Checks that Canvas config entity is eligible for internal HTTP API usage.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match) {
    $canvas_config_entity_type_id = $route_match->getParameter('canvas_config_entity_type_id');
    $canvas_config_entity_type = $this->entityTypeManager->getDefinition($canvas_config_entity_type_id);
    assert($canvas_config_entity_type instanceof ConfigEntityTypeInterface);

    return AccessResult::allowedIf(is_a($canvas_config_entity_type->getClass(), CanvasHttpApiEligibleConfigEntityInterface::class, TRUE));
  }

}
