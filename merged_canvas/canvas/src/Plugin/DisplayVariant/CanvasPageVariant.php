<?php

namespace Drupal\canvas\Plugin\DisplayVariant;

use Drupal\Core\Block\MessagesBlockPluginInterface;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Display\Attribute\PageDisplayVariant;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\PageRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a page display variant decorating the main content with components.
 *
 * Uses the theme's `page.html.twig` and populates each region in that Twig
 * template with an Drupal Canvas component tree, which are defined in the
 * Drupal Canvas PageRegion config entities for that theme's regions.
 *
 * The `content` region is a special case: it is the only theme region required
 * to exist. To keep the Drupal Canvas UX simple and consistent, it:
 * - is not possible to customize what appears in the `content `region: it is
 *   always, and only, the main content. This guarantees that the result of the
 *   matched route's controller is always available when Canvas renders the page.
 * - falls back to displaying the "messages" in the `content` region, if and
 *   only if it does not appear in any other region. (Because that can also be
 *   essential information.)
 *
 * @see \Drupal\system\Controller\SystemController::themesPage()
 * @see \Drupal\Core\Block\MainContentBlockPluginInterface
 * @see \Drupal\canvas\EventSubscriber\PageVariantSelectorSubscriber::onSelectPageDisplayVariant()
 * @see ::MAIN_CONTENT_REGION
 *
 * All MessagesBlockPluginInterface implementations use the global context; but
 * TitleBlockPluginInterface implementations need to receive the information
 * from this page variant. To achieve that without burdening all intermediary
 * abstraction layers with the need for additional parameters or exception
 * handling, PHP fibers are used.
 *
 * Finally, MainContentBlockPluginInterface implementations are prevented from
 * being made available as Canvas Components.
 *
 * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\BlockComponent::checkRequirements()
 *
 * @see docs/components.md
 * @see \Drupal\Core\Render\Element\Page
 * @see \Drupal\canvas\Entity\PageRegion
 * @see \Drupal\Core\Block\MainContentBlockPluginInterface
 * @see \Drupal\Core\Block\TitleBlockPluginInterface
 * @see \Drupal\Core\Block\MessagesBlockPluginInterface
 *
 * @todo When implementing Canvas requirement `41. Conditional display of components`, also implement \Drupal\Core\Display\ContextAwareVariantInterface: https://docs.google.com/spreadsheets/d/1OpETAzprh6DWjpTsZG55LWgldWV_D8jNe9AM73jNaZo/edit?gid=1721130122#gid=1721130122&range=B53
 */
#[PageDisplayVariant(
  id: self::PLUGIN_ID,
  admin_label: new TranslatableMarkup('Page with Drupal Canvas Components')
)]
final class CanvasPageVariant extends VariantBase implements PageVariantInterface, ContainerFactoryPluginInterface {

  public const string PLUGIN_ID = 'canvas';

  /**
   * The plugin configuration key whose value is the preview value.
   *
   * @var string
   */
  public const string PREVIEW_KEY = 'preview';

  /**
   * The (machine) name of the only theme region required to exist.
   *
   * See detailed analysis in the class-level documentation.
   *
   * @see \Drupal\system\Controller\SystemController::themesPage()
   */
  public const string MAIN_CONTENT_REGION = 'content';

  /**
   * The render array representing the main page content.
   *
   * @var array
   */
  private $mainContent = [];

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   *
   * @var string|array
   */
  private $title = '';

  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly AutoSaveManager $autoSaveManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(AutoSaveManager::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setMainContent(array $main_content) {
    $this->mainContent = $main_content;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $regions = PageRegion::loadForActiveTheme();
    if (empty($regions)) {
      throw new \LogicException('This page display variant needs Drupal Canvas PageRegion config entities.');
    }
    assert(is_bool($this->configuration[self::PREVIEW_KEY]) || is_null($this->configuration[self::PREVIEW_KEY]));
    $is_preview = $this->configuration[self::PREVIEW_KEY] === TRUE;

    assert(!empty($this->title));
    assert(!empty($this->mainContent));

    // Track whether a block showing the messages is displayed.
    $messages_block_displayed = FALSE;

    foreach ($regions as $region) {
      // If we are in preview mode replace the region with the auto-saved
      // version if any.
      if ($is_preview) {
        $autoSaveData = $this->autoSaveManager->getAutoSaveEntity($region);
        if (!$autoSaveData->isEmpty()) {
          \assert($autoSaveData->entity instanceof PageRegion);
          $violations = $autoSaveData->entity->getTypedData()->validate();
          if (\count($violations) === 0) {
            // The auto-save entry is valid, so use it instead.
            $region = $autoSaveData->entity;
          }
        }
      }

      $component_tree = $region->getComponentTree();

      // Render the component tree in a PHP fiber to allow injecting page-level
      // information (title, which originates from the matched route's
      // controller) into special Canvas Components.
      // @see \Drupal\Core\Display\PageVariantInterface
      // @see \Drupal\Core\Block\TitleBlockPluginInterface
      // @see \Drupal\canvas\ComponentSource\ComponentSourceInterface::renderComponent()
      // @see \Drupal\block\Plugin\DisplayVariant\BlockPageVariant::build()
      $fiber = new \Fiber(fn() => $component_tree->toRenderable($region, $is_preview));
      $component_instance = $fiber->start();
      while ($fiber->isSuspended()) {
        $component_instance = match (TRUE) {
          // Page-level information: the title.
          $component_instance instanceof TitleBlockPluginInterface => (function () use ($component_instance, $fiber) {
            $component_instance->setTitle($this->title);
            return $fiber->resume();
          })(),
          $component_instance instanceof MessagesBlockPluginInterface => (function () use ($fiber, &$messages_block_displayed) {
            $messages_block_displayed = TRUE;
            return $fiber->resume();
          })(),
          // No other page-level information exists in Drupal at this time.
          default => new \LogicException(),
        };
      }
      assert($fiber->isTerminated());
      $build[$region->get('region')] = $fiber->getReturn();
      CacheableMetadata::createFromObject($region)->applyTo($build);
    }

    // Now render the special "content" region.
    // @see ::MAIN_CONTENT_REGION
    $build[self::MAIN_CONTENT_REGION]['system_main'] = $this->mainContent;
    // If no block displays status messages, still render them.
    if (!$messages_block_displayed) {
      $build[self::MAIN_CONTENT_REGION]['messages'] = [
        '#weight' => -1000,
        '#type' => 'status_messages',
        '#include_fallback' => TRUE,
      ];
    }

    return $build;
  }

}
