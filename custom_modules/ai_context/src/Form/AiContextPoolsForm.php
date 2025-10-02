<?php

declare(strict_types=1);

namespace Drupal\ai_context\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class AiContextPoolsForm extends ConfigFormBase {

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
    return 'ai_context_pools_overview_form';
  }

  protected function getEditableConfigNames(): array {
    return ['ai_context.agent_pools'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $agents = $this->entityTypeManager->getStorage('ai_agent')->loadMultiple();
    $pools = $this->config('ai_context.agent_pools')->get('agents') ?? [];

    // Index pools by agent ID for quick lookup
    $pools_by_agent = [];
    foreach ($pools as $pool) {
      if (!empty($pool['id'])) {
        $pools_by_agent[$pool['id']] = $pool;
      }
    }

    $form['description'] = [
      '#type' => 'item',
      '#markup' => '<p>' . $this->t('Configure AI Context assignments for each agent. Contexts provide additional information to agents at runtime.') . '</p>',
    ];

    $header = [
      $this->t('Agent'),
      $this->t('Context Summary'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($agents as $agent) {
      $agent_id = $agent->id();
      $pool = $pools_by_agent[$agent_id] ?? [];

      // Get context counts.
      $assigned_contexts = $pool['contexts'] ?? [];
      $always_include = $pool['always_include'] ?? [];
      $assigned_count = count($assigned_contexts);
      $always_count = count($always_include);

      // Build context summary.
      $summary_parts = [];
      if ($assigned_count > 0) {
        $summary_parts[] = $this->t('@count contexts assigned', ['@count' => $assigned_count]);
      }
      if ($always_count > 0) {
        $summary_parts[] = $this->t('@count always included', ['@count' => $always_count]);
      }

      $summary = !empty($summary_parts) ? implode(', ', $summary_parts) : $this->t('No contexts assigned');

      $row = [
        'data' => [
          'agent' => [
            'data' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['agent-info']],
              'name' => [
                '#markup' => '<strong>' . $agent->label() . '</strong>',
              ],
              'id' => [
                '#markup' => '<div class="description">' . $agent->id() . '</div>',
              ],
            ],
          ],
          'summary' => [
            'data' => [
              '#type' => 'container',
              '#attributes' => [],
              '#markup' => $summary,
            ],
          ],
          'operations' => [
            'data' => Link::createFromRoute(
              $this->t('Configure contexts'),
              'ai_context.agent_pool_edit',
              ['ai_agent' => $agent_id],
              ['attributes' => ['class' => ['button', 'button--small']]]
            ),
          ],
        ],
      ];

      $rows[] = $row;
    }

    $form['agents'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No AI Agents found. You need to create AI Agents before configuring their contexts.'),
    ];

    // Don't show submit button since this is just a listing
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // No submission needed for overview form
  }

}
