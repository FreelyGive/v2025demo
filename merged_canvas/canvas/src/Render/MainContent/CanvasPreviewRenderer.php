<?php

declare(strict_types=1);

namespace Drupal\canvas\Render\MainContent;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\MainContent\HtmlRenderer;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderCacheInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A *private* main content renderer for Canvas endpoints returning preview markup.
 *
 * It is private because it is not exposed as a `render.main_content_renderer`-
 * tagged service. Used only by PreviewEnvelopeViewSubscriber.
 *
 * Overrides the default HTML renderer to remove the page_top and page_bottom
 * regions, to remove the toolbar and any other extraneous markup in previews,
 * and returns a JSON response containing the rendered HTML.
 *
 * Unlike CanvasTemplateRenderer the output of this renderer is intended to be
 * displayed in an iframe, so assets are included in the HTML instead of being
 * handled separately.
 *
 * @see \Drupal\canvas\EventSubscriber\PreviewEnvelopeViewSubscriber::onViewPreviewEnvelope
 */
final class CanvasPreviewRenderer extends HtmlRenderer {

  public function __construct(
    TitleResolverInterface $title_resolver,
    #[Autowire(service: 'plugin.manager.display_variant')]
    PluginManagerInterface $display_variant_manager,
    EventDispatcherInterface $event_dispatcher,
    ModuleHandlerInterface $module_handler,
    RendererInterface $renderer,
    RenderCacheInterface $render_cache,
    #[Autowire(param: 'renderer.config')]
    array $renderer_config,
    ThemeManagerInterface $theme_manager,
    #[Autowire(service: 'html_response.attachments_processor')]
    private readonly AttachmentsResponseProcessorInterface $attachmentsResponseProcessor,
  ) {
    parent::__construct($title_resolver, $display_variant_manager, $event_dispatcher, $module_handler, $renderer, $render_cache, $renderer_config, $theme_manager);
  }

  /**
   * {@inheritdoc}
   *
   * This renderer renders the HTML, processes the attachments, and wraps it
   * in a JSON response for the frontend to consume.
   *
   * @see \Drupal\Core\EventSubscriber\HtmlResponseSubscriber
   */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match, array $additionalData = []): JsonResponse {
    $response = parent::renderResponse($main_content, $request, $route_match);
    assert($response instanceof AttachmentsInterface);
    $response = $this->attachmentsResponseProcessor->processAttachments($response);
    assert($response instanceof Response);

    // @todo Expose warnings and errors to the Canvas UI: https://www.drupal.org/project/canvas/issues/3489302#comment-15877293
    return new JsonResponse([
      'html' => $response->getContent(),
    ] + $additionalData);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepare(array $main_content, Request $request, RouteMatchInterface $route_match) {
    [$page, $title] = parent::prepare($main_content, $request, $route_match);
    foreach (Element::children($page) as $region) {
      if ($region === CanvasPageVariant::MAIN_CONTENT_REGION) {
        continue;
      }
      // Empty regions don't need HTML comments to inform the Canvas UI; empty regions
      // are not visible. They can only be reached by right-clicking in the UI
      // and moving it to such a not yet visible region.
      if ($page[$region] === []) {
        continue;
      }
      $page_regions = PageRegion::loadForActiveThemeByClientSideId();
      if (!empty($page_regions)) {
        $access = $page_regions[$region]->access('edit', return_as_object: TRUE);
        if ($access->isAllowed()) {
          $page[$region]['#prefix'] = Markup::create("<!-- canvas-region-start-$region -->");
          $page[$region]['#suffix'] = Markup::create("<!-- canvas-region-end-$region -->");
        }
        $cacheableMetadata = CacheableMetadata::createFromRenderArray($page[$region]);
        $cacheableMetadata->addCacheableDependency($access);
        $cacheableMetadata->applyTo($page[$region]);
      }
      // @see canvas_preprocess_region()
      $page[$region]['#canvas_region_preview'] = TRUE;
    }
    return [$page, $title];
  }

  /**
   * {@inheritdoc}
   */
  public function buildPageTopAndBottom(array &$html): void {
    // Intentionally does nothing, so we don't get toolbar, etc.
  }

}
