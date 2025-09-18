<?php

namespace Drupal\canvas_ai\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation of the add metadata function.
 */
#[FunctionCall(
  id: 'ai_agent:add_metadata',
  function_name: 'ai_agent_add_metadata',
  name: 'Add metadata for a field',
  description: 'This method allows you to add the metatag description.',
  group: 'modification_tools',
  module_dependencies: ['canvas'],
  context_definitions: [
    'metadata' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Metadata"),
      description: new TranslatableMarkup("All new metadata that should replace any existing metadata."),
      required: TRUE
    ),
  ],
)]
class AddMetadata extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The metadata.
   *
   * @var string
   */
  protected string $metadata = "";

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $this->metadata = $this->getContextValue('metadata');
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    // \Drupal\canvas_ai\Controller\CanvasBuilder::render() expects a YAML parsable
    // string.
    // @see \Drupal\canvas_ai\Controller\CanvasBuilder::render()
    return Yaml::dump([
      'metadata' => [
        'metatag_description' => $this->metadata,
      ],
    ], 10, 2);
  }

}
