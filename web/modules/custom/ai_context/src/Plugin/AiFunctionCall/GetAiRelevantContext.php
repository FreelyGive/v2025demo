<?php

declare(strict_types=1);

namespace Drupal\ai_context\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_context\Service\AiContextSelector;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns the most relevant AI Contexts for a task and optional agent.
 */
#[FunctionCall(
  id: 'ai_context:get_relevant_contexts',
  function_name: 'ai_context_get_relevant_contexts',
  name: 'Get Relevant AI Contexts',
  description: 'Selects and returns site context for a given task using configured pools and router strategies.',
  group: 'context_tools',
  context_definitions: [
    'task' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Task description'),
      description: new TranslatableMarkup('The current task or user prompt to select context for.'),
      required: TRUE,
    ),
    'agent_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Agent ID'),
      description: new TranslatableMarkup('Agent ID whose context pool to use; if empty, all contexts are considered.'),
      required: FALSE,
    ),
    'max_contexts' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Max contexts'),
      description: new TranslatableMarkup('Optional override for maximum number of contexts to return.'),
      required: FALSE,
    ),
    'always_include' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Always include IDs'),
      description: new TranslatableMarkup('Optional list of ai_context IDs to always include first.'),
      required: FALSE,
    ),
  ],
)]
final class GetAiRelevantContext extends FunctionCallBase implements ExecutableFunctionCallInterface, ContainerFactoryPluginInterface {

  /**
   * The high-level selector service.
   */
  protected AiContextSelector $selector;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): \Drupal\ai\Service\FunctionCalling\FunctionCallInterface|static {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->selector = $container->get('ai_context.selector');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $task = (string) $this->getContextValue('task');
    $agent_id = (string) ($this->getContextValue('agent_id') ?? '');
    $max_override = $this->getContextValue('max_contexts');
    $max_override = is_numeric($max_override) ? (int) $max_override : NULL;
    $always_param = (array) ($this->getContextValue('always_include') ?? []);

    $result = $this->selector->select($task, $agent_id, $always_param, $max_override);
    if (empty($result['ids'])) {
      $this->setOutput('No relevant AI contexts found.');
      return;
    }
    $output = "Selected AI Context IDs: " . implode(', ', $result['ids']) . "\n\n" . (string) $result['text'];
    $this->setOutput($output);
  }
}
