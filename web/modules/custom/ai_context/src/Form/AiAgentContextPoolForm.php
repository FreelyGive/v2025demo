<?php

declare(strict_types=1);

namespace Drupal\ai_context\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class AiAgentContextPoolForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  public function getFormId(): string {
    return 'ai_agent_context_pool_form';
  }

  protected function getEditableConfigNames(): array {
    return ['ai_context.agent_pools'];
  }

  public function getTitle($ai_agent = NULL): TranslatableMarkup {
    if (!$ai_agent) {
      return $this->t('Configure contexts');
    }

    $agent_storage = $this->entityTypeManager->getStorage('ai_agent');
    $agent = $agent_storage->load($ai_agent);

    if (!$agent) {
      return $this->t('Configure contexts');
    }

    return $this->t('Configure contexts for @agent', [
      '@agent' => $agent->label(),
    ]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, $ai_agent = NULL): array {
    if (!$ai_agent) {
      $this->messenger()->addError($this->t('No agent specified.'));
      return $form;
    }

    // Load the agent entity
    $agent_storage = $this->entityTypeManager->getStorage('ai_agent');
    $agent = $agent_storage->load($ai_agent);

    if (!$agent) {
      $this->messenger()->addError($this->t('Agent not found.'));
      return $form;
    }

    // Load contexts and existing configuration
    $context_storage = $this->entityTypeManager->getStorage('ai_context');
    $contexts = $context_storage->loadMultiple();
    $pools = $this->config('ai_context.agent_pools')->get('agents') ?? [];

    // Find current agent's configuration
    $current_config = [];
    foreach ($pools as $pool) {
      if ($pool['id'] === $ai_agent) {
        $current_config = $pool;
        break;
      }
    }

    // Prepare contexts with their tags for table display
    $context_rows = [];
    foreach ($contexts as $context) {
      $context_id = $context->id();
      $tags = $context->get('tags') ?? [];

      // Get tag terms and labels
      $tag_terms = [];
      if (!empty($tags)) {
        $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($tags);
        foreach ($terms as $term) {
          $tag_terms[] = $term;
        }
      }

      // Determine current inclusion type
      $inclusion_type = 'none';
      if (in_array($context_id, $current_config['always_include'] ?? [])) {
        $inclusion_type = 'always';
      } elseif (in_array($context_id, $current_config['contexts'] ?? [])) {
        $inclusion_type = 'relevance';
      }

      $context_rows[$context_id] = [
        'context' => $context,
        'tag_terms' => $tag_terms,
        'inclusion_type' => $inclusion_type,
      ];
    }

    // Build form with standard Drupal classes
    $agent_description = $agent->get('description') ?? '';

    if (!empty($agent_description)) {
      $form['agent_info'] = [
        '#type' => 'item',
        '#markup' => '<div class="description">' . $agent_description . '</div>',
      ];
    }

    $form['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Help'),
      '#open' => FALSE,
      'content' => [
        '#markup' => '<p>' . $this->t('<strong>Not included:</strong> The context will not be provided to this agent.') . '</p>'
          . '<p>' . $this->t('<strong>Include when relevant:</strong> The context will be provided when it matches the current task or user request.') . '</p>'
          . '<p>' . $this->t('<strong>Always include:</strong> The context will always be provided to the agent, regardless of the task.') . '</p>',
      ],
    ];

    // Build contexts table
    $form['contexts_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Context'),
        $this->t('Description'),
        $this->t('Tags'),
        $this->t('Inclusion'),
      ],
      '#empty' => $this->t('No contexts available.'),
    ];

    // Add context rows to table
    foreach ($context_rows as $context_id => $row_data) {
      $context = $row_data['context'];
      $tag_terms = $row_data['tag_terms'];
      $inclusion_type = $row_data['inclusion_type'];

      // Context name column: link to the context edit form.
      $form['contexts_table'][$context_id]['context'] = [
        '#type' => 'item',
        '#markup' => Link::fromTextAndUrl(
          $context->label(),
          Url::fromRoute('entity.ai_context.edit_form', ['ai_context' => $context_id])
        )->toString(),
      ];

      // Description column
      $context_description = $context->get('description');
      $form['contexts_table'][$context_id]['description'] = [
        '#type' => 'item',
        '#markup' => !empty($context_description) ? '<span class="description">' . $context_description . '</span>' : '<em>' . $this->t('No description') . '</em>',
      ];

      // Tags column with commas
      $tags_markup = '';
      if (!empty($tag_terms)) {
        $tag_labels = [];
        foreach ($tag_terms as $term) {
          $tag_labels[] = '<span class="description">' . $term->label() . '</span>';
        }
        $tags_markup = implode(', ', $tag_labels);
      }
      $form['contexts_table'][$context_id]['tags'] = [
        '#type' => 'item',
        '#markup' => $tags_markup ?: '<em>' . $this->t('No tags') . '</em>',
      ];

      // Inclusion type dropdown
      $form['contexts_table'][$context_id]['inclusion'] = [
        '#type' => 'select',
        '#options' => [
          'none' => $this->t('Not included'),
          'relevance' => $this->t('Include when relevant'),
          'always' => $this->t('Always include'),
        ],
        '#default_value' => $inclusion_type,
        '#attributes' => ['class' => ['context-inclusion-select']],
      ];
    }

    // Store agent ID for form submission
    $form['agent_id'] = [
      '#type' => 'value',
      '#value' => $ai_agent,
    ];

    // Add custom styling
    $form['#attached']['library'][] = 'ai_context/admin';

    return parent::buildForm($form, $form_state);
  }


  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $agent_id = $form_state->getValue('agent_id');
    $pools = $this->config('ai_context.agent_pools')->get('agents') ?? [];

    // Collect contexts from table select values
    $contexts_table = $form_state->getValue('contexts_table');
    $all_contexts = [];
    $all_always_include = [];

    foreach ($contexts_table as $context_id => $row_values) {
      $inclusion_type = $row_values['inclusion'];

      switch ($inclusion_type) {
        case 'relevance':
          $all_contexts[] = $context_id;
          break;
        case 'always':
          $all_always_include[] = $context_id;
          break;
        // 'none' case - do nothing, context won't be included
      }
    }

    // Update or add the agent's configuration
    $found = FALSE;
    foreach ($pools as &$pool) {
      if ($pool['id'] === $agent_id) {
        $pool['contexts'] = array_values($all_contexts);
        $pool['always_include'] = array_values($all_always_include);
        $found = TRUE;
        break;
      }
    }

    // If not found, add new configuration
    if (!$found) {
      $pools[] = [
        'id' => $agent_id,
        'contexts' => array_values($all_contexts),
        'always_include' => array_values($all_always_include),
      ];
    }

    $this->configFactory->getEditable('ai_context.agent_pools')
      ->set('agents', $pools)
      ->save();

    $this->messenger()->addStatus($this->t('Context configuration saved for @agent.', [
      '@agent' => $agent_id,
    ]));

    // Redirect back to the pools list
    $form_state->setRedirect('ai_context.pools');
  }

}
