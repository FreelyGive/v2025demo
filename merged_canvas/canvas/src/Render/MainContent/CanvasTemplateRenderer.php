<?php

namespace Drupal\canvas\Render\MainContent;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\MainContent\MainContentRendererInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Asset\AttachedAssets;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Asset\LibraryDiscoveryInterface;

/**
 * Main content renderer for Canvas endpoints returning React-renderable markup.
 *
 * Generates a JSON response with:
 * - `html` the HTML of the rendered page.
 * - `css`: CSS asset info in the structure expected by the `add_css` AJAX
 *    command.
 * - `js`: JS asset info in the structure expected by the `add_js` AJAX command.
 * - `settings`: JS settings in the structure expected by the `settings` AJAX
 *    command.
 *
 * @see \Drupal\Core\Ajax\AddCssCommand
 * @see \Drupal\Core\Ajax\AddJsCommand
 * @see \Drupal\Core\Ajax\SettingsCommand
 * @see ui/src/services/processResponseAssets.ts
 */
final class CanvasTemplateRenderer implements MainContentRendererInterface {

  public function __construct(
    protected ElementInfoManagerInterface $element_info_manager,
    protected RendererInterface $renderer,
    protected AssetResolverInterface $assetResolver,
    protected AssetCollectionRendererInterface $cssCollectionRenderer,
    protected AssetCollectionRendererInterface $jsCollectionRenderer,
    protected RequestStack $requestStack,
    protected ModuleHandlerInterface $moduleHandler,
    protected LanguageManagerInterface $languageManager,
    protected LibraryDiscoveryInterface $libraryDiscovery,
  ) {

  }

  /**
   * Recursively identifies library dependencies.
   *
   * @param string $library
   *   The library name in the format 'extension/library_name'.
   * @param array $collected_dependencies
   *   Array of already collected dependencies.
   *
   * @return array
   *   Array of all dependencies, including nested ones.
   */
  private function resolveLibraryDependencies(string $library, array &$collected_dependencies = []): array {
    if (in_array($library, $collected_dependencies)) {
      return [];
    }

    [$extension, $name] = explode('/', $library, 2);
    if (!$this->moduleHandler->moduleExists($extension)) {
      return [];
    }

    $library_info = $this->libraryDiscovery->getLibraryByName($extension, $name);
    if (!$library_info || empty($library_info['dependencies'])) {
      return [];
    }

    $dependencies = [];
    foreach ($library_info['dependencies'] as $dependency) {
      if (!in_array($dependency, $collected_dependencies)) {
        $collected_dependencies[] = $dependency;
        $dependencies[] = $dependency;
        $nested_dependencies = $this->resolveLibraryDependencies($dependency, $collected_dependencies);
        $dependencies = array_merge($dependencies, $nested_dependencies);
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   *
   * This renderer has a specific purpose: to make the assets and settings in
   * '#attached' available to requests made by the Canvas UI alongside the HTML.
   *
   * @see ui/src/services/processResponseAssets.ts
   */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match): Response {
    $html = $this->renderer->renderRoot($main_content);
    $transforms = NestedArray::getValue($main_content, ['#attached', 'canvas-transforms']) ?? [];

    // Wrap this in `<template hyperscriptify>`, which targets it (and its
    // children - JSX or Twig) for React rendering via `hyperscriptify()`).
    // Hyperscriptify takes a not-ideal-for-eyeballs markup structure and
    // renders into something pleasant with React.
    // While hyperscriptify can be called on any element, using <template> keeps
    // the for-informational-purposes-only markup out of the DOM so React can
    // then turn it into something DOM-worthy.
    //
    // @see https://github.com/effulgentsia/hyperscriptify
    $assets = AttachedAssets::createFromRenderArray($main_content);

    // A few Drupal asset libraries need Canvas-specific variants, because of:
    // - some assets in the library conflict
    // - the library is requested by `canvas_stark` but should be rendered by the
    //   current admin theme, so we account for the admin theme's library extend
    //   and overrides.
    // @see \Drupal\canvas\Hook\LibraryHooks::customizeDialogLibrary()
    // This is performed here instead of library_info_alter() as this
    // information is cached per-theme and this distinction needs to be made
    // only in Canvas contexts.
    $libraries_replace = [
      'core/drupal.dialog' => 'canvas/canvas.drupal.dialog',
      'core/drupal.ajax' => 'canvas/canvas.drupal.ajax',
      'core/drupal.dialog.ajax' => 'canvas/canvas.drupal.dialog.ajax',
    ];
    foreach ($libraries_replace as $original => $replacement) {
      if ($index = array_search($original, $assets->libraries)) {
        $assets->libraries[$index] = $replacement;
      }
    }

    $query = $request->query->all();

    // This is effectively the same as the ajax_page_state query parameter
    // automatically included in all Drupal.Ajax requests. This camel cased
    // equivalent is explicitly added by Drupal Canvas as the request is
    // not made by Drupal.Ajax.
    $ajax_page_state = isset($query['ajaxPageState']) ? json_decode($query['ajaxPageState'], TRUE) : [];

    // The first time (and perhaps other times?) this renderer runs, the
    // libraries query parameter is compressed. We decompress anything requiring
    // it here.
    if (isset($ajax_page_state['libraries']) && !is_array($ajax_page_state['libraries'])) {
      if (is_array($ajax_page_state['libraries'])) {
        $ajax_page_state['libraries'] = array_map(
          fn($item) => str_contains($item, '/') ? $item : UrlHelper::uncompressQueryParameter($item),
          $ajax_page_state['libraries'],
        );
      }
      else {
        $ajax_page_state['libraries'] = UrlHelper::uncompressQueryParameter($ajax_page_state['libraries']);
      }
    }

    $already_loaded_libraries = isset($ajax_page_state['libraries']) ? explode(',', $ajax_page_state['libraries']) : [];

    $potentially_conflicting_libraries_added_on_canvas_load = [
      'canvas/canvas.drupal.ajax',
      'canvas/canvas.drupal.dialog',
      'canvas/canvas.drupal.dialog.ajax',
      'core/drupal.ajax',
      'core/drupal.dialog',
      'core/drupal.dialog.ajax',
    ];

    // Get all dependencies of the potentially conflicting libraries, including
    // nested ones.
    $dependencies_of_the_potential_conflicts = [];
    $collected_dependencies = [];
    foreach ($potentially_conflicting_libraries_added_on_canvas_load as $library) {
      $dependencies = $this->resolveLibraryDependencies($library, $collected_dependencies);
      $dependencies_of_the_potential_conflicts = array_merge($dependencies_of_the_potential_conflicts, $dependencies);
    }

    $already_loaded_libraries = array_unique([...$already_loaded_libraries, ...$potentially_conflicting_libraries_added_on_canvas_load, ...$dependencies_of_the_potential_conflicts]);
    $assets
      ->setAlreadyLoadedLibraries($already_loaded_libraries);

    // Collect CSS, JS and settings, which are added as properties to the JSON
    // response so the client can add them to the page using Drupal.Ajax()
    $get_css = $this->assetResolver->getCssAssets($assets, FALSE);
    $css_array = $this->cssCollectionRenderer->render($get_css);
    $css_for_ajax = array_map(fn($item) =>
      array_diff_key($item['#attributes'], ['rel' => 'rel']), $css_array);

    [$head_assets, $foot_assets] = $this->assetResolver->getJsAssets($assets, FALSE);
    $head_array = $this->jsCollectionRenderer->render($head_assets);
    $foot_array = $this->jsCollectionRenderer->render($foot_assets);
    $js_for_ajax = array_map(
      fn($item) => array_diff_key($item['#attributes'], ['rel' => 'rel']),
      [...$head_array, ...$foot_array]
    );
    $js_for_ajax = array_filter($js_for_ajax, fn($item) => !empty($item['src']));
    $settings = $assets->getSettings();

    $data = [
      'html' => "<template data-hyperscriptify>$html</template>",
      'css' => !empty($css_for_ajax) ? $css_for_ajax : [],
      'js' => !empty($js_for_ajax) ? $js_for_ajax : (object) $js_for_ajax,
      'settings' => $settings,
    ];
    $data['transforms'] = \count($transforms) > 0 ? $transforms : new \stdClass();

    return new JsonResponse($data);
  }

}
