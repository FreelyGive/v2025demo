<?php

namespace Drupal\canvas\Element;

use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\PropSource\DynamicPropSource;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

#[RenderElement('linked_prop_source')]
class LinkedPropSource extends RenderElementBase {

  /**
   * Proves a render element for a linked prop source in a form.
   *
   * In the UI, a DynamicPropSource that populates a component input is
   * considered "linked".
   *
   * @todo Resolve the naming confusion in https://www.drupal.org/i/3548297
   *
   * Properties:
   * - #sdc_prop_name: The name of the prop in the component.
   * - #sdc_prop_label: The label of the prop in the component.
   * - #linked_prop_source: The dynamic prop source object.
   * - #field_link_suggestions: An array of field name suggestions for linking.
   * - #component: The component with props being linked.
   * - #is_required: Whether the prop is required.
   *
   * @see \Drupal\canvas\PropSource\DynamicPropSource
   */
  public function getInfo() {
    return [
      '#input' => FALSE,
      '#theme_wrappers' => ['container'],
      '#process' => [
        [static::class, 'processLinkedPropSource'],
      ],
      '#sdc_prop_name' => NULL,
      '#linked_prop_source' => NULL,
      '#field_link_suggestions' => [],
      '#component' => NULL,
      '#is_required' => FALSE,
      '#attributes' => [
        'class' => ['canvas-linked-prop-wrapper'],
      ],
    ];
  }

  /**
   * Processes a linked prop source form element.
   */
  public static function processLinkedPropSource(array &$element): array {
    $sdc_prop_name = $element['#sdc_prop_name'];
    assert(is_string($sdc_prop_name));
    $sdc_prop_label = $element['#sdc_prop_label'];
    assert(is_string($sdc_prop_label));
    $linked_prop_source = $element['#linked_prop_source'];
    assert($linked_prop_source instanceof DynamicPropSource);
    $field_link_suggestions = $element['#field_link_suggestions'] ?? [];
    assert(is_array($field_link_suggestions));
    $component = $element['#component'];
    assert($component instanceof ComponentInterface);
    $is_required = $element['#is_required'] ?? FALSE;

    $element['label_wrap'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['canvas-linked-prop-label-wrapper'],
      ],
      'label' => [
        '#type' => 'label',
        '#title' => $sdc_prop_label,
        '#required' => $is_required,
      ],
      'post_label' => [
        // @see ui/src/components/form/components/drupal/PropLinker.tsx, the
        // template that renders `prop_linker`.
        // @see docs/semi-coupled-theme-engine.md for information about the
        // underlying logic that makes this renderable with JSX.
        '#theme' => 'prop_linker',
        '#linked' => TRUE,
        '#prop_name' => $sdc_prop_name,
        '#suggestions' => $field_link_suggestions,
      ],
    ];

    $element['badge'] = [
      // @see ui/src/components/form/components/drupal/LinkedFieldBox.tsx,
      // the template that renders `linked_field_box`.
      // @see docs/semi-coupled-theme-engine.md for information about the
      // underlying logic that makes this renderable with JSX.
      '#theme' => 'linked_field_box',
      '#title' => $linked_prop_source->label(),
      '#prop_name' => $sdc_prop_name,
    ];

    return $element;
  }

}
