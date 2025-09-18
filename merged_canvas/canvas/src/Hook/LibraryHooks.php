<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Core\Asset\LibraryDiscoveryParser;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Version;

/**
 * Defines a class for library hooks.
 */
final class LibraryHooks {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LibraryDiscoveryParser $libraryDiscoveryParser,
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeInitializationInterface $themeInitialization,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ThemeHandlerInterface $themeHandler,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly Version $version,
  ) {
  }

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    // Find all libraries that specify canvasExtension in drupalSettings and provide
    // default values and image paths.
    foreach ($libraries as &$library) {
      if (!isset($library['drupalSettings']['canvasExtension'])) {
        continue;
      }

      foreach ($library['drupalSettings']['canvasExtension'] as &$extension_settings) {
        $module_path = $this->moduleExtensionList->getPath($extension);
        $extension_settings['modulePath'] = $module_path;

        assert(!empty($extension_settings['id']), "The canvasExtension config in $extension must have an 'id' property.");
        assert(!empty($extension_settings['name']), "The canvasExtension config in $extension must have a 'name' property.");

        if (empty($extension_settings['description'])) {
          $extension_settings['description'] = new TranslatableMarkup('No description provided.');
        }

        if (!isset($extension_settings['imgSrc'])) {
          continue;
        }

        $img_src = $extension_settings['imgSrc'];

        // Only prepend the path if it's a relative path without a leading slash
        if (!str_starts_with($img_src, '/') && !str_starts_with($img_src, 'http')) {
          assert(!str_starts_with($img_src, '.'), 'The extension image path must not start with "."');
          $extension_settings['imgSrc'] = Url::fromUri('base://' . $module_path . '/' . $img_src)->toString();
        }
      }
    }

    // Add the library to the list of dependencies for the navigation to allow
    // overriding its CSS.
    if ($extension === 'navigation') {
      $libraries['internal.navigation']['dependencies'][] = 'canvas/navigation.canvas.override';
    }
    if ($extension === 'canvas') {
      $version = $this->version->getVersion();
      // Only add the version for tagged releases. If version is not present,
      // AssetQueryStringInterface is used automatically.
      if ($version !== '0.0.0') {
        // For astro.client and astro.hydration we don't use their versions, but
        // the Canvas UI version.
        $libraries['astro.client']['version'] = $this->version->getVersion();
        $libraries['astro.hydration']['version'] = $this->version->getVersion();
        $libraries['canvas-ui']['version'] = $this->version->getVersion();
      }
    }
  }

  /**
   * Implements hook_library_info_build().
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild(): array {
    $libraries = [];

    // @see \Drupal\canvas\Entity\AssetLibrary::getAssetLibrary()
    // @see \Drupal\canvas\EntityHandlers\CanvasAssetStorage::generateFiles()
    foreach (AssetLibrary::loadMultiple() as $library_id => $library) {
      $library_name = "asset_library." . $library->id();
      // Prod.
      $libraries[$library_name] = [
        'dependencies' => [],
      ];
      if ($library->hasCss()) {
        $libraries[$library_name]['css']['theme'][$library->getCssPath()] = [];
      }
      if ($library->hasJs()) {
        $libraries[$library_name]['js'][$library->getJsPath()] = [];
      }
      assert(empty($library->getAssetLibraryDependencies()));
      // Draft.
      $draft_css_url = \sprintf('/canvas/api/v0/auto-saves/css/%s/%s', AssetLibrary::ENTITY_TYPE_ID, $library_id);
      $libraries[$library_name . '.draft']['css']['theme'][$draft_css_url] = ['preprocess' => FALSE];
      $draft_js_url = \sprintf('/canvas/api/v0/auto-saves/js/%s/%s', AssetLibrary::ENTITY_TYPE_ID, $library_id);
      $libraries[$library_name . '.draft']['js'][$draft_js_url] = ['preprocess' => FALSE];
    }

    // @see \Drupal\canvas\Entity\JavaScriptComponent::getAssetLibrary()
    // @see \Drupal\canvas\EntityHandlers\CanvasAssetStorage::generateFiles()
    foreach (JavaScriptComponent::loadMultiple() as $component_id => $component) {
      $library_name = "astro_island." . $component_id;
      // Prod.
      if ($component->hasCss()) {
        $libraries[$library_name]['css']['component'][$component->getCssPath()] = [];
      }
      $libraries[$library_name]['dependencies'] = $component->getAssetLibraryDependencies();
      $libraries[$library_name]['dependencies'][] = 'canvas/asset_library.' . AssetLibrary::GLOBAL_ID;
      // Draft.
      $draft_css_url = \sprintf('/canvas/api/v0/auto-saves/css/%s/%s', JavaScriptComponent::ENTITY_TYPE_ID, $component_id);
      $libraries[$library_name . '.draft']['css']['component'][$draft_css_url] = ['preprocess' => FALSE];
      $libraries[$library_name . '.draft']['dependencies'][] = 'canvas/asset_library.' . AssetLibrary::GLOBAL_ID . '.draft';
      // To avoid a race condition for auto-saved code components, always load
      // the data that it might start using at any point.
      $libraries[$library_name . '.draft']['dependencies'][] = 'canvas/canvasData.v0';
    }

    $theme_config = $this->configFactory->get('system.theme');
    $admin_theme_name = $theme_config->get('admin') ?: $theme_config->get('default');
    if ($this->themeHandler->themeExists($admin_theme_name)) {
      $libraries += $this->customizeDialogLibrary($admin_theme_name);
    }
    $this->buildExtensionLibraries($libraries);

    // Collect the CSS file paths from all of these Canvas-specific libraries so they
    // can be made available in drupalSettings. This makes is possible to add
    // dialog-scoped versions of this CSS on page load, so even dialogs that are
    // not opened with AJAX can be styled correctly.
    $css_files = [];
    foreach ($libraries as $library_name => &$library) {
      foreach ($libraries[$library_name]['css'] ?? [] as $files) {
        foreach ($files as $filename => $file_definition) {
          if (!str_ends_with($filename, '.css')) {
            continue;
          }
          $css_files[] = str_replace('/./', '/', $filename);
        }
      }
    }
    $libraries['canvas.drupal.dialog']['drupalSettings']['canvas']['dialogCss'] = $css_files;

    return $libraries;
  }

  /**
   * Creates Canvas-specific versions of any core-owned dependencies.
   *
   * The Canvas specific versions are largely the same as the core versions, but
   * take into account overrides specified by the admin theme.
   *
   * @param array $main_new_library
   *   The library having its dependencies evaluated and possibly replaced.
   * @param array $added_libraries
   *   All the new libraries being created by library_info_build().
   * @param array $canvas_replacing_cores
   *   Every key is a core library and the value its Canvas-specific version.
   * @param array $dependencies_to_check
   *   An array of dependencies to check and potentially create Canvas versions of.
   * @param array $existing_libraries
   *   An array of library definitions with admin theme overrides applied.
   * @param string $prefix
   *   Optional argument that makes it possible to check non-core libraries.
   */
  private function convertDependencies(array &$main_new_library, array &$added_libraries, array &$canvas_replacing_cores, array $dependencies_to_check, array $existing_libraries, string $prefix = 'core/'): void {
    foreach ($dependencies_to_check as $key => $dependency_name) {
      if (str_starts_with($dependency_name, $prefix) && !isset($canvas_replacing_cores[$dependency_name])) {
        $library_name_without_extension = substr($dependency_name, strlen($prefix));
        $dependency_definition = $existing_libraries[$library_name_without_extension];
        $new_library_name = "canvas.$library_name_without_extension";
        $canvas_replacing_cores[$dependency_name] = $new_library_name;
        $added_libraries[$new_library_name] = $dependency_definition;
        $main_new_library['dependencies'][] = 'canvas/' . $new_library_name;
        unset($main_new_library['dependencies'][$key]);
        $more_dependencies = $dependency_definition['dependencies'] ?? [];
        $this->convertDependencies($main_new_library, $added_libraries, $canvas_replacing_cores, $more_dependencies, $existing_libraries, $prefix);
        foreach ($added_libraries[$new_library_name]['dependencies'] as $child_dependency) {
          if (\array_key_exists($child_dependency, $canvas_replacing_cores)) {
            $added_libraries[$new_library_name]['dependencies'][] = 'canvas/' . $canvas_replacing_cores[$child_dependency];
          }
        }
      }
    }
  }

  private function buildDependencyChain(array &$all_dependencies, array $all_libraries, array $dependencies_to_check, string $admin_theme_name): void {
    foreach ($dependencies_to_check as $dependency) {
      if (str_starts_with($dependency, $admin_theme_name . '/') && !in_array($dependency, $all_dependencies)) {
        $all_dependencies[] = $dependency;
        /** @var string $internal_dependency_name */
        $internal_dependency_name = str_replace($admin_theme_name . '/', '', $dependency);
        if (isset($all_libraries[$internal_dependency_name]['dependencies'])) {
          $this->buildDependencyChain($all_dependencies, $all_libraries, $all_libraries[$internal_dependency_name]['dependencies'], $admin_theme_name);
        }
      }
    }
  }

  private function buildBaseLibrary(array &$libraries, array $default_libraries, string $theme_name, array $all_library_definitions): void {
    foreach ($default_libraries as $library_name) {
      if (str_starts_with($library_name, $theme_name . '/')) {
        $internal_library_name = str_replace($theme_name . '/', '', $library_name);
        $library_we_need = $all_library_definitions[(string) $internal_library_name] ?? [];
        if (isset($library_we_need['css'])) {
          $css = array_map(fn($item) => [
            ...$item,
            'data' => './' . $item['data'],
            'preprocess' => FALSE,
          ], $library_we_need['css']);
          $libraries['canvas.scoped.admin.css']['css'] = array_merge(
            $libraries['canvas.scoped.admin.css']['css'],
            $css,
          );
        }
        if (isset($library_we_need['dependencies'])) {
          $all_dependencies = [];
          $this->buildDependencyChain($all_dependencies, $all_library_definitions, $library_we_need['dependencies'], $theme_name);
          foreach ($all_dependencies as $dependency) {
            /** @var string $internal_dependency_name */
            $internal_dependency_name = str_replace($theme_name . '/', '', $dependency);
            $dependee_library = $all_library_definitions[$internal_dependency_name];
            if (isset($dependee_library['css'])) {
              $css = array_map(fn($item) => [
                ...$item,
                'data' => './' . $item['data'],
                'preprocess' => FALSE,
              ], $dependee_library['css']);
              $libraries['canvas.scoped.admin.css']['css'] = array_merge(
                $libraries['canvas.scoped.admin.css']['css'],
                $css,
              );
            }
          }
        }
      }
    }
  }

  /**
   * Adds libraries that extend Drupal Canvas to a centralized library.
   *
   * @param array $libraries
   *   The libraries array.
   *
   * @return void
   */
  private function buildExtensionLibraries(array &$libraries): void {
    $libraries_discovery = new YamlDiscovery('libraries', $this->moduleHandler->getModuleDirectories());

    // Any library with a 'canvasExtension' drupalSettings will be added.
    $canvas_extensions = array_reduce($libraries_discovery->getDefinitions(), function ($carry, $item) {
      if (isset($item['drupalSettings']['canvasExtension'])) {
        $carry[] = $item['provider'] . '/' . $item['id'];
      }
      return $carry;
    }, []);

    // Add the libraries as dependencies of canvas/extensions.
    if (!empty($canvas_extensions)) {
      $libraries['extensions'] = [
        'dependencies' => $canvas_extensions,
      ];
    }
  }

  /**
   * Customize core/drupal.dialog for Drupal Canvas.
   *
   * Creates a customized version of core/drupal.dialog that accounts for the
   * admin theme's overrides and extends. A dedicated library is created instead
   * of altering the existing one to avoid issues of library info being cached
   * too broadly, and so themes such as canvas_stark can use the admin theme's
   * dialog styling without it being the active theme during the request.
   *
   * @param string $admin_theme_name
   *   The admin theme to customize for.
   */
  private function customizeDialogLibrary(string $admin_theme_name): array {
    $libraries = [];

    // Set the active theme to the admin theme, then build the core libraries as
    // processed by that theme.
    $active_admin_theme = $this->themeInitialization->getActiveThemeByName($admin_theme_name);
    $actual_active_theme = $this->themeManager->getActiveTheme();
    $this->themeManager->setActiveTheme($active_admin_theme);
    $core_libraries_via_admin_theme = $this->libraryDiscoveryParser->buildByExtension('core');
    $admin_theme_library_definitions = $this->libraryDiscoveryParser->buildByExtension($admin_theme_name);
    $this->themeManager->setActiveTheme($actual_active_theme);

    // This array keeps track of the core libraries that will have Canvas equivalents.
    $canvas_replacing_cores = [
      'core/drupal.dialog' => 'canvas.drupal.dialog',
      'core/drupal.ajax' => 'canvas.drupal.ajax',
      'core/drupal.dialog.ajax' => 'canvas.drupal.dialog.ajax',
    ];

    $dialog_library_definition = $core_libraries_via_admin_theme['drupal.dialog'];
    $this->convertDependencies($dialog_library_definition, $libraries, $canvas_replacing_cores, $dialog_library_definition['dependencies'], $core_libraries_via_admin_theme);

    $ajax_library_definition = $core_libraries_via_admin_theme['drupal.ajax'];

    // Depending on the message library results in Olivero bringing in a firehose
    // of dependencies we don't want, and in some cases like the navigation JS,
    // will cause errors due to the JS expecting elements that aren't present.
    $ajax_library_definition['dependencies'] = array_diff($ajax_library_definition['dependencies'], ['core/drupal.message']);
    $ajax_library_definition['js'][] = [
      ...$ajax_library_definition['js'][0],
      'data' => 'core/misc/message.js',
    ];
    $ajax_library_definition['dependencies'][] = 'core/drupal.announce';
    $this->convertDependencies($ajax_library_definition, $libraries, $canvas_replacing_cores, $ajax_library_definition['dependencies'], $core_libraries_via_admin_theme);
    $libraries['canvas.drupal.ajax'] = $ajax_library_definition;

    $ajax_dialog_definition = $core_libraries_via_admin_theme['drupal.dialog.ajax'];
    $this->convertDependencies($ajax_dialog_definition, $libraries, $canvas_replacing_cores, $ajax_dialog_definition['dependencies'], $core_libraries_via_admin_theme);
    $libraries['canvas.drupal.dialog.ajax'] = $ajax_dialog_definition;

    // Now that we've created every library requiring an Canvas-specific version, go
    // through the dependencies declared by each and swap out any remaining core
    // versions that should be summoning the Canvas versions instead.
    $dependencies_already_added = [];
    foreach ($libraries as &$library) {
      if (!isset($library['dependencies'])) {
        continue;
      }
      foreach ($library['dependencies'] as $key => $dependency) {
        if (isset($canvas_replacing_cores[$dependency]) && !in_array($dependency, $dependencies_already_added)) {
          $dependencies_already_added[] = $dependency;
          $library['dependencies'][$key] = 'canvas/' . $canvas_replacing_cores[$dependency];
        }
      }
    }
    $libraries['canvas.drupal.dialog'] = $dialog_library_definition;

    // An additional library will be created with the admin theme's default
    // assets. This library will be added as part of a dialog open request and
    // the CSS will be scoped so it applied only within the dialog.
    $libraries['canvas.scoped.admin.css'] = [
      'css' => [],
      'js' => [],
      'dependencies' => [],
    ];
    $admin_theme_default_libraries = $active_admin_theme->getLibraries();
    $base_theme_extensions = $active_admin_theme->getBaseThemeExtensions();
    foreach ($base_theme_extensions as $theme_name => $base_theme_extension) {
      $active_base_theme = $this->themeInitialization->getActiveThemeByName($theme_name);
      $base_theme_default_libraries = $active_base_theme->getLibraries();
      $base_theme_library_definitions = $this->libraryDiscoveryParser->buildByExtension($theme_name);
      $this->buildBaseLibrary($libraries, $base_theme_default_libraries, $theme_name, $base_theme_library_definitions);
    }
    foreach ($admin_theme_default_libraries as $library_name) {
      if (str_starts_with($library_name, $admin_theme_name . '/')) {
        $internal_library_name = str_replace($admin_theme_name . '/', '', $library_name);
        $library_we_need = $admin_theme_library_definitions[(string) $internal_library_name] ?? [];
        if (isset($library_we_need['css'])) {
          $css = array_map(fn($item) => [
            ...$item,
            'data' => './' . $item['data'],
            'preprocess' => FALSE,
          ], $library_we_need['css']);
          $libraries['canvas.scoped.admin.css']['css'] = array_merge(
            $libraries['canvas.scoped.admin.css']['css'],
            $css,
          );
        }
        if (isset($library_we_need['dependencies'])) {
          $all_dependencies = [];
          $this->buildDependencyChain($all_dependencies, $admin_theme_library_definitions, $library_we_need['dependencies'], $admin_theme_name);
          foreach ($all_dependencies as $dependency) {
            /** @var string $internal_dependency_name */
            $internal_dependency_name = str_replace($admin_theme_name . '/', '', $dependency);
            $dependee_library = $admin_theme_library_definitions[$internal_dependency_name];
            if (isset($dependee_library['css'])) {
              $css = array_map(fn($item) => [
                ...$item,
                'data' => './' . $item['data'],
                'preprocess' => FALSE,
              ], $dependee_library['css']);
              $libraries['canvas.scoped.admin.css']['css'] = array_merge(
                $libraries['canvas.scoped.admin.css']['css'],
                $css,
              );
            }
          }
        }
      }
    }

    // Convert the CSS assets to the concern-grouped structure expected by a
    // library definition.
    $group_css_ids = [
      CSS_COMPONENT => 'component',
      CSS_BASE => 'base',
      CSS_LAYOUT => 'layout',
      CSS_STATE => 'state',
      CSS_THEME => 'theme',
    ];
    foreach ($libraries as &$definition) {
      $grouped_css = [];
      foreach ($definition['css'] as $css) {
        // The 'weight' key is used to represent the groupings in libraries.yml
        // for css. The 'group' key is used for CSS_AGGREGATE_THEME or
        // CSS_AGGREGATE_DEFAULT constants. Some of these are altered further
        // after they're built and may not correspond to one of the original
        // constants, so we fall back to 'component'. We cast to an int because
        // some weights could have been altered to floats to add extra precision.
        $group = $group_css_ids[(int) $css['weight']] ?? 'component';
        // Add to a group and index by filename. Make the path absolute since it
        // is referencing core assets in a library owned by Drupal Canvas.
        // We can use an empty array and let the parser fill out the rest of the
        // detail.
        $grouped_css[$group]['/' . $css['data']] = [];
      }
      $definition['css'] = $grouped_css;

      $grouped_js = [];

      foreach ($definition['js'] as $js) {
        // Index by asset name. Make the paths absolute as they are referencing
        // core in a library owned by Drupal Canvas.
        $grouped_js['/' . $js['data']] = $js;
        // The data property is no longer needed.
        unset($grouped_js[$js['data']]['data']);
      }

      $definition['js'] = $grouped_js;
    }

    $extends = $active_admin_theme->getLibrariesExtend();
    foreach ($canvas_replacing_cores as $library_name => $new_library_name) {
      if (isset($extends[$library_name])) {
        $libraries['canvas.drupal.dialog']['dependencies'] = array_merge($libraries['canvas.drupal.dialog']['dependencies'], $extends[$library_name]);
      }
    }

    // Theme overrides of core/drupal.dialog are already accounted for, but there
    // may be non-core drupal.dialog dependencies that have admin theme overrides.
    $overrides = $active_admin_theme->getLibrariesOverride();
    foreach ($overrides as $theme_overrides) {
      foreach ($theme_overrides as $library_name => $override) {
        if (in_array($library_name, $libraries['canvas.drupal.dialog']['dependencies'])) {
          [$library_source, $library_id] = explode('/', $library_name);
          // Build an admin-theme-overridden version of the dependency.
          $this->themeManager->setActiveTheme($active_admin_theme);
          $the_libraries = $this->libraryDiscoveryParser->buildByExtension($library_source);
          $this->themeManager->setActiveTheme($actual_active_theme);
          // Add the admin-theme-overridden dependency as a new library.
          $no_slash_library_name = str_replace('/', '.', $library_name);
          $replacement_library_name = 'canvas.' . $no_slash_library_name;
          $libraries[$replacement_library_name] = $the_libraries[$library_id];

          // Replace the original dependency with the overridden one.
          $libraries['canvas.drupal.dialog']['dependencies'] = array_unique(array_map(
            fn($item) => $item === $library_name ? 'canvas/' . $replacement_library_name : $item,
            $libraries['canvas.drupal.dialog']['dependencies']
          ));
        }
      }
    }

    return $libraries;
  }

}
