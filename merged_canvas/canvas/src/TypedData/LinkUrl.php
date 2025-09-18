<?php

declare(strict_types=1);

namespace Drupal\canvas\TypedData;

use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\TypedData\Type\UriInterface;
use Drupal\link\LinkItemInterface;

/**
 * Defines a link URL computed value.
 *
 * Resolves e.g. `entity:node/3` to `/node/3` or `/subdir/node/3`, which is a
 * URL that a browser understands.
 */
final class LinkUrl extends StringData implements UriInterface {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $item = $this->getParent();
    \assert($item instanceof LinkItemInterface);
    $uri = $item->get('uri')->getValue();
    if (empty($uri)) {
      return $uri;
    }
    if (in_array($uri, ['<nolink>', '<none>', '<button>'], TRUE)) {
      $uri = 'route:' . $uri;
      $item->set('uri', $uri);
    }
    elseif (\parse_url($uri, PHP_URL_SCHEME) === NULL) {
      if (str_starts_with($uri, '<front>')) {
        $uri = '/' . substr($uri, strlen('<front>'));
      }
      if (!str_starts_with($uri, '/')) {
        return $uri;
      }
      // We cannot use Url::fromUri without a scheme, use internal scheme.
      $item->set('uri', 'internal:' . $uri);
    }
    return $item->getUrl()->toString();
  }

  public function getCastedValue(): string {
    return $this->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    // We don't support setting a value here.
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

}
