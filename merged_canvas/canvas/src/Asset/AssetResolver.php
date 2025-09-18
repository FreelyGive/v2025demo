<?php

declare(strict_types=1);

namespace Drupal\canvas\Asset;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Asset\AssetResolver as CoreAssetResolver;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Asset\LibraryDependencyResolverInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

final class AssetResolver extends CoreAssetResolver {

  protected AssetResolverInterface $assetResolver;

  public function __construct(AssetResolverInterface $asset_resolver, LibraryDiscoveryInterface $library_discovery, LibraryDependencyResolverInterface $library_dependency_resolver, ModuleHandlerInterface $module_handler, ThemeManagerInterface $theme_manager, LanguageManagerInterface $language_manager, CacheBackendInterface $cache, ?ThemeHandlerInterface $theme_handler = NULL) {
    $this->assetResolver = $asset_resolver;
    parent::__construct($library_discovery, $library_dependency_resolver, $module_handler, $theme_manager, $language_manager, $cache, $theme_handler);
  }

  /**
   * An enhanced version getJsAssets().
   *
   * The default getJsAssets() has functionality to prevent already-loaded
   * libraries from being loaded an additional time. It does not, however,
   * prevent duplicate assets from being reloaded if they are part of multiple
   * libraries.
   *
   * Typically, multiple libraries using the same assets is not a concern, but
   * Drupal Canvas has several custom libraries that are equivalent to
   * core libraries but with changes that account for admin theme overrides and
   * other as-needed customization.
   *
   * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
   *   The assets attached to the current response.
   * @param bool $optimize
   *   Whether to apply the JavaScript asset collection optimizer.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   (optional) The interface language for the assets will be rendered with.
   *
   * @return array
   *   A nested array containing 2 values:
   *   - at index zero: the (possibly optimized) collection of JavaScript assets
   *     for the top of the page
   *   - at index one: the (possibly optimized) collection of JavaScript assets
   *     for the bottom of the page
   */
  public function getCanvasJsAssets(AttachedAssetsInterface $assets, bool $optimize, ?LanguageInterface $language = NULL): array {
    $default_result = $this->assetResolver->getJsAssets($assets, $optimize, $language);

    // Populate a list of JS assets that have already been loaded.
    $already_loaded_js = array_reduce($assets->getAlreadyLoadedLibraries(), function ($carry, $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      if (!empty($definition['js'])) {
        foreach ($definition['js'] as $js_asset) {
          if (isset($js_asset['data'])) {
            $carry[] = $js_asset['data'];
          }
        }
      }
      return $carry;
    }, []);

    // Check the JS assets to be loaded and remove any that have already been
    // added via other libraries.
    foreach ($default_result as $location => $some_assets) {
      if (!empty($some_assets)) {
        $new_js_assets = array_filter($some_assets, fn($file) => !in_array($file, $already_loaded_js), ARRAY_FILTER_USE_KEY);
        $default_result[$location] = $new_js_assets;
      }
    }

    return $default_result;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsAssets(AttachedAssetsInterface $assets, $optimize, ?LanguageInterface $language = NULL): array {
    $already_loaded = $assets->getAlreadyLoadedLibraries();
    $to_load = $assets->getLibraries();
    $already_loaded_canvas = array_filter($already_loaded, fn($item) => str_contains($item, 'canvas/canvas.drupal'));
    $to_load_canvas = array_filter($to_load, fn($item) => str_contains($item, 'canvas/canvas.drupal'));
    if (!empty($to_load_canvas) || !empty($already_loaded_canvas)) {
      return $this->getCanvasJsAssets($assets, $optimize, $language);
    }
    return parent::getJsAssets($assets, $optimize, $language);
  }

  /**
   * The only difference: asset type is `drupalSettings` instead of `js`.
   *
   * @todo Remove when core bug https://www.drupal.org/project/drupal/issues/3533354 is fixed.
   */
  protected function getJsSettingsAssets(AttachedAssetsInterface $assets): array {
    $settings = [];

    foreach ($this->getLibrariesToLoad($assets, asset_type: 'drupalSettings') as $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      if (isset($definition['drupalSettings'])) {
        $settings = NestedArray::mergeDeepArray([$settings, $definition['drupalSettings']], TRUE);
      }
    }

    return $settings;
  }

}
