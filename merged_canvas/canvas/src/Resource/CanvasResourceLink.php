<?php

declare(strict_types=1);

namespace Drupal\canvas\Resource;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Url;

/**
 * Represents an RFC8288 based link.
 *
 * This is a straight copy of \Drupal\jsonapi\JsonApiResource\Link.
 *
 * @internal
 *
 * @see \Drupal\jsonapi\JsonApiResource\Link
 * @see https://tools.ietf.org/html/rfc8288
 */
final class CanvasResourceLink implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  protected Url $uri;

  protected string $href;

  protected string $rel;

  /**
   * The link target attributes.
   *
   * @var string[]
   *   An associative array where the keys are the attribute keys and values are
   *   either string or an array of strings.
   */
  protected array $attributes;

  /**
   * CanvasResourceLink constructor.
   *
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Any cacheability metadata associated with the link. For example, a
   *   'call-to-action' link might reference a registration resource if an event
   *   has vacancies or a wait-list resource otherwise. Therefore, the link's
   *   cacheability might be depend on a certain entity's values other than the
   *   entity on which the link will appear.
   * @param \Drupal\Core\Url $url
   *   The Url object for the link.
   * @param string $link_relation_type
   *   An array of registered or extension RFC8288 link relation types.
   * @param array $target_attributes
   *   An associative array of target attributes for the link.
   *
   * @see https://tools.ietf.org/html/rfc8288#section-2.1
   */
  public function __construct(RefinableCacheableDependencyInterface $cacheability, Url $url, string $link_relation_type, array $target_attributes = []) {
    assert(Inspector::assertAllStrings(array_keys($target_attributes)));
    assert(Inspector::assertAll(function ($target_attribute_value) {
      return is_string($target_attribute_value) || is_array($target_attribute_value);
    }, array_values($target_attributes)));
    $generated_url = $url->toString(TRUE);
    $this->href = $generated_url->getGeneratedUrl();
    $this->uri = $url;
    $this->rel = $link_relation_type;
    $this->attributes = $target_attributes;
    $this->setCacheability($cacheability->addCacheableDependency($generated_url));
  }

  /**
   * Gets the link's URI.
   *
   * @return \Drupal\Core\Url
   *   The link's URI as a Url object.
   */
  public function getUri(): Url {
    return $this->uri;
  }

  /**
   * Gets the link's URI as a string.
   *
   * @return string
   *   The link's URI as a string.
   */
  public function getHref(): string {
    return $this->href;
  }

  /**
   * Gets the link's relation type.
   *
   * @return string
   *   The link's relation type.
   */
  public function getLinkRelationType(): string {
    return $this->rel;
  }

  /**
   * Gets the link's target attributes.
   *
   * @return string[]
   *   The link's target attributes.
   */
  public function getTargetAttributes(): array {
    return $this->attributes;
  }

  /**
   * Compares two links.
   *
   * @param \Drupal\canvas\Resource\CanvasResourceLink $a
   *   The first link.
   * @param \Drupal\canvas\Resource\CanvasResourceLink $b
   *   The second link.
   *
   * @return int
   *   0 if the links can be considered identical, an integer greater than or
   *   less than 0 otherwise.
   */
  public static function compare(CanvasResourceLink $a, CanvasResourceLink $b): int {
    // Any string concatenation would work, but a Link header-like format makes
    // it clear what is being compared.
    $a_string = sprintf('<%s>;rel="%s"', $a->getHref(), $a->rel);
    $b_string = sprintf('<%s>;rel="%s"', $b->getHref(), $b->rel);
    $cmp = strcmp($a_string, $b_string);
    // If the `href` or `rel` of the links are not equivalent, it's not
    // necessary to compare target attributes.
    if ($cmp === 0) {
      return (int) !empty(DiffArray::diffAssocRecursive($a->getTargetAttributes(), $b->getTargetAttributes()));
    }
    return $cmp;
  }

  /**
   * Merges two equivalent links into one link with the merged cacheability.
   *
   * The links must share the same URI, link relation type and attributes.
   *
   * @param \Drupal\canvas\Resource\CanvasResourceLink $a
   *   The first link.
   * @param \Drupal\canvas\Resource\CanvasResourceLink $b
   *   The second link.
   *
   * @return static
   *   A new CanvasResourceLink object with the cacheability of both links merged.
   */
  public static function merge(CanvasResourceLink $a, CanvasResourceLink $b): CanvasResourceLink {
    assert(static::compare($a, $b) === 0, 'Only equivalent links can be merged.');
    $merged_cacheability = (new CacheableMetadata())->addCacheableDependency($a)->addCacheableDependency($b);
    return new static($merged_cacheability, $a->getUri(), $a->getLinkRelationType(), $a->getTargetAttributes());
  }

}
