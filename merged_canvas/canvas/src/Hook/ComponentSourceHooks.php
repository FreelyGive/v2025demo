<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Asset\LibraryDependencyResolverInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\canvas\CodeComponentDataProvider;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Plugin\ComponentPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * @file
 * Hook implementations that make Component Sources work.
 *
 * @see https://www.drupal.org/project/issues/canvas?component=Component+sources
 * @see docs/components.md
 */
readonly final class ComponentSourceHooks implements ContainerInjectionInterface {

  public function __construct(
    private RouteMatchInterface $routeMatch,
    private CodeComponentDataProvider $codeComponentDataProvider,
    private LibraryDependencyResolverInterface $libraryDependencyResolver,
    private ThemeManagerInterface $themeManager,
    private ConfigFactoryInterface $configFactory,
    private RequestStack $requestStack,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('current_route_match'),
      $container->get(CodeComponentDataProvider::class),
      $container->get(LibraryDependencyResolverInterface::class),
      $container->get(ThemeManagerInterface::class),
      $container->get(ConfigFactoryInterface::class),
      $container->get(RequestStack::class),
    );
  }

  const ASSET_LIBRARY_METHOD_MAPPING = [
    'canvas/canvasData.v0.baseUrl' => 'getCanvasDataBaseUrlV0',
    'canvas/canvasData.v0.branding' => 'getCanvasDataBrandingV0',
    'canvas/canvasData.v0.breadcrumbs' => 'getCanvasDataBreadcrumbsV0',
    'canvas/canvasData.v0.pageTitle' => 'getCanvasDataPageTitleV0',
    'canvas/canvasData.v0.jsonapiSettings' => 'getCanvasDataJsonApiSettingsV0',
  ];

  /**
   * Implements hook_rebuild().
   */
  #[Hook('rebuild')]
  public function rebuild(): void {
    // The module installer cleared all plugin caches. Create/update Component
    // config entities for all Canvas Component source plugins.
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\BlockComponent
    // @phpstan-ignore-next-line
    \Drupal::service(BlockManagerInterface::class)->getDefinitions();
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent
    // @phpstan-ignore-next-line
    \Drupal::service(ComponentPluginManager::class)->getDefinitions();
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {
    if ($is_syncing) {
      return;
    }
    $this->rebuild();
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    // @todo Remove this when https://www.drupal.org/project/drupal/issues/3534717 lands.
    $definitions['field.value.boolean']['mapping']['value']['type'] = 'boolean';
  }

  /**
   * Implements hook_page_attachments().
   *
   * For code components.
   *
   * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$page): void {
    // Early return when on a page that does not use the default theme.
    // TRICKY: no cacheability metadata needed for `system.theme` because it has
    // special handling.
    // @see \Drupal\system\SystemConfigSubscriber::onConfigSave()
    $page['#cache']['contexts'][] = 'theme';
    $default_theme = $this->configFactory->get('system.theme')->get('default');
    if ($this->themeManager->getActiveTheme($this->routeMatch)->getName() !== $default_theme) {
      return;
    }

    $route = $this->routeMatch->getRouteObject();
    assert($route instanceof Route);
    $is_preview = $route->getOption('_canvas_use_template_draft') === TRUE;
    // TRICKY: the `route` cache context varies also by route parameters, that
    // is unnecessary here, because this only varies by route definition.
    $page['#cache']['contexts'][] = 'route.name';
    $asset_library = AssetLibrary::load(AssetLibrary::GLOBAL_ID);
    // The `global `asset library is guaranteed to exist, but protect even
    // against the most obscure edge cases. (Also: tests do simulate that!)
    if ($asset_library) {
      $page['#attached']['library'][] = $asset_library->getAssetLibrary($is_preview);
    }
  }

  /**
   * Implements hook_js_settings_alter().
   */
  #[Hook('js_settings_alter')]
  public function jsSettingsAlter(array &$settings, AttachedAssetsInterface $assets): void {
    $path = [CodeComponentDataProvider::CANVAS_DATA_KEY, CodeComponentDataProvider::V0];
    $canvasData = $settings[CodeComponentDataProvider::CANVAS_DATA_KEY] ?? [];

    // This is an oversight in core infra; this should not be necessary.
    $all_attached_asset_libraries = $this->libraryDependencyResolver->getLibrariesWithDependencies($assets->getLibraries());

    $request = $this->requestStack->getCurrentRequest();
    \assert($request instanceof Request);

    $all = in_array('canvas/canvasData.v0', $all_attached_asset_libraries, TRUE);
    if ($all || in_array('canvas/canvasData.v0.baseUrl', $all_attached_asset_libraries, TRUE)) {
      // Allow overrides: only set if still NULL.
      if (NestedArray::getValue($settings, [...$path, 'baseUrl']) === NULL) {
        $canvasData = array_replace_recursive($canvasData, $this->memoize($request, 'canvas/canvasData.v0.baseUrl'));
      }
    }
    if ($all || in_array('canvas/canvasData.v0.branding', $all_attached_asset_libraries, TRUE)) {
      // Allow overrides: only set if still NULL.
      if (NestedArray::getValue($settings, [...$path, 'branding', 'homeUrl']) === NULL) {
        $canvasData = array_replace_recursive($canvasData, $this->memoize($request, 'canvas/canvasData.v0.branding'));
      }
    }
    if ($all || in_array('canvas/canvasData.v0.breadcrumbs', $all_attached_asset_libraries, TRUE)) {
      // Allow overrides: only set if still NULL.
      if (NestedArray::getValue($settings, [...$path, 'breadcrumbs']) === NULL) {
        $canvasData = array_replace_recursive($canvasData, $this->memoize($request, 'canvas/canvasData.v0.breadcrumbs'));
      }
    }
    if ($all || in_array('canvas/canvasData.v0.pageTitle', $all_attached_asset_libraries, TRUE)) {
      // Allow overrides: only set if still NULL.
      if (NestedArray::getValue($settings, [...$path, 'pageTitle']) === NULL) {
        $canvasData = array_replace_recursive($canvasData, $this->memoize($request, 'canvas/canvasData.v0.pageTitle'));
      }
    }
    if ($all || in_array('canvas/canvasData.v0.jsonapiSettings', $all_attached_asset_libraries, TRUE)) {
      // Allow overrides: only set if still NULL.
      if (NestedArray::getValue($settings, [...$path, 'jsonapiSettings']) === NULL) {
        $canvasData = array_replace_recursive($canvasData, $this->memoize($request, 'canvas/canvasData.v0.jsonapiSettings'));
      }
    }
    if (!empty($canvasData)) {
      ksort($canvasData[CodeComponentDataProvider::V0]);
      $settings[CodeComponentDataProvider::CANVAS_DATA_KEY] = $canvasData;
    }
  }

  /**
   * Avoids repeated calls to CodeComponentDataProvider for the same request.
   *
   * @see \Drupal\canvas\CodeComponentDataProvider
   */
  private function memoize(Request $request, string $asset_library): array {
    assert(str_starts_with($asset_library, 'canvas/canvasData.v0.'));

    static $cached;
    if (!isset($cached)) {
      $cached = [];
    }
    if (!isset($cached[$asset_library])) {
      $cached[$asset_library] = new \SplObjectStorage();
    }
    if (!isset($cached[$asset_library][$request])) {
      $method = self::ASSET_LIBRARY_METHOD_MAPPING[$asset_library];
      assert(method_exists($this->codeComponentDataProvider, $method));
      $cached[$asset_library][$request] = $this->codeComponentDataProvider->$method();
    }

    return $cached[$asset_library][$request];
  }

}
