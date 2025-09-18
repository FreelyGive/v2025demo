<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 *
 * @todo Remove when https://www.drupal.org/project/canvas/issues/3545859 is
 *   fixed and released in Canvas.
 */
final class UrlField extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();

    if ($entity->id()) {
      $this->list[0] = $this->createItem(0, [
        'value' => $entity->toUrl()->toString(),
      ]);
    }
  }

}
