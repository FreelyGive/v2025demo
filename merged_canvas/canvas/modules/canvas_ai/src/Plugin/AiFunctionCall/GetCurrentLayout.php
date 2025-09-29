<?php

namespace Drupal\canvas_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\canvas_ai\CanvasAiPermissions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Function call plugin to get the current layout.
 *
 * This plugin retrieves the current layout from the tempstore.
 * The layout information can be used by AI agents to understand and manipulate
 * the current page structure.
 *
 * @internal
 */
#[FunctionCall(
  id: 'canvas_ai:get_current_layout',
  function_name: 'get_current_layout',
  name: 'Get Current Layout',
  description: 'Gets the current layout stored in the system.',
  group: 'information_tools',
)]
final class GetCurrentLayout extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The Canvas AI tempstore service.
   *
   * @var \Drupal\canvas_ai\CanvasAiTempStore
   */
  protected CanvasAiTempStore $canvasAiTempStore;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface | static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->canvasAiTempStore = $container->get('canvas_ai.tempstore');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // Make sure that the user has the right permissions.
    if (!$this->currentUser->hasPermission(CanvasAiPermissions::USE_CANVAS_AI)) {
      throw new \Exception('The current user does not have the right permissions to run this tool.');
    }
    $current_layout = $this->canvasAiTempStore->getData(CanvasAiTempStore::CURRENT_LAYOUT_KEY);
    $this->setOutput($current_layout ? (string) $current_layout : 'No layout currently stored.');
  }

}
