<?php

namespace Drupal\canvas_stark;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;

class PreRender implements TrustedCallbackInterface {

  public static function verticalTabs(array $element): array {
    // Inspired by Claro, so items can be identified as vertical tab items and
    // in some cases, items within an accordion.
    $group_type_is_details = isset($element['group']['#type']) && $element['group']['#type'] === 'details';
    $groups_are_present = isset($element['group']['#groups']) && is_array($element['group']['#groups']);

    if ($group_type_is_details && $groups_are_present) {
      $group_keys = Element::children($element['group']['#groups'], TRUE);
      $group_key = implode('][', $element['#parents']);
      // Only check siblings against groups because we are only looking for
      // group elements.
      if (in_array($group_key, $group_keys)) {
        $children_keys = Element::children($element['group']['#groups'][$group_key], TRUE);

        foreach ($children_keys as $child_key) {
          $type = $element['group']['#groups'][$group_key][$child_key]['#type'] ?? NULL;
          if ($type === 'details') {
            if (!empty($element['#accordion'])) {
              $element['group']['#groups'][$group_key][$child_key]['#accordion_item'] = TRUE;
            }
            $element['group']['#groups'][$group_key][$child_key]['#vertical_tab_item'] = TRUE;
          }
        }
      }
    }

    return $element;
  }

  public static function table(array $element): array {
    if (!empty($element['#tabledrag'])) {
      $element['#attributes']['data-canvas-tabledrag'] = 'true';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'verticalTabs',
      'table',
    ];
  }

}
