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
 * Plugin implementation of create field content function.
 */
#[FunctionCall(
  id: 'ai_agent:create_field_content',
  function_name: 'ai_agent_create_field_content',
  name: 'Create content for entity field',
  description: 'This method allows you to add the content on entity field.',
  group: 'modification_tools',
  module_dependencies: ['canvas'],
  context_definitions: [
    'field_content' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Content for the field."),
      description: new TranslatableMarkup("Content for the field."),
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
final class CreateFieldContent extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The text value.
   *
   * @var string
   */
  protected string $value = '';

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
    $this->value = $this->getContextValue('field_content');
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
      'created_content' => $this->value,
      'field_name' => $this->fieldName,
    ], 10, 2);
  }

}
