<?php

declare(strict_types=1);

namespace Drupal\canvas\ComponentSource;

/**
 * Defines an interface for component source plugins that support URL rewrites.
 */
interface UrlRewriteInterface extends ComponentSourceInterface {

  /**
   * Rewrites an example or default component-relative URL to be resolvable.
   *
   * Must refer to a file that actually exists in the defined component, so
   * that it can be made into a resolvable URL. For example: an image file
   * is converted into the publicly accessible URL.
   *
   * @param string $url
   *   The example URL.
   *
   * @return string
   *   A resolvable URL.
   */
  public function rewriteExampleUrl(string $url): string;

}
