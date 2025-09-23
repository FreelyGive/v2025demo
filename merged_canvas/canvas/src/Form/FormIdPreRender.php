<?php

declare(strict_types=1);

namespace Drupal\canvas\Form;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\Attribute\TrustedCallback;

/**
 * Defines a pre-render method for adding form ID to elements.
 */
final class FormIdPreRender {

  /**
   * Pre-render callback to add form ID to each form element.
   *
   * @param array $element
   *   Array element.
   */
  #[TrustedCallback]
  public static function addFormId(array $element): array {
    // @todo Rename to canvas-data-form-id in https://drupal.org/i//3517029.
    $form_id = $element['#attributes']['data-form-id'];
    foreach (Element::children($element) as $child) {
      $element[$child]['#attributes']['data-form-id'] = $form_id;
      $element[$child] = self::addFormId($element[$child]);
    }
    return $element;
  }

  #[TrustedCallback]
  public static function addRequiredAttributesToChildren(array $element): array {
    $element = self::addFormId($element);
    $element['#attributes']['data-ajax'] = TRUE;
    foreach (Element::children($element) as $key) {
      $element[$key] = self::addRequiredAttributesToChildren($element[$key]);
    }
    return $element;
  }

  /**
   * Adds a data-ajax attribute to elements.
   *
   * @param array $element
   *   Element to add attribute to.
   * @param string $form_id
   *   Form ID.
   */
  public static function addAjaxAttribute(array &$element, string $form_id): void {
    // @todo Rename to canvas-data-ajax in https://drupal.org/i//3517029.
    $element['#attributes']['data-ajax'] = TRUE;
    // @todo Rename to canvas-data-form-id in https://drupal.org/i//3517029.
    $element['#attributes']['data-form-id'] = $form_id;
    // We can't add an element pre render callback without first ensuring the
    // defaults have been added.
    if (isset($element['#type']) && empty($element['#defaults_loaded']) && ($info = \Drupal::service('plugin.manager.element_info')->getInfo($element['#type']))) {
      // Add in any default pre-render callbacks.
      $element['#pre_render'] = $info['#pre_render'] ?? [];
    }
    $element['#pre_render'][] = [FormIdPreRender::class, 'addRequiredAttributesToChildren'];
    foreach (Element::children($element) as $key) {
      self::addAjaxAttribute($element[$key], $form_id);
    }
  }

}
