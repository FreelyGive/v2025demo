<?php

declare(strict_types=1);

namespace Drupal\canvas\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\canvas\Render\MainContent\CanvasPreviewRenderer;
use Drupal\canvas\Render\PreviewEnvelope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines an event subscriber that converts a preview envelope into a response.
 */
final class PreviewEnvelopeViewSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly CanvasPreviewRenderer $renderer,
    private readonly RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * Sets a response given a preview envelope.
   *
   * @param \Symfony\Component\HttpKernel\Event\ViewEvent $event
   *   The event to process.
   */
  public function onViewPreviewEnvelope(ViewEvent $event): void {
    $request = $event->getRequest();
    $result = $event->getControllerResult();

    if ($result instanceof PreviewEnvelope) {
      $response = $this->renderer->renderResponse($result->previewRenderArray, $request, $this->routeMatch, $result->additionalData);
      if ($response instanceof CacheableResponseInterface) {
        $main_content_view_subscriber_cacheability = (new CacheableMetadata())->setCacheContexts(['url.query_args:' . MainContentViewSubscriber::WRAPPER_FORMAT]);
        $response->addCacheableDependency($main_content_view_subscriber_cacheability);
      }
      $response->setStatusCode($result->statusCode);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::VIEW][] = ['onViewPreviewEnvelope'];
    return $events;
  }

}
