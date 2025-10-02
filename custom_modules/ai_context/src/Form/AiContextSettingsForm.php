<?php

declare(strict_types=1);

namespace Drupal\ai_context\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class AiContextSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected AiProviderPluginManager $aiProvider,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('ai.provider'),
    );
  }

  public function getFormId(): string {
    return 'ai_context_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['ai_context.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ai_context.settings');

    $form['strategy'] = [
      '#type' => 'select',
      '#title' => $this->t('Selection strategy'),
      '#options' => [
        'keyword' => $this->t('Keyword (default)'),
        'llm' => $this->t('LLM-assisted (requires provider/model)'),
      ],
      '#default_value' => $config->get('strategy') ?? 'keyword',
    ];

    $form['max_contexts'] = [
      '#type' => 'number',
      '#title' => $this->t('Max contexts to inject'),
      '#default_value' => $config->get('max_contexts') ?? 3,
      '#min' => 1,
      '#max' => 10,
    ];

    $form['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('Token budget for injection'),
      '#default_value' => $config->get('max_tokens') ?? 1200,
      '#min' => 100,
      '#description' => $this->t('Estimated tokens reserved for context block appended to sub-agent system prompts.'),
    ];

    // Build provider/model options for the 'chat' operation.
    // This is broadly supported and sufficient for LLM-assisted routing.
    $options = $this->aiProvider->getSimpleProviderModelOptions('chat', TRUE, TRUE, []);
    $selected_combo = '';
    $saved_provider = (string) ($config->get('provider_id') ?? '');
    $saved_model = (string) ($config->get('model_id') ?? '');
    if ($saved_provider && $saved_model) {
      $candidate = $saved_provider . '__' . $saved_model;
      if (isset($options[$candidate])) {
        $selected_combo = $candidate;
      }
    }
    $form['provider_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred Provider/Model (LLM)'),
      '#options' => $options,
      '#default_value' => $selected_combo,
      '#states' => [
        'visible' => [
          ':input[name="strategy"]' => ['value' => 'llm'],
        ],
      ],
      '#description' => $this->t('Lists configured providers and their models for the chat_with_tools operation.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $strategy = (string) $form_state->getValue('strategy');
    $provider_id = '';
    $model_id = '';
    if ($strategy === 'llm') {
      $combo = (string) $form_state->getValue('provider_model');
      if ($combo) {
        $parts = explode('__', $combo);
        if (count($parts) === 2) {
          $provider_id = $parts[0];
          $model_id = $parts[1];
        }
      }
    }

    $this->configFactory->getEditable('ai_context.settings')
      ->set('strategy', $strategy)
      ->set('max_contexts', (int) $form_state->getValue('max_contexts'))
      ->set('max_tokens', (int) $form_state->getValue('max_tokens'))
      ->set('provider_id', $provider_id)
      ->set('model_id', $model_id)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
