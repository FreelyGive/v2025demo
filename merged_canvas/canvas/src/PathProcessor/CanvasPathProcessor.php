<?php

declare(strict_types=1);

namespace Drupal\canvas\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a path processor to rewrite React-based Canvas routes.
 *
 * The main Canvas frontend route can have additional parameters that are handled
 * by the React router; Drupal does not care about these, so we strip them off.
 * This is the cleanest way until core supports this directly in routing.
 *
 * @see https://www.drupal.org/project/drupal/issues/2741939
 */
class CanvasPathProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    // Only rewrite if not /canvas/api and starts with /canvas/.
    // For this to work, our routes require that no route normalization happens
    // when the redirect module is enabled.
    // @see \Drupal\canvas\EventSubscriber\CanvasRouteOptionsEventSubscriber::preventRouteNormalization.
    if (str_starts_with($path, '/canvas/') && !str_starts_with($path, '/canvas/api')) {
      return '/canvas';
    }
    return $path;
  }

}
