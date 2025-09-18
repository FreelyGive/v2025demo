<?php

namespace Drupal\canvas_ai\Plugin\AiFunctionCall;

use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\canvas\Validation\ConstraintPropertyPathTranslatorTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of edit the component js function.
 */
#[FunctionCall(
  id: 'ai_agent:edit_component_js',
  function_name: 'ai_agent_edit_component_js',
  name: 'Edit javascript on components',
  description: 'This method allows you to edit the javascript on components.',
  group: 'modification_tools',
  module_dependencies: ['canvas'],
  context_definitions: [
    'javascript' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Javascript"),
      description: new TranslatableMarkup("All the new javascript that should replace the old one."),
      required: TRUE
    ),
    'props_metadata' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Props"),
      description: new TranslatableMarkup("Metadata for props"),
      required: TRUE
    ),
    'component_machine_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Component machine name"),
      description: new TranslatableMarkup("The machine name of the component to edit."),
      required: TRUE
    ),
  ],
)]
final class EditComponentJs extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  use ConstraintPropertyPathTranslatorTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  protected LoggerInterface $logger;

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
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $instance->logger = $container->get('logger.factory')->get('canvas_ai');
    return $instance;
  }

  /**
   * The js.
   *
   * @var string
   */
  protected string $js = "";
  /**
   * The props.
   *
   * @var string
   */
  protected string $props = "";

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    try {
      $machine_name = $this->getContextValue('component_machine_name');
      $this->js = $this->getContextValue('javascript');
      $this->props = $this->getContextValue('props_metadata');
      $props_array = Json::decode($this->props);
      // Check if the component exists.
      /** @var \Drupal\canvas\Entity\JavaScriptComponent $component */
      $component = $this->entityTypeManager->getStorage('js_component')->load($machine_name);

      if (!$component) {
        throw new \Exception("Could not find component $machine_name.");
      }
      $transformed_props = [];
      if (is_array($props_array)) {
        foreach ($props_array as $prop) {
          if (!empty($prop['id']) && !empty($prop['name']) && !empty($prop['type']) && !empty($prop['example'])) {
            $transformed = [
              'title' => $prop['name'],
              'type' => $prop['type'],
              'examples' => [$prop['example']],
            ];
            foreach (['format', '$ref', 'enum'] as $optional) {
              if (isset($prop[$optional])) {
                $transformed[$optional] = $prop[$optional];
              }
            }
            $transformed_props[$prop['id']] = $transformed;
          }
        }
      }

      $output = [
        'name' => $component->get('name'),
        'machineName' => $machine_name,
        // Mark this code component as "internal": do not make it available to Content Creators yet.
        // @see docs/config-management.md, section 3.2.1
        'status' => FALSE,
        'sourceCodeJs' => $this->js,
        'sourceCodeCss' => '',
        'compiledJs' => '',
        'compiledCss' => '',
        'importedJsComponents' => [],
        'props' => $transformed_props,
        'dataDependencies' => [],
      ];
      $violations = JavaScriptComponent::createFromClientSide($output)->getTypedData()->validate();
      if ($violations->count() > 0) {

        // Translate constraint property paths to match YAML input structure
        $translatedViolations = $this->translateConstraintPropertyPathsAndRoot(
          ['' => 'component_structure.'],
          $violations,
          ''
        );
        throw new ConstraintViolationException($translatedViolations, 'Component validation errors');
      }

    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      // \Drupal\canvas_ai\Controller\CanvasBuilder::render() also YAML parsable output.
      // @see \Drupal\canvas_ai\Controller\CanvasBuilder::render()
      $this->setOutput(Yaml::dump(['error' => sprintf('Failed to process Javascript component data: %s', $e->getMessage())], 10, 2));
      return;
    }
    // \Drupal\canvas_ai\Controller\CanvasBuilder::render() expects a YAML parsable
    // string.
    // @see \Drupal\canvas_ai\Controller\CanvasBuilder::render()
    $this->setOutput(Yaml::dump([
      'js_structure' => $this->js,
      'props_metadata' => $this->props,
    ], 10, 2)
    );
  }

}
