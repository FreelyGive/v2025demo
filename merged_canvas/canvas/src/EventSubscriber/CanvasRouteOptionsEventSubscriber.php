<?php

declare(strict_types=1);

namespace Drupal\canvas\EventSubscriber;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Affects only Canvas-owned routes.
 *
 * @internal
 */
final class CanvasRouteOptionsEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  public function transformWrapperFormatRouteOption(RequestEvent $event): void {
    if (!str_starts_with($this->routeMatch->getRouteName() ?? '', 'canvas.api.')) {
      return;
    }

    // Allow Canvas routes to declare they must always use a particular main content
    // renderer, by accepting a `_wrapper_format` route option that is upcast
    // to the URL query parameter that Drupal core expects.
    // @see \Drupal\Core\EventSubscriber\MainContentViewSubscriber::WRAPPER_FORMAT
    // @see \Drupal\canvas\Render\MainContent\CanvasTemplateRenderer
    $route_object = $this->routeMatch->getRouteObject();
    if (!is_null($route_object) && $wrapper_format = $route_object->getOption('_wrapper_format')) {
      $event->getRequest()->query->set(MainContentViewSubscriber::WRAPPER_FORMAT, $wrapper_format);
    }
  }

  public function addCsrfToken(RouteBuildEvent $event): void {
    foreach ($event->getRouteCollection() as $name => $route) {
      if (str_starts_with($name, 'canvas.api.') &&
        // Drupal's AJAX submits to these URL and doesn't know that it needs to
        // add an X-CSRF-Token header. These routes use Drupal's form API which
        // already includes CSRF protection via a hidden input.
        $route->getOption('_wrapper_format') !== 'canvas_template') {
        if (array_intersect($route->getMethods(), ['POST', 'PATCH', 'DELETE'])) {
          $route->setRequirement('_csrf_request_header_token', 'TRUE');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['transformWrapperFormatRouteOption'];
    $events[RoutingEvents::ALTER][] = ['addCsrfToken'];
    return $events;
  }

}
