<?php

namespace Drupal\canvas_ai\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_agents\Enum\AiAgentStatusItemTypes;
use Drupal\ai_agents\Plugin\AiFunctionCall\AiAgentWrapper;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_agents\Service\AgentStatus\Interfaces\AiAgentStatusPollerServiceInterface;
use Drupal\ai_agents\Service\AgentStatus\UpdateItems\TextGenerated;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\canvas_ai\Plugin\AiFunctionCall\AddMetadata;
use Drupal\canvas_ai\Plugin\AiFunctionCall\CreateComponent;
use Drupal\canvas_ai\Plugin\AiFunctionCall\EditComponentJs;
use Drupal\canvas_ai\Plugin\AiFunctionCall\CreateFieldContent;
use Drupal\canvas_ai\Plugin\AiFunctionCall\EditFieldContent;
use Drupal\canvas_ai\Plugin\AiFunctionCall\SetAIGeneratedComponentStructure;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\canvas_ai\Plugin\AiFunctionCall\SetAIGeneratedTemplateData;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Yaml\Yaml;

/**
 * Renders the Drupal Canvas AI calls.
 */
final class CanvasBuilder extends ControllerBase {

  /**
   * Constructs a new CanvasBuilder object.
   */
  public function __construct(
    protected AiProviderPluginManager $providerService,
    protected PluginManagerInterface $agentManager,
    protected CsrfTokenGenerator $csrfTokenGenerator,
    protected CanvasAiPageBuilderHelper $canvasAiPageBuilderHelper,
    protected CanvasAiTempStore $canvasAiTempStore,
    protected FileSystemInterface $fileSystem,
    protected AiAgentStatusPollerServiceInterface $poller,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('plugin.manager.ai_agents'),
      $container->get('csrf_token'),
      $container->get('canvas_ai.page_builder_helper'),
      $container->get('canvas_ai.tempstore'),
      $container->get('file_system'),
      $container->get('ai_agents.agent_status_poller'),
    );
  }

  /**
   * Renders the Drupal Canvas AI calls.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function render(Request $request): JsonResponse {
    $token = $request->headers->get('X-CSRF-Token') ?? '';
    if (!$this->csrfTokenGenerator->validate($token, 'canvas_ai.canvas_builder')) {
      throw new AccessDeniedHttpException('Invalid CSRF token');
    }

    /** @var \Drupal\ai_agents\PluginBase\AiAgentEntityWrapper $agent */
    $agent = $this->agentManager->createInstance('canvas_ai_orchestrator');
    $contentType = $request->getContentTypeFormat();
    $files = [];
    if ($contentType === 'json') {
      $prompt = Json::decode($request->getContent());
    }
    else {
      $prompt = $request->request->all();
      $files = $request->files->all();
      $prompt['derived_proptypes'] = Json::decode($prompt['derived_proptypes']);
    }
    // If $prompt['messages'] is missing or invalid, this code reconstructs it
    // by scanning for keys named 'message <number>', and
    // assembling them into an ordered 'messages' array, while cleaning up old keys
    // as we use $prompt['messages'] for further processing .
    if (!isset($prompt['messages']) || !is_array($prompt['messages'])) {
      $messages = [];
      $keys_to_remove = [];
      foreach ($prompt as $key => $value) {
        if (preg_match('/^message(\d+)$/', $key, $matches)) {
          $num = (int) $matches[1];
          $decoded = Json::decode($value);
          if ($decoded !== NULL) {
            $messages[$num] = $decoded;
            $keys_to_remove[] = $key;
          }
        }
      }
      if (!empty($messages)) {
        ksort($messages);
        $prompt['messages'] = array_values($messages);
        foreach ($keys_to_remove as $key) {
          unset($prompt[$key]);
        }
      }
    }
    $image_files = [];
    foreach ($files as $file) {
      $allowed_image_types = ['image/jpeg', 'image/png'];
      $mime_type = $file->getClientMimeType();

      if (!in_array($mime_type, $allowed_image_types, TRUE)) {
        return new JsonResponse([
          'status' => FALSE,
          'message' => 'Only image files are allowed (jpeg, png, jpg).',
        ]);
      }
      // Copy the file to the temp directory.
      $filename = $file->getClientOriginalName();
      $tmp_name = 'temporary://' . $filename;
      $this->fileSystem->copy($file->getPathname(), $tmp_name, FileExists::Replace);
      // Create actual file entities.
      $file = $this->entityTypeManager()->getStorage('file')->create([
        'uid' => $this->currentUser()->id(),
        'filename' => $filename,
        'uri' => $tmp_name,
        'status' => 0,
      ]);
      $file->save();
      $binary = file_get_contents($tmp_name);
      if ($binary === FALSE) {
        return new JsonResponse([
          'status' => FALSE,
          'message' => 'An error occurred reading the uploaded file.',
        ]);
      }

      $image_files[] = new ImageFile($binary, $mime_type, $filename);
    }

    if (empty($prompt['messages'])) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'No prompt provided',
      ]);
    }
    $task_message = array_pop($prompt['messages']);
    $agent->setChatInput(new ChatInput([
      new ChatMessage($task_message['role'], $task_message['text'], $image_files),
    ]));

    // Store the current layout in the temp store. This will be later used by
    // the ai agents.
    // @see \Drupal\canvas_ai\Plugin\AiFunctionCall\GetCurrentLayout.
    $current_layout = $prompt['current_layout'] ?? '';
    if (!empty($current_layout)) {
      $this->canvasAiTempStore->setData(CanvasAiTempStore::CURRENT_LAYOUT_KEY, Json::encode($current_layout));
    }

    $task = $prompt['messages'];
    $messages = [];
    foreach ($task as $message) {
      if (!empty($message['files'])) {
        $images = [];
        foreach ($message['files'] as $file_info) {
          if (!empty($file_info['src'])) {
            $binary = @file_get_contents($file_info['src']);
            preg_match('/^data:(.*?);base64,/', $file_info['src'], $matches);
            $mime_type = $matches[1] ?? '';
            if ($binary !== FALSE) {
              $images[] = new ImageFile($binary, $mime_type, 'temp');
            }
          }
        }
        // The text is intentionally kept empty while setting it in comments
        // so that the AI only takes the image as a context/history for the
        // next prompt not any text related to it.
        $messages[] = new ChatMessage($message['role'], '', $images);
        break;
      }
      else {
        if (!empty($message['text'])) {
          $messages[] = new ChatMessage($message['role'] === 'user' ? 'user' : 'assistant', $message['text']);
        }
      }
    }
    $agent->setChatHistory($messages);
    $agent->setProgressThreadId($prompt['request_id']);
    $agent->setDetailedProgressTracking([
      AiAgentStatusItemTypes::Started,
      AiAgentStatusItemTypes::TextGenerated,
      AiAgentStatusItemTypes::Finished,
    ]);
    $default = $this->providerService->getDefaultProviderForOperationType('chat');
    if (!is_array($default) || empty($default['provider_id']) || empty($default['model_id'])) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'No default provider found.',
      ]);
    }
    $config = $this->config('canvas_ai.settings');
    $http_client_options = [
      'timeout' => $config->get('http_client_options.timeout') ?? 60,
    ];
    $provider = $this->providerService->createInstance(
      $default['provider_id'],
      ['http_client_options' => $http_client_options]
    );
    $agent->setAiProvider($provider);
    $agent->setModelName($default['model_id']);
    $agent->setAiConfiguration([]);
    $agent->setCreateDirectly(TRUE);
    $menu_fetch_source = $this->getMenuFetchSource();
    $json_api_module_status = $this->moduleHandler()->moduleExists('jsonapi') ? 'enabled' : 'disabled';
    $agent->setTokenContexts([
      'entity_type' => $prompt['entity_type'] ?? NULL,
      'entity_id' => $prompt['entity_id'] ?? NULL,
      'selected_component' => $prompt['selected_component'] ?? NULL,
      'layout' => $prompt['layout'] ?? NULL,
      'derived_proptypes' => isset($prompt['derived_proptypes']) ? JSON::encode($prompt['derived_proptypes']) : NULL,
      'available_regions' => JSON::encode($this->canvasAiPageBuilderHelper->getAvailableRegions(Json::encode($prompt['current_layout']))) ?? NULL,
      'page_title' => $prompt['page_title'] ?? NULL,
      'page_description' => $prompt['page_description'] ?? NULL,
      'active_component_uuid' => $prompt['active_component_uuid'] ?? 'None',
      'menu_fetch_source' => $menu_fetch_source,
      'json_api_module_status' => $json_api_module_status,
    ]);
    $solvability = $agent->determineSolvability();
    $status = FALSE;
    $message = '';
    $response = [];
    if ($solvability == AiAgentInterface::JOB_NOT_SOLVABLE) {
      $message = 'Something went wrong';
    }
    elseif ($solvability == AiAgentInterface::JOB_SHOULD_ANSWER_QUESTION) {
      $message = $agent->answerQuestion();
    }
    elseif ($solvability == AiAgentInterface::JOB_INFORMS) {
      $message = $agent->inform();
      $status = TRUE;
    }
    elseif ($solvability == AiAgentInterface::JOB_SOLVABLE) {
      $response['status'] = TRUE;
      $tools = $agent->getToolResults(TRUE);
      $map = [
        EditComponentJs::class => ['js_structure', 'props_metadata'],
        CreateComponent::class => ['component_structure'],
        CreateFieldContent:: class => ['created_content'],
        EditFieldContent:: class => ['refined_text'],
        AddMetadata::class => ['metadata'],
        SetAIGeneratedComponentStructure::class => ['operations'],
        SetAIGeneratedTemplateData::class => ['operations'],
      ];
      if (!empty($tools)) {
        foreach ($tools as $tool) {
          foreach ($map as $class => $keys) {
            if ($tool instanceof $class) {
              // @todo Refactor this after https://www.drupal.org/i/3529313 is fixed.
              $output = $tool->getReadableOutput();
              // The SetAIGeneratedTemplateData tool returns JSON instead of
              // YAML, to reduce parsing errors.
              if ($tool instanceof SetAIGeneratedTemplateData) {
                $data = Json::decode($output);
              }
              else {
                $data = Yaml::parse($output);
              }
              foreach ($keys as $key) {
                if (!empty($data[$key])) {
                  $response[$key] = $data[$key];
                }
              }
            }
          }
          if ($tool instanceof AiAgentWrapper) {
            $response['message'] = $tool->getReadableOutput();
          }
          if (in_array($tool->getPluginId(), ['ai_agents::ai_agent::canvas_ai_template_builder_agent', 'ai_agents::ai_agent::canvas_ai_page_builder_agent'])) {
            $this->canvasAiTempStore->deleteData(CanvasAiTempStore::CURRENT_LAYOUT_KEY);
          }
        }
      }
      else {
        $response['message'] = $agent->solve();
      }
      return new JsonResponse(
        $response,
      );
    }
    return new JsonResponse([
      'status' => $status,
      'message' => $message,
    ]);
  }

  /**
   * Function to get the x-csrf-token.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function getCsrfToken(Request $request): Response {
    return new Response($this->csrfTokenGenerator->get('canvas_ai.canvas_builder'));
  }

  /**
   * Function to get the source for menu fetching.
   *
   * @return string
   *   The menu fetch source.
   */
  private function getMenuFetchSource(): string {
    if ($this->moduleHandler()->moduleExists('jsonapi_menu_items')) {
      $menuFetchSource = 'jsonapi_menu_items';
    }
    elseif ($this->config('system.feature_flags')->get('linkset_endpoint') === TRUE) {
      $menuFetchSource = 'linkset';
    }
    elseif ($this->currentUser()->hasPermission('administer site configuration')) {
      $menuFetchSource = 'linkset_not_configured';
    }
    else {
      $menuFetchSource = 'menu_fetching_functionality_not_available';
    }
    return $menuFetchSource;
  }

  /**
   * Poller function to get the AI progress.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function getAiProgress(Request $request): JsonResponse {
    $token = $request->headers->get('X-CSRF-Token') ?? '';
    if (!$this->csrfTokenGenerator->validate($token, 'canvas_ai.canvas_builder')) {
      throw new AccessDeniedHttpException('Invalid CSRF token');
    }

    $progress = $this->poller->getLatestStatusUpdates($request->get('request_id'));
    $items = [];
    $agent_runner_to_agent_id = [];
    $is_finished = FALSE;

    foreach ($progress->getItems() as $event) {
      /** @var \Drupal\ai_agents\Service\AgentStatus\Interfaces\UpdateItems\StatusBaseInterface $event */
      $event_type = $event->getType();
      $agent_id = $event->getAgentId();

      if ($event_type == AiAgentStatusItemTypes::Started) {
        $agent_runner_id = $event->getAgentRunnerId();
        $agent_runner_to_agent_id[$agent_runner_id] = $agent_id;
        $items[$agent_id] = [
          'id' => $agent_id,
          'type' => 'agent',
          'name' => $event->getAgentName(),
          'description' => $this->getAgentDescription($agent_id, $event->getAgentName() ?? 'Agent'),
          'status' => 'running',
          'generated_text' => '',
          'agent_runner_id' => $agent_runner_id,
        ];
      }
      elseif ($event_type == AiAgentStatusItemTypes::Finished) {
        if (isset($items[$agent_id])) {
          $items[$agent_id]['status'] = 'completed';
          if ($agent_id == 'canvas_ai_orchestrator') {
            $is_finished = TRUE;
            break;
          }
        }
      }
      elseif ($event_type == AiAgentStatusItemTypes::TextGenerated) {
        if ($event instanceof TextGenerated) {
          $generated_text = $event->getGeneratedText();
          if (!empty($generated_text)) {
            $agent_runner_id = $event->getAgentRunnerId();
            if (isset($agent_runner_to_agent_id[$agent_runner_id])) {
              $target_agent_id = $agent_runner_to_agent_id[$agent_runner_id];
              if (isset($items[$target_agent_id])) {
                $items[$target_agent_id]['generated_text'] = !empty($items[$target_agent_id]['generated_text'])
                  ? $items[$target_agent_id]['generated_text'] . "\n\n" . $generated_text
                  : $generated_text;
              }
            }
          }
        }
      }
    }

    if ($is_finished) {
      foreach ($items as $key => $item) {
        if ($item['status'] !== 'completed') {
          $items[$key]['status'] = 'completed';
        }
      }
    }

    // If there is only one item, and it's the orchestrator, remove its
    // generated_text to avoid duplicating the final response.
    if (count($items) === 1 && isset($items['canvas_ai_orchestrator'])) {
      unset($items['canvas_ai_orchestrator']['generated_text']);
    }

    return new JsonResponse([
      'is_finished' => $is_finished,
      'items' => array_values($items),
    ]);
  }

  /**
   * Function to return the agent description.
   *
   * @param string $agent_id
   *   The agent ID.
   * @param string $agent_name
   *   The agent name.
   *
   * @return string
   *   The agent description.
   */
  private function getAgentDescription(string $agent_id, string $agent_name): string {
    $descriptions = [
      'canvas_ai_orchestrator' => $this->t('Thinking'),
      'canvas_title_generation_agent' => $this->t('Generate a title'),
      'canvas_component_agent' => $this->t('Generate a component'),
      'canvas_metadata_generation_agent' => $this->t('Generate metadata'),
      'canvas_page_builder_agent' => $this->t('Building the page'),
      'information_finder' => $this->t('Looking for information'),
      'canvas_template_builder_agent' => $this->t('Building the template'),
      'media_finder_agent' => $this->t('Looking for images'),
    ];
    return $descriptions[$agent_id] ?? $this->t('@agentName working', ['@agentName' => $agent_name]);
  }

}
