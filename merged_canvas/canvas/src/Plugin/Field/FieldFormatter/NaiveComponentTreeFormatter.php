<?php

namespace Drupal\canvas\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;

/**
 * @todo This is naive and insufficient: this needs to take over the rendering of the entire entity, not just of this single field. Still, for PoC/data model purposes, this is sufficient initially.
 */
#[FieldFormatter(
  id: 'canvas_naive_render_sdc_tree',
  label: new TranslatableMarkup('Render SDC tree'),
  field_types: [
    'component_tree',
  ],
)]
class NaiveComponentTreeFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    \assert($items instanceof ComponentTreeItemList);
    return [$items->toRenderable($items->getEntity())];
  }

}
