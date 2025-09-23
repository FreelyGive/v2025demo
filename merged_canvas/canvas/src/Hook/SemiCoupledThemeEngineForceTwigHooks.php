<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * @file
 * Hook implementations informing the Semi-Coupled theme engine to Twig-render.
 *
 * Identifies templates that should be Twig rendered.
 *
 * @see themes/canvas_stark/templates/process_as_regular_twig
 * @see \Drupal\canvas\Hook\SemiCoupledThemeEngineHooks
 * @see https://git.drupalcode.org/project/canvas/-/commit/c5b5d93d79cb7260ec5160fa22014a1f755b40cf
 */
class SemiCoupledThemeEngineForceTwigHooks {

  /**
   * Implements hook_form_alter().
   *
   * Forces rendering with Twig.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $ml_widget = isset($form['#id']) && \str_contains($form['#id'], 'views-exposed-form-media-library-widget');
    $ml_form = \str_contains($form_id, 'media');
    if ($ml_widget || $ml_form) {
      $this->markMediaLibrary($form, 'media_library');
    }
  }

  /**
   * Attaches form id to all form elements.
   *
   * @param array $form
   *   The form or form element which children should have form id attached.
   * @param string $form_id
   *   The form id attached to form elements.
   */
  protected function markMediaLibrary(array &$form, string $form_id): void {
    foreach (Element::children($form) as $child) {
      if (!isset($form[$child]['#form_id'])) {
        $form[$child]['#form_id'] = $form_id;
      }
      $this->markMediaLibrary($form[$child], $form_id);
    }
  }

  /**
   * Implements hook_theme_suggestions_form_element_label().
   */
  #[Hook('theme_suggestions_form_element_label')]
  public function themeSuggestionsFormElementLabel(array $variables): array {
    $suggestions = [];
    if (isset($variables['element']['#form_id']) && \str_contains($variables['element']['#form_id'], 'media_library')) {
      $suggestions[] = 'form_element_label__media_library';
    }
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_form_element')]
  public function themeSuggestionsFormElement(array $variables): array {
    $suggestions = [];
    if (isset($variables['element']['#form_id']) && str_contains($variables['element']['#form_id'], 'media_library')) {
      $suggestions[] = 'form_element__media_library';
    }
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_form')]
  public function themeSuggestionsForm(array $variables): array {
    $suggestions = [];
    $ml_view = isset($variables['element']['#id']) && str_contains($variables['element']['#id'], 'views-exposed-form-media-library-widget');
    $ml_form = !empty($variables['element']['#form_id']) && str_contains($variables['element']['#form_id'], 'media_library');
    if ($ml_view || $ml_form) {
      $suggestions[] = 'form__media_library';
    }
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_select')]
  public function themeSuggestionsSelect(array $variables): array {
    $suggestions = [];
    $ml_view = isset($variables['element']['#id']) && str_contains($variables['element']['#id'], 'views-exposed-form-media-library-widget');
    $ml_form = !empty($variables['element']['#form_id']) && str_contains($variables['element']['#form_id'], 'media_library');
    if ($ml_view || $ml_form) {
      $suggestions[] = 'select__media_library';
    }
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_input')]
  public function themeSuggestionsInput(array $variables): array {
    $suggestions = [];
    $ml_ajax = isset($variables['element']['#ajax']['wrapper']) && $variables['element']['#ajax']['wrapper'] === 'media-library-wrapper';
    $ml_form = isset($variables['element']['#form_id']) && str_contains($variables['element']['#form_id'], 'media_library');
    if ($ml_ajax || $ml_form) {
      $suggestions[] = 'input__media_library';
    }
    return $suggestions;
  }

}
