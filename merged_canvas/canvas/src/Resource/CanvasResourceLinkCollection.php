<?php

declare(strict_types=1);

namespace Drupal\canvas\Resource;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;

/**
 * Contains a set of CanvasResourceLink objects.
 *
 * Heavily inspired by \Drupal\jsonapi\JsonApiResource\LinkCollection.
 * The differences are:
 * - JsonApi LinkCollection requires a context while we don't here.
 * - Each link rel can hold an array of links in JsonApi, while we only allow one.
 * - Implements \Drupal\Core\Cache\CacheableDependencyInterface and
 * \Drupal\Core\Cache\RefinableCacheableDependencyInterface.
 *
 * @internal
 *
 * @see \Drupal\jsonapi\JsonApiResource\LinkCollection
 */
final class CanvasResourceLinkCollection implements \IteratorAggregate, CacheableDependencyInterface, RefinableCacheableDependencyInterface {

  use CacheableDependencyTrait;
  use RefinableCacheableDependencyTrait;

  /**
   * The links in the collection, keyed by unique strings.
   *
   * @var \Drupal\canvas\Resource\CanvasResourceLink[]
   */
  protected array $links;

  /**
   * CanvasResourceLinkCollection constructor.
   *
   * @param \Drupal\canvas\Resource\CanvasResourceLink[] $links
   *   An associated array of key names and CanvasResourceLink objects.
   */
  public function __construct(array $links) {
    assert(Inspector::assertAll(function ($key) {
      return static::validKey($key);
    }, array_keys($links)));
    assert(Inspector::assertAll(function ($link) {
      return $link instanceof CanvasResourceLink;
    }, $links));
    ksort($links);
    $this->links = $links;
    foreach ($links as $link) {
      $this->addCacheableDependency($link);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return \ArrayIterator<\Drupal\canvas\Resource\CanvasResourceLink>
   */
  public function getIterator(): \ArrayIterator {
    return new \ArrayIterator($this->links);
  }

  /**
   * Gets a new CanvasResourceLinkCollection with the given link inserted.
   *
   * @param string $key
   *   A key for the link. If the key already exists and the link shares an
   *   href, link relation type and attributes with an existing link with that
   *   key, those links will be merged together.
   * @param \Drupal\canvas\Resource\CanvasResourceLink $new_link
   *   The link to insert.
   *
   * @return static
   *   A new CanvasResourceLinkCollection with the given link inserted or merged with the
   *   current set of links.
   */
  public function withLink(string $key, CanvasResourceLink $new_link): CanvasResourceLinkCollection {
    assert(static::validKey($key));
    $merged = $this->links;
    if (isset($merged[$key])) {
      if (CanvasResourceLink::compare($merged[$key], $new_link) === 0) {
        $merged[$key] = CanvasResourceLink::merge($merged[$key], $new_link);
      }
    }
    else {
      $merged[$key] = $new_link;
    }
    $collection = new static($merged);
    // We need to keep existing cache metadata added to the collection object
    // for e.g. absent links.
    $collection->addCacheTags($this->getCacheTags())
      ->addCacheContexts($this->getCacheContexts())
      ->mergeCacheMaxAge($this->getCacheMaxAge());
    return $collection;
  }

  /**
   * Whether a link with the given key exists.
   *
   * @param string $key
   *   The key.
   *
   * @return bool
   *   TRUE if a link with the given key exist, FALSE otherwise.
   */
  public function hasLinkWithKey($key): bool {
    return array_key_exists($key, $this->links);
  }

  /**
   * Filters a CanvasResourceLinkCollection using the provided callback.
   *
   * @param callable $f
   *   The filter callback. The callback has the signature below.
   *
   * @code
   *   boolean callback(string $key, \Drupal\canvas\Resource\CanvasResourceLink $link))
   * @endcode
   *
   * @return CanvasResourceLinkCollection
   *   A new, filtered CanvasResourceLinkCollection.
   */
  public function filter(callable $f): CanvasResourceLinkCollection {
    $links = iterator_to_array($this);
    $filtered = array_reduce(array_keys($links), function ($filtered, $key) use ($links, $f) {
      if ($f($key, $links[$key])) {
        $filtered[$key] = $links[$key];
      }
      return $filtered;
    }, []);
    return new CanvasResourceLinkCollection($filtered);
  }

  /**
   * Merges two CanvasResourceLinkCollections.
   *
   * @param \Drupal\canvas\Resource\CanvasResourceLinkCollection $a
   *   The first link collection.
   * @param \Drupal\canvas\Resource\CanvasResourceLinkCollection $b
   *   The second link collection.
   *
   * @return \Drupal\canvas\Resource\CanvasResourceLinkCollection
   *   A new CanvasResourceLinkCollection with the links of both inputs.
   */
  public static function merge(CanvasResourceLinkCollection $a, CanvasResourceLinkCollection $b): CanvasResourceLinkCollection {
    $merged = new CanvasResourceLinkCollection([]);
    foreach ($a as $key => $link) {
      $merged = $merged->withLink($key, $link);
    }
    foreach ($b as $key => $link) {
      $merged = $merged->withLink($key, $link);
    }
    return $merged;
  }

  /**
   * Ensures that a link key is valid.
   *
   * @param string $key
   *   A key name.
   *
   * @return bool
   *   TRUE if the key is valid, FALSE otherwise.
   */
  protected static function validKey(string $key): bool {
    return !is_numeric($key);
  }

  /**
   * @return array<string, string>
   *
   * @see https://jsonapi.org/format/#document-links
   */
  public function asArray(): array {
    return array_reduce($this->links, function (array $carry, CanvasResourceLink $link): array {
      $carry[$link->getLinkRelationType()] = $link->getHref();
      return $carry;
    }, []);
  }

}
