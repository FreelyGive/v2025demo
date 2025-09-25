<?php

namespace Drupal\canvas_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\canvas_ai\CanvasAiPermissions;
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
  id: 'canvas_ai:search_canvas_pages',
  function_name: 'search_canvas_pages',
  name: 'Search Canvas Pages',
  description: 'This tool can search other canvas pages, when the agent needs to get information about other pages.',
  group: 'information_tools',
  context_definitions: [
    'search_string' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Search String"),
      description: new TranslatableMarkup("This takes a string and searches with OR on each word."),
      required: FALSE
    ),
    'id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("ID"),
      description: new TranslatableMarkup("The ID of the page to get information about if known. Search string will be ignored if this is provided."),
      required: FALSE
    ),
    'amount' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Amount"),
      description: new TranslatableMarkup("The amount of pages to return. Max 10."),
      required: FALSE,
      default_value: 10,
    ),
    'offset' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup("Offset"),
      description: new TranslatableMarkup("The offset to start returning results from."),
      required: FALSE,
      default_value: 0,
    ),
  ],
)]
final class SearchCanvasPages extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

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
    $instance->entityTypeManager = $container->get('entity_type.manager');
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
    $search_string = $this->getContextValue('search_string') ?? '';
    $id = $this->getContextValue('id') ?? '';
    $amount = $this->getContextValue('amount') ?? 10;
    $offset = $this->getContextValue('offset') ?? 0;

    // Load the page entity type.
    $page_storage = $this->entityTypeManager->getStorage('canvas_page');

    // Explode the search string into words for OR searching.
    $search_terms = array_filter(preg_split('/\s+/', $search_string));

    $query = $page_storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('title', 'ASC')
      ->range($offset, $amount);

    if ($id) {
      $query->condition('id', $id);
    }
    elseif (!empty($search_terms)) {
      $group = $query->orConditionGroup();
      foreach ($search_terms as $term) {
        $group->condition('title', '%' . $term . '%', 'LIKE');
        $group->condition('description', '%' . $term . '%', 'LIKE');
      }
      $query->condition($group);
    }

    $nids = $query->execute();
    $pages = $page_storage->loadMultiple($nids);
    $layout = [];
    foreach ($pages as $page) {
      $layout[] = [
        'id' => $page->id(),
        'title' => $page->label(),
        'url' => $page->toUrl()->setAbsolute()->toString(),
      ];
    }
    $this->setOutput(Yaml::dump($layout) ?? 'No layout currently stored.');
  }

}
