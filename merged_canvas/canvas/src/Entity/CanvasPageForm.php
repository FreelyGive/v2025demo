<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the CanvasPage entity forms.
 *
 * Overrides the default form to add SEO settings and other customizations.
 */
final class CanvasPageForm extends ContentEntityForm {

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $group = 'seo_settings';
    $form[$group] = [
      '#type' => 'details',
      '#group' => 'advanced',
      '#weight' => -10,
      '#title' => $this->t('SEO settings'),
      '#tree' => TRUE,
      // Not keeping it open messes up media library ajax
      // when rendering the form in Canvas UI.
      // @todo remove this once https://www.drupal.org/project/canvas/issues/3501626 lands.
      '#open' => TRUE,
    ];
    $form[$group]['image'] = $form['image'];
    $form[$group]['image']['#weight'] = -10;
    // TRICKY: it seems there's a Drupal core bug wrt #group, long-term fix TBD.
    // @see https://git.drupalcode.org/project/canvas/-/merge_requests/501#note_448716
    unset($form['image']);
    $form[$group]['description'] = $form['description'];
    $form[$group]['description']['#weight'] = 10;
    unset($form['description']);
    if (isset($form['metatags']['widget'][0]['basic']['title'])) {
      $form['metatags']['widget'][0]['basic']['title']['#group'] = $group;
    }

    $this->addTransliterationSettings($form);
    $this->customizePathField($form);

    return $form;
  }

  /**
   * Adds transliteration language settings to the form.
   *
   * @param array $form
   *   The form array to modify.
   */
  private function addTransliterationSettings(array &$form): void {
    $current_language = $this->languageManager->getCurrentLanguage();
    $langcode = $current_language->getId();
    $form['#attached']['drupalSettings']['langcode'] = $langcode;

    // Get language overrides from the transliteration service.
    $overrides = $this->getTransliterationLanguageOverrides($langcode);
    $form['#attached']['drupalSettings']['transliteration_language_overrides'] = [$langcode => $overrides];
  }

  /**
   * Customizes the path field appearance and position.
   *
   * Moves the path field out of advanced settings and positions it after the
   * title.
   *
   * @param array $form
   *   The form array to modify.
   */
  private function customizePathField(array &$form): void {
    // Remove the details wrapper from the path widget to make it a direct form
    // element.
    if (isset($form['path']['widget'][0]['#type']) && $form['path']['widget'][0]['#type'] === 'details') {
      $form['path']['widget'][0]['#type'] = 'container';
      // Remove unnecessary attributes from the container.
      unset($form['path']['widget'][0]['#open']);
      unset($form['path']['widget'][0]['#title']);
    }

    // Remove path field from the advanced group to position it in the main
    // form.
    unset($form['path']['#group']);

    // Also unset group at the widget level, which is where Path module may add
    // it.
    unset($form['path']['widget'][0]['#group']);

    // Set a low positive weight to place it just after the title field.
    $form['path']['#weight'] = 5;
  }

  /**
   * Gets transliteration language overrides for a language.
   *
   * This is duplicating
   * \Drupal\Core\Render\Element\MachineName::getTransliterationLanguageOverrides
   * because it's not available as an API.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return array
   *   The language overrides.
   *
   * @see \Drupal\Core\Transliteration\PhpTransliteration::readLanguageOverrides()
   * @see \Drupal\Core\Render\Element\MachineName::getTransliterationLanguageOverrides
   */
  private function getTransliterationLanguageOverrides(string $langcode): array {
    $overrides = &drupal_static(__CLASS__ . '_' . __METHOD__, []);

    if (isset($overrides[$langcode])) {
      return $overrides[$langcode];
    }
    // This is where the data files are stored.
    $data_directory = DRUPAL_ROOT . '/core/lib/Drupal/Component/Transliteration/data';
    $file = $data_directory . '/' . preg_replace('/[^a-zA-Z\-]/', '', $langcode) . '.php';

    $overrides[$langcode] = [];
    if (is_file($file)) {
      include $file;
    }

    $this->moduleHandler->alter('transliteration_overrides', $overrides[$langcode], $langcode);

    return [$langcode => $overrides[$langcode]];
  }

}
