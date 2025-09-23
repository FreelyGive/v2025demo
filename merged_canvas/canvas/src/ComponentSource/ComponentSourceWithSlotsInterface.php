<?php

declare(strict_types=1);

namespace Drupal\canvas\ComponentSource;

/**
 * Defines an interface for component sources that support slots.
 */
interface ComponentSourceWithSlotsInterface extends ComponentSourceInterface {

  /**
   * Gets information about the slots.
   *
   * @return array<string, array{'title': string, 'description'?: string, 'examples': array<mixed>}>
   *
   * @todo Reassess the return type/array shape.
   */
  public function getSlotDefinitions(): array;

  /**
   * Sets the slots in a render array.
   *
   * @param array $build
   *   The render array.
   * @param array $slots
   *   The slots, keyed by slot name.
   *
   * @return void
   */
  public function setSlots(array &$build, array $slots): void;

}
