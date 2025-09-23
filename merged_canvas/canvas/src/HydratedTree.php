<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;

/**
 * Defines a value object for a hydrated component tree.
 */
final class HydratedTree implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  public function __construct(
    protected array $tree,
    CacheableDependencyInterface $cacheability,
  ) {
    $this->setCacheability($cacheability);
  }

  /**
   * Gets hydrated tree.
   *
   * @return array
   *   The hydrated tree.
   */
  public function getTree(): array {
    return $this->tree;
  }

}
