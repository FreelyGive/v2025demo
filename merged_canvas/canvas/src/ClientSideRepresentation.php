<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\canvas\Render\ImportMapResponseAttachmentsProcessor;

/**
 * @see \Drupal\jsonapi\Normalizer\Value\CacheableNormalization
 */
final class ClientSideRepresentation implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * Factory method.
   */
  public static function create(array $values, ?array $preview): self {
    if ($preview) {
      assert(!array_key_exists('default_markup', $values));
      assert(!array_key_exists('css', $values));
      assert(!array_key_exists('js_header', $values));
      assert(!array_key_exists('js_footer', $values));
    }
    return new self($values, $preview);
  }

  /**
   * @param array|null $preview
   *   Optional, will be expanded to `default_markup` + `css` + `js_header` +
   *   `js_footer` in $values.
   */
  private function __construct(
    public readonly array $values,
    public readonly ?array $preview,
  ) {
  }

  public function renderPreviewIfAny(RendererInterface $renderer, AssetRenderer $asset_renderer): ClientSideRepresentation {
    if ($this->preview === NULL) {
      return $this;
    }

    $build = $this->preview;
    $default_markup = $renderer->renderInIsolation($build);
    $assets = AttachedAssets::createFromRenderArray($build);
    $import_map = ImportMapResponseAttachmentsProcessor::buildHtmlTagForAttachedImportMaps(BubbleableMetadata::createFromRenderArray($build)) ?? [];

    // A pre-rendered version of this config entity is provided so no requests
    // are needed when adding it to the layout which includes a default
    // markup, CSS files, JS files in the header and JS files in the
    // footer.
    return (new self(
      values: $this->values + [
        'default_markup' => $default_markup,
        'css' => $asset_renderer->renderCssAssets($assets),
        'js_header' => $renderer->renderInIsolation($import_map) . $asset_renderer->renderJsHeaderAssets($assets),
        'js_footer' => $asset_renderer->renderJsFooterAssets($assets),
      ],
      preview: NULL,
    ))
      ->addCacheableDependency($this)
      ->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
  }

  /**
   * Removes cache contexts.
   *
   * @param array $ignorable_cache_contexts
   *   The cache contexts to be removed, because they are safe to ignore.
   *
   * @return $this
   *
   * @see \Drupal\canvas\Controller\ApiConfigControllers::normalize()
   */
  public function removeCacheContexts(array $ignorable_cache_contexts): self {
    $this->cacheContexts = array_diff($this->cacheContexts, $ignorable_cache_contexts);
    return $this;
  }

}
