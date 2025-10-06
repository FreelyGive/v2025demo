<?php

declare(strict_types=1);

namespace Drupal\ai_context\Plugin\AiFunctionCall;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\ai_agents\Plugin\AiFunctionCall\AiAgentWrapper as BaseWrapper;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai_context\Service\AiContextSelector;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Wrapper that injects selected AI Context into sub-agent tokens before run.
 */
final class AiAgentWrapperWithContext extends BaseWrapper implements ExecutableFunctionCallInterface, ContainerFactoryPluginInterface {

  public function __construct(
    $configuration,
    $plugin_id,
    $plugin_definition,
    ContextDefinitionNormalizer $context_definition,
    AiAgentManager $aiAgentManager,
    AiProviderPluginManager $aiProvider,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly AiContextSelector $selector,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $context_definition, $aiAgentManager, $aiProvider, $entityTypeManager);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_agents'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('ai_context.selector'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    // Attempt to inject context tokens before delegating.
    try {
      $agentId = (string) ($this->pluginDefinition['function_name'] ?? '');
      $prompt = (string) ($this->getContextValue('prompt') ?? '');
      if ($agentId && $prompt) {
        $result = $this->selector->select($prompt, $agentId);
        if (!empty($result['ids'])) {
          $existing = $this->tokens ?? [];
          $this->setTokens($existing + ['ai_context' => [
            'ids' => $result['ids'],
            'render' => (string) $result['text'],
          ]]);
        }
      }
    }
    catch (\Throwable $e) {
      // Non-fatal; proceed without injected context.
    }

    // Proceed with normal behavior.
    parent::execute();
  }
}
