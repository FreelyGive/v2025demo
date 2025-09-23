<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Defines a trait with various methods for working with symfony/dom-crawler.
 */
trait CrawlerTrait {

  /**
   * Builds a crawler for a render array.
   *
   * Bubbled metadata (cacheability and attachments) is available on the given
   * render array after calling this.
   *
   * @param array $build
   *   Render array.
   */
  protected function crawlerForRenderArray(array &$build): Crawler {
    $renderer = \Drupal::service(RendererInterface::class);
    \assert($renderer instanceof RendererInterface);
    $context = new RenderContext();
    // We don't use an arrow function here as we want $build to be modified by
    // reference and that isn't possible with an arrow function.
    $out = (string) $renderer->executeInRenderContext($context, function () use (&$build, $renderer) {
      return $renderer->render($build);
    });
    return new Crawler($out);
  }

}
