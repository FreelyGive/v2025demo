<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Core\Render\Element;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * @file
 * Hook implementations that make the Semi-Coupled theme engine work.
 *
 * @see https://www.drupal.org/project/issues/canvas?component=Semi-Coupled+theme+engine
 * @see docs/semi-coupled-theme-engine.md
 * @see \Drupal\canvas\Hook\SemiCoupledThemeEngineForceTwigHooks
 */
class SemiCoupledThemeEngineHooks {

  /**
   * Implements hook_field_widget_complete_form_alter().
   *
   * Provide additional context to canvas_theme_suggestions_alter().
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(array &$field_widget_complete_form, FormStateInterface $form_state, array $context): void {
    $field_widget_complete_form['#widget-type'] = $context['widget']->getPluginId();
    if (isset($field_widget_complete_form['widget']) && \is_array($field_widget_complete_form["widget"])) {
      $field_widget_complete_form["widget"]['#widget-type'] = $context['widget']->getPluginId();
      foreach (Element::children($field_widget_complete_form['widget']) as $key) {
        $field_widget_complete_form['widget'][$key]['#widget-type'] = $context['widget']->getPluginId();
        foreach (Element::children($field_widget_complete_form['widget'][$key]) as $child_key) {
          $field_widget_complete_form['widget'][$key][$child_key]['#widget-type'] = $context['widget']->getPluginId();
        }
      }
    }
  }

  /**
   * Implements hook_theme_suggestions_alter().
   */
  #[Hook('theme_suggestions_alter')]
  public function themeSuggestionsAlter(array &$suggestions, array $variables): void {
    // Add widget type to theme suggestions.
    if (\in_array($variables["theme_hook_original"], [
      'container',
      'field_multiple_value_form',
      'fieldset',
    ]) && !empty($variables["element"]["#widget-type"])) {
      if ($variables["theme_hook_original"] === 'fieldset') {
        $suggestions[] = $variables["theme_hook_original"] . '__widget_' . \str_replace('-', '_', $variables["element"]["#widget-type"]);
      }
      else {
        $suggestions[] = $variables["theme_hook_original"] . '__' . \str_replace('-', '_', $variables["element"]["#widget-type"]);
      }
    }
    elseif (!empty($variables["element"]["#widget-type"])) {
      $suggestions[] = $variables["theme_hook_original"] . '__inwidget_' . \str_replace('-', '_', $variables["element"]["#widget-type"]);
    }
  }

}
