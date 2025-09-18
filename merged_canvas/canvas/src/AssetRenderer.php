<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Nicely complements \Drupal\Core\Asset\AttachedAssets.
 *
 * @internal
 */
final class AssetRenderer {

  public function __construct(
    private readonly AssetResolverInterface $assetResolver,
    #[Autowire(service: 'asset.css.collection_renderer')]
    private readonly AssetCollectionRendererInterface $cssCollectionRenderer,
    #[Autowire(service: 'asset.js.collection_renderer')]
    private readonly AssetCollectionRendererInterface $jsCollectionRenderer,
    private readonly RendererInterface $renderer,
  ) {}

  public function renderCssAssets(AttachedAssets $assets): string|\Stringable {
    $css_assets = $this->assetResolver->getCssAssets($assets, TRUE);
    $build = $this->cssCollectionRenderer->render($css_assets);
    return $this->renderer->render($build);
  }

  public function renderJsHeaderAssets(AttachedAssets $assets): string|\Stringable {
    $js_assets = $this->assetResolver->getJsAssets($assets, TRUE);
    $build = $this->jsCollectionRenderer->render($js_assets[0]);
    return $this->renderer->render($build);
  }

  public function renderJsFooterAssets(AttachedAssets $assets): string|\Stringable {
    $js_assets = $this->assetResolver->getJsAssets($assets, TRUE);
    $build = $this->jsCollectionRenderer->render($js_assets[1]);
    return $this->renderer->render($build);
  }

}
