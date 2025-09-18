<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\canvas\AssetRenderer;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Entity\Pattern;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CanvasController {

  public function __construct(
    private readonly AssetRenderer $assetRenderer,
    protected ThemeManagerInterface $themeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'plugin.manager.field.widget')]
    protected readonly WidgetPluginManager $fieldWidgetPluginManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LibraryDiscoveryInterface $libraryDiscovery,
    private readonly RendererInterface $renderer,
    private readonly ThemeInitializationInterface $themeInitialization,
    private readonly AccountInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    private readonly UrlGeneratorInterface $urlGenerator,
  ) {}

  private const HTML = <<<HTML
<!doctype html>
<html {{ html_attributes }}>
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
  <css-placeholder token="CSS-HERE-PLEASE">
  <js-placeholder token="JS-HERE-PLEASE">
  <title>Drupal Canvas</title>
  <style>
    .canvas-loading {
      font-family: sans-serif;
      opacity: 0.5;
      display: flex;
      justify-content: center;
      align-items: center;
      inset: 0;
      position: fixed;
      animation: pulseLoading 2s infinite;
    }

    @keyframes pulseLoading {
      0%, 100% {
          opacity: 1;
      }
      50% {
          opacity: 0.5;
      }
    }
  </style>
</head>
<body {{ body_attributes }}>
  <div id="canvas" class="canvas-container"><div class="canvas-loading">Loading Drupal Canvas…</div></div>
</body>
</html>
HTML;

  public function __invoke(?string $entity_type, ?EntityInterface $entity) : HtmlResponse {
    // @phpstan-ignore-next-line function.alreadyNarrowedType
    assert($this->validateTransformAssetLibraries());
    // List of libraries to load in the preview iframe.
    $preview_libraries = [
      'system/base',
      ...$this->themeManager->getActiveTheme()->getLibraries(),
    ];

    // Assets for the preview <iframe>s. They will be rendered by
    // \Drupal\canvas\AssetRenderer and added to `drupalSettings` in
    // the response. They are used when rendering the preview <iframe>s.
    // @see ui/src/components/ComponentPreview.tsx
    $preview_assets = (new AttachedAssets())->setLibraries($preview_libraries);

    $canvas_module_path = $this->moduleHandler->getModule('canvas')->getPath();
    $dev_mode = $this->moduleHandler->moduleExists('canvas_dev_mode');
    // ⚠️ This is highly experimental and *will* be refactored.
    $ai_extension_available = $this->moduleHandler->moduleExists('canvas_ai');
    // ⚠️ This is highly experimental and *will* be refactored.
    $personalization_extension_available = $this->moduleHandler->moduleExists('canvas_personalization');
    $system_site_config = $this->configFactory->get('system.site');
    $entity_types_with_keys = [];
    $entity_type_labels = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type_definition) {
      if ($entity_type_id === 'node' || $entity_type_definition->entityClassImplements(ComponentTreeEntityInterface::class)) {
        $entity_types_with_keys[$entity_type_id] = $entity_type_definition->getKeys();
        if ($entity_type_definition->getBundleEntityType()) {
          $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
          $entity_type_labels[$entity_type_id] = array_map(fn($bundle) => $bundle['label'], $bundles);
        }
        else {
          $entity_type_labels[$entity_type_id] = $entity_type_definition->getLabel();
        }
      }
    }

    return (new HtmlResponse($this->buildHtml()))->addCacheableDependency($system_site_config)->setAttachments([
      'library' => [
        'canvas/canvas-ui',
        'canvas/extensions',
        ...$this->getTransformAssetLibraries(),
        // `drupalSettings.canvasData.v0` must be unconditionally present: in case
        // the user starts creating/editing code components.
        // This is also how draft/auto-save code components ensure all "canvas data"
        // is always available.
        // @see \Drupal\canvas\Hook\LibraryHooks::libraryInfoBuild()
        'canvas/canvasData.v0',
      ],
      'drupalSettings' => [
        'canvas' => [
          'base' => $entity_type !== NULL && $entity !== NULL
            ? Url::fromRoute('canvas.boot.entity', [
              'entity_type' => $entity_type,
              'entity' => $entity->id(),
            ])->getInternalPath()
            : Url::fromRoute('canvas.boot.empty')->getInternalPath(),
          'entityTypeKeys' => $entity_types_with_keys,
          'entityTypeLabels' => $entity_type_labels,
          'devMode' => $dev_mode,
          'aiExtensionAvailable' => $ai_extension_available,
          'personalizationExtensionAvailable' => $personalization_extension_available,
          // Allow for perfect component previews, by letting the client side
          // know what global assets to load in component preview <iframe>s.
          // @see ui/src/components/ComponentPreview.tsx
          'globalAssets' => [
            'css' => $this->assetRenderer->renderCssAssets($preview_assets),
            'jsHeader' => $this->assetRenderer->renderJsHeaderAssets($preview_assets),
            'jsFooter' => $this->assetRenderer->renderJsFooterAssets($preview_assets),
          ],
          'canvasModulePath' => $canvas_module_path,
          'permissions' => [
            'globalRegions' => $this->currentUser->hasPermission(PageRegion::ADMIN_PERMISSION),
            'patterns' => $this->currentUser->hasPermission(Pattern::ADMIN_PERMISSION),
            'codeComponents' => $this->currentUser->hasPermission(JavaScriptComponent::ADMIN_PERMISSION),
            'contentTemplates' => $this->currentUser->hasPermission(ContentTemplate::ADMIN_PERMISSION),
            'publishChanges' => $this->currentUser->hasPermission(AutoSaveManager::PUBLISH_PERMISSION),
          ],
          'contentEntityCreateOperations' => $this->getContentEntityCreateOperations(),
          'homepagePath' => $system_site_config->get('page.front'),
          'loginUrl' => $this->urlGenerator->generateFromRoute('user.login'),
        ],
        // Override actual `canvasData` with dummy data for code component editor
        // development purposes.
        'canvasData' => [
          'v0' => [
            'pageTitle' => 'This is a page title for testing purposes',
            'breadcrumbs' => [
              0 => [
                'key' => '<front>',
                'text' => 'Home',
                'url' => \base_path(),
              ],
              1 => [
                'key' => 'user.page',
                'text' => 'My account',
                'url' => \base_path() . 'user',
              ],
            ],
          ],
        ],
      ],
      // Note: the tokens here are under our control, and this accepts no user
      // input. Hence these hardcoded tokens are fine.
      'html_response_attachment_placeholders' => [
        'styles' => '<css-placeholder token="CSS-HERE-PLEASE">',
        'scripts' => '<js-placeholder token="JS-HERE-PLEASE">',
      ],
    ]);
  }

  /**
   * Sets the <html> and <body> attributes on the static HTML.
   *
   * Replaces:
   * - `{{ html_attributes }}`
   * - `{{ body_attributes }}`
   *
   * Does not replace (handled by HtmlResponseAttachmentsProcessor):
   * - `<css-placeholder token="CSS-HERE-PLEASE">`
   * - `<js-placeholder token="JS-HERE-PLEASE">`
   *
   * @see \Drupal\Core\Render\HtmlResponseAttachmentsProcessor
   */
  private function buildHtml(): string {
    $theme_config = $this->configFactory->get('system.theme');
    $admin_theme_name = $theme_config->get('admin') ?: $theme_config->get('default');
    $active_admin_theme = $this->themeInitialization->getActiveThemeByName($admin_theme_name);
    $actual_active_theme = $this->themeManager->getActiveTheme();
    $this->themeManager->setActiveTheme($active_admin_theme);
    // Create a temporary rendered html element so we can extract the attributes
    // and add them to this response. This ensures things like langcode and text
    // direction are added to the html tag as expected.
    // @see template_preprocess_html()
    // @see hook_preprocess_html()
    $html_stub = [
      '#theme' => 'html',
      'page' => [],
    ];
    $other_html = Html::load((string) $this->renderer->render($html_stub));

    // Get item 1 so it is the <html> and <body> tags rendered by Drupal, vs
    // the ones the DOMDocument returned by HTML::load() wraps everything in.
    $html_element = $other_html->getElementsByTagName('html')->item(1);
    $body_element = $other_html->getElementsByTagName('body')->item(1);

    $html_attributes = new Attribute();
    $body_attributes = new Attribute();

    if ($html_element) {
      foreach (($html_element->attributes ?? []) as $attribute) {
        $html_attributes->setAttribute($attribute->name, $attribute->value);
      }
    }
    if ($body_element) {
      foreach (($body_element->attributes ?? []) as $attribute) {
        $body_attributes->setAttribute($attribute->name, $attribute->value);
      }
    }
    $this->themeManager->setActiveTheme($actual_active_theme);
    // TRICKY: don't use core/modules/system/templates/html.html.twig nor that
    // of a theme, because those include the skip link, which assumes the
    // presence of #main-content, which does not exist in the Canvas UI.
    $build = [
      '#type' => 'inline_template',
      '#template' => self::HTML,
      '#context' => [
        'body_attributes' => $body_attributes,
        'html_attributes' => $html_attributes,
      ],
    ];
    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * Finds all asset libraries whose name starts with `canvas.transform.`.
   *
   * @return string[]
   *   A list of asset libraries.
   *
   * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase
   */
  private function getTransformAssetLibraries(): array {
    $libraries = [];
    foreach (\array_keys($this->moduleHandler->getModuleList()) as $module) {
      $module_transforms = \array_filter(\array_keys($this->libraryDiscovery->getLibrariesByExtension($module)), static fn (string $library_name) => \str_starts_with($library_name, 'canvas.transform.'));
      $libraries = [
        ...$libraries,
        ...array_map(fn ($lib_name) => "$module/$lib_name", $module_transforms),
      ];
    }
    return $libraries;
  }

  /**
   * Ensures Canvas informs developers when using missing client-side transforms.
   */
  private function validateTransformAssetLibraries(): true {
    // Find all used client-side transforms.
    $transforms = [];
    foreach ($this->fieldWidgetPluginManager->getDefinitions() as $definition) {
      if (!isset($definition['canvas']['transforms']) || !is_array($definition['canvas']['transforms'])) {
        continue;
      }
      $transforms = [...$transforms, ...array_keys($definition['canvas']['transforms'])];
    }
    $transforms = array_unique($transforms);

    // Detect used client-side transforms without a corresponding asset library.
    $encountered_transform_asset_libraries = array_map(
      fn (string $asset_library): string => substr($asset_library, strpos($asset_library, '/') + strlen('/canvas.transform.')),
      $this->getTransformAssetLibraries(),
    );
    $missing = array_diff($transforms, $encountered_transform_asset_libraries);
    if (!empty($missing)) {
      throw new \LogicException(sprintf("Client-side transforms '%s' encountered without corresponding asset libraries.", implode("', '", $missing)));
    }

    return TRUE;
  }

  /**
   * Returns the content entity create operations permissions.
   *
   * @return array
   *   Returns an array keyed by entity type IDs, containing a nested array with
   *   the bundle IDs as key, and the value being FALSE if the user doesn't
   *   access to the create operation, or the singular label for the bundle if
   *   they do.
   */
  private function getContentEntityCreateOperations(): array {
    $operations = [];
    $field_map = $this->entityFieldManager->getFieldMapByFieldType(ComponentTreeItem::PLUGIN_ID);
    foreach ($field_map as $entity_type_id => $detail) {
      $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      $field_names = \array_keys($detail);
      // This assumes one component tree field per bundle/entity.
      // If this assumption is willing to change, will need to be updated in
      // https://www.drupal.org/i/3526189.
      foreach ($field_names as $field_name) {
        $bundles = $detail[$field_name]['bundles'];
        foreach ($bundles as $bundle) {
          if ($this->entityTypeManager->getAccessControlHandler($entity_type_id)->createAccess($bundle)) {
            $operations[$entity_type_id][$bundle] = $bundleInfo[$bundle]['label'];
          }
        }
      }
    }
    return $operations;
  }

}
