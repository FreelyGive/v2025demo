<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\options\Plugin\Field\FieldType\ListIntegerItem;

/**
 * @todo Fix upstream.
 */
class ListIntegerItemOverride extends ListIntegerItem {

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();

    $this->value = static::castAllowedValue($this->value);
  }

}
