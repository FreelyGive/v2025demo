<?php

declare(strict_types=1);

namespace Drupal\canvas_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the image upload size.
 */
final class CanvasAiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'canvas_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['canvas_ai.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['file_upload_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum upload size'),
      '#default_value' => $this->config('canvas_ai.settings')->get('file_upload_size'),
      '#description' => $this->t('Maximum image upload size (MB)'),
      '#min' => 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('canvas_ai.settings')
      ->set('file_upload_size', $form_state->getValue('file_upload_size'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
