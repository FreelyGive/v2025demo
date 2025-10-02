<?php

namespace Drupal\context_content_watch\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the getting canvas content function.
 */
#[FunctionCall(
  id: 'context_content_watch:get_canvas_content',
  function_name: 'context_content_watch_get_canvas_content',
  name: 'Get Canvas content',
  description: 'This method gets the content of a Canvas page by its ID.',
  group: 'information_tools',
  context_definitions: [
    'page_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Page ID"),
      description: new TranslatableMarkup("The ID of the Canvas page to get the content for."),
      required: TRUE,
    ),
  ],
)]
class GetCanvasContent extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter'),
    );
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // Collect the context values.
    $page_id = $this->getContextValue('page_id');

    // Query all component content for the given page ID.
    $components = $this->connection->select('canvas_page__components', 'c')
      ->fields('c', ['components_inputs'])
      ->condition('entity_id', $page_id)
      ->execute()
      ->fetchCol();

    $page = $this->entityTypeManager->getStorage('canvas_page')->load($page_id);

    // Return a structured payload.
    $this->setStructuredOutput([
      'id' => $page_id,
      'title' => $page->label(),
      'url' => $page->getUrl()->toString(),
      'component_content' => $components,
    ]);
  }

}
