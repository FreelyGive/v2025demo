<?php

namespace Drupal\canvas_ai;

use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Service for managing auto save functionality for Canvas AI.
 */
class CanvasAiTempStore {

  /**
   * Storage key for current layout data of the page.
   */
  public const CURRENT_LAYOUT_KEY = 'current_layout';

  /**
   * The private tempstore object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * Constructs a new CanvasAiTempStore object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The tempstore factory.
   */
  public function __construct(
    PrivateTempStoreFactory $tempStoreFactory,
  ) {
    $this->tempStore = $tempStoreFactory->get('canvas_ai');
  }

  /**
   * Sets the data in the tempstore.
   *
   * @param string $key
   *   The key for storing data.
   * @param string $data
   *   The data to store in the tempstore.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function setData(string $key, string $data): void {
    $this->tempStore->set($key, $data);
  }

  /**
   * Gets the data from the tempstore.
   *
   * @param string $key
   *   The key to retrieve data for.
   *
   * @return string|null
   *   The data, or NULL if not set.
   */
  public function getData(string $key): ?string {
    return $this->tempStore->get($key);
  }

  /**
   * Removes specific data from the tempstore.
   *
   * @param string $key
   *   The key to remove data for.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function deleteData(string $key): void {
    $this->tempStore->delete($key);
  }

}
