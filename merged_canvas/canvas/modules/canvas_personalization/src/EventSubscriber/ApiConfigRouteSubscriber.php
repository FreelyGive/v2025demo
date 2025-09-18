<?php

declare(strict_types=1);

namespace Drupal\canvas_personalization\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\canvas_personalization\Entity\Segment;
use Symfony\Component\Routing\RouteCollection;

final class ApiConfigRouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    // Allow `segment` to be used in internal config HTTP API routes
    foreach ($collection as $route_name => $config_api_route) {
      if (!str_starts_with($route_name, 'canvas.api.config.')) {
        continue;
      }
      if ($route = $collection->get($route_name)) {
        $requirement = $route->getRequirement('canvas_config_entity_type_id');
        if ($requirement) {
          $requirement = str_replace(['(', ')'], '', $requirement);
          $allowed_entity_type_ids = explode('|', $requirement);
          $newRequirement = implode('|', [...$allowed_entity_type_ids, Segment::ENTITY_TYPE_ID]);
          $route->setRequirement('canvas_config_entity_type_id', '(' . $newRequirement . ')');
        }
      }
    }
  }

}
