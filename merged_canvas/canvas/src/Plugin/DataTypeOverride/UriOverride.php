<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\DataTypeOverride;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\TypedData\Plugin\DataType\Uri;

/**
 * @todo Fix upstream: `uri` data type *never* returns valid URIs without this!
 */
class UriOverride extends Uri {

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    $components = parse_url($this->value);
    // Without at least a scheme and host, there's no hope of casting this to a
    // valid URI. Abort.
    if (!is_array($components) || !array_key_exists('scheme', $components) || !array_key_exists('host', $components)) {
      return $this->value;
    }

    $uri = $components['scheme'] . '://'
      . $components['host']
      . UrlHelper::encodePath($components['path'] ?? '');

    if (array_key_exists('query', $components) && strlen($components['query'])) {
      $uri .= '?' . $components['query'];
    }
    if (array_key_exists('fragment', $components)) {
      $uri .= '#' . $components['fragment'];
    }

    assert(UrlHelper::isValid($uri));
    return $uri;
  }

}
