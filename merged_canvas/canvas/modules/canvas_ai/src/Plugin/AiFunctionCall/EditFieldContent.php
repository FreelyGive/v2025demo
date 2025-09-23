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
 * Plugin implementation of edit field content function.
 */
#[FunctionCall(
  id: 'ai_agent:edit_field_content',
  function_name: 'ai_agent_edit_field_content',
  name: 'Refine content for entity field',
  description: 'This method allows you to refine the content on entity field.',
  group: 'modification_tools',
  module_dependencies: ['canvas'],
  context_definitions: [
    'text_value' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Text"),
      description: new TranslatableMarkup("All the new text that should replace the old one."),
      required: TRUE
    ),
    'field_name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Field Name"),
      description: new TranslatableMarkup("The field provided by the user."),
      required: TRUE
    ),
  ],
)]
class EditFieldContent extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The text value.
   *
   * @var string
   */
  protected string $value = "";

  /**
   * The field name.
   *
   * @var string
   */
  protected string $fieldName = "";

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $this->value = $this->getContextValue('text_value');
    $this->fieldName = $this->getContextValue('field_name');
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    // \Drupal\canvas_ai\Controller\CanvasBuilder::render() expects a YAML parsable
    // string.
    // @see \Drupal\canvas_ai\Controller\CanvasBuilder::render()
    return Yaml::dump([
      'refined_text' => $this->value,
      'field_name' => $this->fieldName,
    ]);
  }

}
