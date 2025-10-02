<?php

namespace Drupal\context_content_watch\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\canvas_ai\CanvasAiPermissions;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

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
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

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
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // Collect the context values.
    $page_id = $this->getContextValue('page_id');
    // Permissions, turned off for demo. We would need to upcast the user.

    $page = $this->entityTypeManager->getStorage('canvas_page')->load($page_id);
    // Return a structured payload.
    $output = [
      'id' => $page->id(),
      'title' => $page->label(),
      'status' => $page->isPublished() ? 'published' : 'unpublished',
      'metatags' => $page->get('metatags')->getValue(),
      'url' => $page->toUrl()->setAbsolute()->toString(),
      'components' => $page->get('components')->getValue(),
    ];
    $this->setStructuredOutput($output);
    $this->setOutput("```yaml\n" . Yaml::dump($output, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK) . "\n```");
  }

}
