<?php

namespace Drupal\canvas_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\canvas_ai\CanvasAiPermissions;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Function call plugin to get single component context.
 *
 * This plugin retrieves information about one component in the site
 * using the CanvasPageBuilderHelper service. The information can be used by AI
 * agents to understand available components and their capabilities.
 *
 * @internal
 */
#[FunctionCall(
  id: 'canvas_ai:get_single_component_context',
  function_name: 'get_single_component_context',
  name: 'Get Single Component Context',
  description: 'This method gets information about a specific component in the site.',
  group: 'information_tools',
  module_dependencies: ['canvas_ai'],
  context_definitions: [
    'component' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Component"),
      description: new TranslatableMarkup("Optional id of a specific component to get information about. If not provided, information about all components will be returned."),
      required: FALSE,
    ),
  ],
)]
final class GetSingleComponentContext extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The Canvas page builder helper service.
   *
   * @var \Drupal\canvas_ai\CanvasAiPageBuilderHelper
   */
  protected CanvasAiPageBuilderHelper $pageBuilderHelper;

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
    $instance->pageBuilderHelper = $container->get('canvas_ai.page_builder_helper');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // Get the context values.
    $component = $this->getContextValue('component') ?? '';
    // Make sure that the user has the right permissions.
    if (!$this->currentUser->hasPermission(CanvasAiPermissions::USE_CANVAS_AI)) {
      throw new \Exception('The current user does not have the right permissions to run this tool.');
    }
    $data = Yaml::parse($this->pageBuilderHelper->getComponentContextForAi());
    if (!empty($component)) {
      // If a specific component is requested, filter to just that one.
      foreach ($data as $id => $component_data) {
        if ($component_data['id'] !== $component) {
          unset($data[$id]);
        }
      }
    }
    $this->setOutput(Yaml::dump($data, 10, 2));
  }
}
