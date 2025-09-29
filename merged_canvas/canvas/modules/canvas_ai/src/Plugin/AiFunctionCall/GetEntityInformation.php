<?php

namespace Drupal\canvas_ai\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Plugin implementation to get entity information.
 */
#[FunctionCall(
  id: 'ai_agent:get_entity_information',
  function_name: 'ai_agent_get_entity_information',
  name: 'Get entity information',
  description: 'This method allows to get entity information.',
  group: 'information_tools',
  module_dependencies: ['canvas'],
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Entity Type"),
      description: new TranslatableMarkup("The entity type for which you want to perform operations."),
      required: FALSE
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Entity Id"),
      description: new TranslatableMarkup("The entity id for which you want to perform operations."),
      required: FALSE,
    ),
    'selected_component' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Selected Component"),
      description: new TranslatableMarkup("The selected component for which you want to perform operations."),
      required: FALSE
    ),
    'layout' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Current Layout"),
      description: new TranslatableMarkup("The current layout of the page."),
      required: FALSE
    ),
  ],
)]
final class GetEntityInformation extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type.
   *
   * @var string|null
   */
  protected string|null $entityType;

  /**
   * The entity ID.
   *
   * @var int|null
   */
  protected int|null $entityId;

  /**
   * The selected component value.
   *
   * @var string
   */
  protected string $selectedComponent;

  /**
   * The current layout.
   *
   * @var string
   */
  protected string $layout;

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $this->entityId = $this->getContextValue('entity_id');
    $this->entityType = $this->getContextValue('entity_type');
    $this->selectedComponent = $this->getContextValue('selected_component') ?? '';
    $this->layout = $this->getContextValue('layout') ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return Yaml::dump([
      'entity_id' => $this->entityId,
      'entity_type' => $this->entityType,
      'selected_component' => $this->selectedComponent,
      'layout' => $this->layout,
    ], 10, 2);
  }

}
