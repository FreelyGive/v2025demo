<?php

declare(strict_types=1);

namespace Drupal\canvas_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\canvas\Entity\PageRegion;

/**
 * Configure Canvas AI settings for this site.
 */
final class CanvasAIThemeRegionSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'canvas_ai_theme_region_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['canvas_ai.theme_region.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('canvas_ai.theme_region.settings');
    $active_regions = $this->getActiveRegions();
    $form['#tree'] = TRUE;

    if (empty($active_regions)) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => $this->t("You don't have any global regions enabled in your theme."),
      ];
      return $form;
    }

    $form['message'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Use this form to give proper descriptions for all the Global regions, which will be used by AI to generate content for those regions.'),
    ];

    $descriptions = $config->get('region_descriptions') ?? [];

    foreach ($active_regions as $region) {
      $region_id = $this->getRegionId($region);
      $form[$region_id] = [
        '#type' => 'details',
        '#title' => $region->label(),
        '#open' => TRUE,
      ];
      $form[$region_id]['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#description' => $this->t('Provide a description for what kind of content should be placed in this region.'),
        '#default_value' => $descriptions[$region_id] ?? '',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $active_regions = $this->getActiveRegions();

    $descriptions = [];
    foreach ($active_regions as $region) {
      $region_id = $this->getRegionId($region);
      $descriptions[$region_id] = $form_state->getValue([$region_id, 'description']);
    }

    $this->config('canvas_ai.theme_region.settings')
      ->set('region_descriptions', $descriptions)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get active theme regions.
   *
   * @return array
   *   An array of active theme regions.
   */
  protected function getActiveRegions(): array {
    $regions = PageRegion::loadMultiple();
    return array_filter($regions, fn($region) => $region->status());
  }

  /**
   * Get region ID.
   *
   * @param \Drupal\canvas\Entity\PageRegion $region
   *   The page region.
   *
   * @return string
   *   The region ID.
   */
  protected function getRegionId(PageRegion $region): string {
    $region_id = $region->id();
    // Remove the theme prefix.
    if (str_contains($region_id, '.')) {
      $parts = explode('.', $region_id, 2);
      return $parts[1] ?? $region_id;
    }
    return $region_id;
  }

}
