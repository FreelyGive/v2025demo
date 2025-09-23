<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\canvas\ClientSideRepresentation;

/**
 * @see \Drupal\canvas\Controller\ApiConfigControllers
 * @see \Drupal\canvas\Access\CanvasHttpApiEligibleConfigEntityAccessCheck
 * @internal This interface must be implemented by any Drupal Canvas config
 *   entity that wants to be exposed via Canvas's HTTP API for config entities.
 */
interface CanvasHttpApiEligibleConfigEntityInterface extends ConfigEntityInterface {

  /**
   * Normalizes this config entity to the data model expected by the client.
   *
   * @return \Drupal\canvas\ClientSideRepresentation
   *
   * @see openapi.yml
   * @see \Drupal\canvas\ComponentSource\ComponentSourceInterface::inputToClientModel()
   */
  public function normalizeForClientSide(): ClientSideRepresentation;

  /**
   * Creates a new config entity from the data model used by the client.
   *
   * @see openapi.yml
   */
  public static function createFromClientSide(array $data): static;

  /**
   * Updates this config entity from the data model used by the client.
   *
   * @see openapi.yml
   */
  public function updateFromClientSide(array $data): void;

  /**
   * Allows the config entity query that generates the listing to be refined.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The config entity query to refine, passed by reference.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The cacheability of the given query, to be refined to match the
   *   refinements made to the query.
   *
   * @return void
   */
  public static function refineListQuery(QueryInterface &$query, RefinableCacheableDependencyInterface $cacheability): void;

}
