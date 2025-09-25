<?php

namespace Drupal\canvas_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\canvas_ai\CanvasAiPermissions;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

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
  id: 'canvas_ai:get_canvas_page',
  function_name: 'get_canvas_page',
  name: 'Get Canvas Page',
  description: 'Gets the canvas page layout stored in the system.',
  group: 'information_tools',
  context_definitions: [
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("ID"),
      description: new TranslatableMarkup("The ID of the page to get the setup for."),
      required: FALSE
    ),
  ],
)]
final class GetCanvasPage extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
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
    // Get the input values.
    $id = $this->getContextValue('id') ?? '';
    if (empty($id)) {
      throw new \Exception('No ID provided.');
    }
    // Load the page entity type.
    $page_storage = $this->entityTypeManager->getStorage('canvas_page');
    $page = $page_storage->load($id);
    if (!$page) {
      throw new \Exception('No page found for the provided ID.');
    }
    $output = $page->toArray();
    $layout = [];
    // We need to go through each component and convert possible UUID to ID.
    if (!empty($output['components'])) {
      foreach ($output['components'] as $key => $component) {
        // Decode inputs.
        $inputs = Json::decode($component['inputs']);
        // Ugly solution, thinks all inputs are media.
        foreach ($inputs as $input_key => $input_value) {
          if (is_array($input_value) && !empty($input_value['target_uuid'])) {
            $media_storage = $this->entityTypeManager->getStorage('media');
            $media = $media_storage->loadByProperties(['uuid' => $input_value['target_uuid']]);
            if (!empty($media)) {
              $media = reset($media);
              $inputs[$input_key]['target_id'] = $media->id();
              unset($inputs[$input_key]['target_uuid']);
            }
          }
        }
        $output['components'][$key]['inputs'] = Json::encode($inputs);

        // If its a parent, we put it directly in the layout.
        if (empty($component['parent_uuid'])) {
          $layout[$component['uuid']] = $component;
        }
        else {
          $layout[$component['parent_uuid']][$component['slot']][] = $component;
        }
      }

    }
    $this->setOutput(Yaml::dump($layout, 10, 2) ?? 'No layout currently stored.');
  }

}
