<?php

declare(strict_types=1);

namespace Drupal\canvas_personalization\Plugin\Condition;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @phpstan-type UtmParameterPluginSetting array{key: string, value: string, matching: 'exact'|'starts_with'}
 * @phpstan-type UtmParameterPluginSettings array{all: bool, parameters: array<string, UtmParameterPluginSetting>}
 */
#[Condition(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('UTM Parameters'),
)]
final class UtmParameters extends ConditionPluginBase {

  public const string PLUGIN_ID = 'utm_parameters';

  public const string UTM_ID = 'utm_id';
  public const string UTM_SOURCE = 'utm_source';
  public const string UTM_MEDIUM = 'utm_medium';
  public const string UTM_CAMPAIGN = 'utm_campaign';
  public const string UTM_TERM = 'utm_term';
  public const string UTM_CONTENT = 'utm_content';
  public const string CUSTOM = 'custom';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'parameters' => [],
      'all' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['parameters'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $parameter_options = [
      self::UTM_ID => $this->t('ID', ['context' => 'UTM Parameters']),
      self::UTM_SOURCE => $this->t('Source', ['context' => 'UTM Parameters']),
      self::UTM_MEDIUM => $this->t('Medium', ['context' => 'UTM Parameters']),
      self::UTM_CAMPAIGN => $this->t('Campaign', ['context' => 'UTM Parameters']),
      self::UTM_TERM => $this->t('Term', ['context' => 'UTM Parameters']),
      self::UTM_CONTENT => $this->t('Content', ['context' => 'UTM Parameters']),
      self::CUSTOM => $this->t('Custom', ['context' => 'UTM Parameters']),
    ];
    $form['parameters']['_new_parameter'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['parameters']['_new_parameter']['key'] = [
      '#type' => 'select',
      '#title' => $this->t('Parameter', ['context' => 'UTM Parameters']),
      '#options' => $parameter_options,
    ];
    $form['parameters']['_new_parameter']['custom_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Parameter', ['context' => 'UTM Parameters']),
      '#states' => [
        'visible' => [
          'select[name="settings[parameters][_new_parameter][key]"]' => ['value' => 'custom'],
        ],
      ],
    ];
    $form['parameters']['_new_parameter']['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value', ['context' => 'UTM Parameters']),
    ];
    $form['parameters']['_new_parameter']['matching'] = [
      '#type' => 'select',
      '#options' => [
        'exact' => $this->t('Exact matching', ['context' => 'UTM Parameters']),
        'starts_with' => $this->t('Starts with', ['context' => 'UTM Parameters']),
      ],
      '#default_value' => 'exact',
      '#required' => TRUE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate in https://www.drupal.org/i/3527075
    parent::validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // @todo this condition should be able to support multiple parameters.
    //   Revisiting in https://www.drupal.org/i/3527075

    $newValue = $form_state->getValue(['parameters', '_new_parameter']);
    $this->configuration['parameters'] = $form_state->getValue('example');
    $key = $newValue['key'] === 'custom' ? $newValue['custom_key'] : $newValue['key'];
    $this->configuration['parameters'][] = [
      'key' => rawurlencode($key),
      'value' => rawurlencode($newValue['value']),
      'matching' => $newValue['matching'],
    ];
    $this->configuration['all'] = TRUE;
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): string {
    $parameterSummary = '';
    $parameters = $this->configuration['parameters'];
    foreach ($parameters as $parameter) {
      // @todo Use matching for the summary in https://www.drupal.org/i/3527075
      $key = Xss::filter($parameter['key']);
      $value = Xss::filter($parameter['value']);
      $parameterSummary .= "$key=$value";
    }

    return (string) $this->t(
      'UTM Parameters: @parameters', ['@parameters' => $parameterSummary],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    if (empty($this->configuration['parameters'])) {
      return TRUE;
    }
    // @todo Verify this is how it will need to work, and if it should use a
    //   context instead in https://www.drupal.org/i/3525795.
    // @phpstan-ignore-next-line
    $request = \Drupal::request();
    $result = TRUE;
    foreach ($this->configuration['parameters'] as $parameter) {
      $requestParamValue = $request->get($parameter['key'], NULL);
      $result &= match($parameter['matching']) {
        'exact' => $parameter['value'] === $requestParamValue,
        'starts_with' => str_starts_with($requestParamValue, $parameter['value']),
        default => throw new \LogicException(sprintf('Unknown matching for condition %s', $this->pluginId)),
      };
    }
    return (bool) ($this->configuration['negate'] ? !$result : $result);
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]
   */
  public function getContextDefinitions(): array {
    // @todo Implement context definitions in https://www.drupal.org/i/3525795.
    return parent::getContextDefinitions();
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string>
   */
  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();
    foreach ($this->configuration['parameters'] as $parameter) {
      $contexts[] = 'url.query_args:' . $parameter['key'];
    }
    return $contexts;
  }

}
