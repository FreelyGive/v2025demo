<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity\Routing;

use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\canvas\Controller\CanvasController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Enhances entity form routes that default to Drupal Canvas.
 *
 * This is about mapping a content entity type's link template's specific route
 * parameter names (for example `{canvas_page}`) to the generic `{entity}`.
 *
 * @see \Drupal\canvas\Entity\Routing\CanvasHtmlRouteProvider
 */
final class CanvasHtmlRouteEnhancer implements EnhancerInterface {

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request): array {
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    if (!$this->applies($route)) {
      return $defaults;
    }
    $defaults['_controller'] = CanvasController::class;

    $entity_type_id = $route->getDefault('_canvas');
    $defaults['entity_type'] = $entity_type_id;

    $defaults['entity'] = NULL;
    if (!empty($defaults[$entity_type_id])) {
      $defaults['entity'] = $defaults[$entity_type_id];
    }

    unset($defaults['_canvas']);
    return $defaults;
  }

  /**
   * Checks if the route applies to this enhancer.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check.
   *
   * @return bool
   *   Whether the route applies to this enhancer.
   */
  private function applies(Route $route): bool {
    return $route->hasDefault('_canvas');
  }

}
