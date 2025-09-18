<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource;

// cspell:ignore Tilly anzut nhsy sxnz Umso Dzyawdvr Mafgg Royu Cmsy Pmsg Lgfkq ergmkgy Ptgi Ltxk

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Tests\canvas\Kernel\Traits\CacheBustingTrait;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Drupal\Tests\canvas\Traits\CrawlerTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\CodeComponentDataProvider;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropSource\StaticPropSource;
use Drupal\canvas\Render\ImportMapResponseAttachmentsProcessor;
use Drupal\media\Entity\MediaType;
use Drupal\canvas_test_code_components\Hook\IslandCastaway;

/**
 * Tests JsComponent.
 *
 * @covers \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent
 * @group canvas
 * @group JavaScriptComponents
 *
 * @phpstan-import-type ComponentConfigEntityId from \Drupal\canvas\Entity\Component
 */
final class JsComponentTest extends ComponentSourceTestBase {

  use CiModulePathTrait;
  use UserCreationTrait;
  use CrawlerTrait;
  use CacheBustingTrait;

  protected readonly AssetResolverInterface $assetResolver;
  protected readonly CodeComponentDataProvider $codeComponentDataProvider;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas_test_code_components',
    // For testing a code component using the "video" prop shape.
    'field',
    'media_library',
    'views',
    'canvas_test_video_fixture',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->assetResolver = $this->container->get(AssetResolverInterface::class);
    $this->codeComponentDataProvider = $this->container->get(CodeComponentDataProvider::class);

    // For testing a code component using the "video" prop shape.
    $this->installEntitySchema('media');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $media_type = MediaType::create([
      'id' => 'video',
      'label' => 'Video',
      'source' => 'video_file',
    ]);
    $media_type->save();
    $source_field = $media_type->getSource()->createSourceField($media_type);
    // @phpstan-ignore-next-line
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();
    $media_type
      ->set('source_configuration', [
        'source_field' => $source_field->getName(),
      ])
      ->save();
  }

  protected function generateComponentConfig(): void {
    parent::generateComponentConfig();
    $this->container->get('config.installer')->installDefaultConfig('module', 'canvas_test_code_components');
  }

  public function testDiscovery(): array {
    self::assertSame([], $this->findCreatedComponentConfigEntities(JsComponent::SOURCE_PLUGIN_ID, 'canvas_test_code_components'));

    $this->generateComponentConfig();

    // ⚠️ It is impossible to create ineligible JavaScriptComponent config entities!
    // @see \Drupal\Tests\canvas\Kernel\Config\JavaScriptComponentValidationTest::providerTestEntityShapes()
    self::assertSame([], $this->findIneligibleComponents(JsComponent::SOURCE_PLUGIN_ID, 'canvas_test_code_components'));
    $expected_js_component_ids = array_keys(self::getExpectedSettings());
    $js_components = $this->findCreatedComponentConfigEntities(JsComponent::SOURCE_PLUGIN_ID, 'canvas_test_code_components');

    self::assertSame($expected_js_component_ids, $js_components);

    return array_combine($js_components, $js_components);
  }

  /**
   * @param array<ComponentConfigEntityId> $component_ids
   * @covers ::getReferencedPluginClass()
   * @depends testDiscovery
   */
  public function testGetReferencedPluginClass(array $component_ids): void {
    self::assertSame(
      // Code components are not plugins, but config entities!
      array_fill_keys($component_ids, NULL),
      $this->getReferencedPluginClasses($component_ids)
    );
  }

  /**
   * Tests the shape-matched `prop_field_definitions` for all code components.
   *
   * @depends testDiscovery
   */
  public function testSettings(array $component_ids): void {
    $settings = $this->getAllSettings($component_ids);
    self::assertSame(self::getExpectedSettings(), $settings);

    // Slightly more scrutiny for ComponentSources with a generated field-based
    // input UX: verifying this results in working `StaticPropSource`s is
    // sufficient, everything beyond that is covered by PropShapeRepositoryTest.
    // @see \Drupal\Tests\canvas\Kernel\PropShapeRepositoryTest::testPropShapesYieldWorkingStaticPropSources()
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase
    $components = $this->componentStorage->loadMultiple($component_ids);
    foreach ($components as $component_id => $component) {
      // Use reflection to test the private ::getDefaultStaticPropSource() method.
      assert($component instanceof Component);
      $source = $component->getComponentSource();
      $private_method = new \ReflectionMethod($source, 'getDefaultStaticPropSource');
      $private_method->setAccessible(TRUE);
      foreach (array_keys($settings[$component_id]['prop_field_definitions']) as $prop) {
        $static_prop_source = $private_method->invoke($source, $prop);
        $this->assertInstanceOf(StaticPropSource::class, $static_prop_source);
      }
    }
  }

  public static function getExpectedSettings(): array {
    return [
      'js.canvas_test_code_components_captioned_video' => [
        'prop_field_definitions' => [
          'caption' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              ['value' => 'A video'],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'displayWidth' => [
            'field_type' => 'list_integer',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              ['value' => 400],
            ],
            'expression' => 'ℹ︎list_integer␟value',
          ],
          'video' => [
            'field_type' => 'entity_reference',
            'field_storage_settings' => [
              'target_type' => 'media',
            ],
            'field_instance_settings' => [
              'handler' => 'default:media',
              'handler_settings' => [
                'target_bundles' => [
                  'video' => 'video',
                ],
              ],
            ],
            'field_widget' => 'media_library_widget',
            // ⚠️ Empty default value.
            // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::exampleValueRequiresEntity()
            'default_value' => [],
            'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:video␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟url}',
          ],
        ],
      ],
      'js.canvas_test_code_components_interactive' => [
        'prop_field_definitions' => [
          'name' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'Count']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'js.canvas_test_code_components_using_drupalsettings_get_site_data' => [
        'prop_field_definitions' => [],
      ],
      'js.canvas_test_code_components_using_imports' => [
        'prop_field_definitions' => [],
      ],
      'js.canvas_test_code_components_vanilla_image' => [
        'prop_field_definitions' => [
          'image' => [
            'field_type' => 'image',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'image_image',
            // ⚠️ Empty default value.
            // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::exampleValueRequiresEntity()
            'default_value' => [],
            'expression' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
        ],
      ],
      'js.canvas_test_code_components_with_enums' => [
        'prop_field_definitions' => [
          'favorite_color' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              [
                'value' => 'red',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
          'size' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              [
                'value' => 'small',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
        ],
      ],
      'js.canvas_test_code_components_with_link_prop' => [
        'prop_field_definitions' => [
          'link' => [
            'field_type' => 'link',
            'field_storage_settings' => [],
            'field_instance_settings' => [
              'title' => 0,
            ],
            'field_widget' => 'link_default',
            'default_value' => [
              [
                'uri' => '/llamas',
                'options' => [],
              ],
            ],
            'expression' => 'ℹ︎link␟url',
          ],
          'text' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'This is my link']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'js.canvas_test_code_components_with_no_props' => [
        'prop_field_definitions' => [],
      ],
      'js.canvas_test_code_components_with_props' => [
        'prop_field_definitions' => [
          'age' => [
            'field_type' => 'integer',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'number',
            'default_value' => [0 => ['value' => 40]],
            'expression' => 'ℹ︎integer␟value',
          ],
          'name' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'Canvas']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'js.canvas_test_code_components_with_slots' => [
        'prop_field_definitions' => [
          'name' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'Name']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
    ];
  }

  /**
   * @param array<ComponentConfigEntityId> $component_ids
   * @covers ::renderComponent()
   * @depends testDiscovery
   */
  public function testRenderComponentLive(array $component_ids): void {
    $this->assertNotEmpty($component_ids);

    // We need to force the cache busting query to ensure we use it correctly.
    $this->setCacheBustingQueryString($this->container, '2.1.0-alpha3');

    $rendered = $this->renderComponentsLive(
      $component_ids,
      get_default_input: [__CLASS__, 'getDefaultInputForGeneratedInputUx'],
    );

    // ⚠️ The `'html'` expectations are tested separately for this very complex
    // rendering.
    // @see ::testRenderComponent()
    $rendered_without_html = array_map(
      fn($expectations) => array_diff_key($expectations, ['html' => NULL]),
      $rendered,
    );

    $default_render_cache_contexts = [
      'languages:language_interface',
      'theme',
      'user.permissions',
    ];

    $default_cacheability = (new CacheableMetadata())
      ->setCacheContexts($default_render_cache_contexts);
    $module_path = self::getCiModulePath();
    $site_path = $this->siteDirectory;
    $default_libraries = [
      'canvas/asset_library.' . AssetLibrary::GLOBAL_ID,
      'canvas/astro.hydration',
    ];
    $default_html_head_links = [
      [
        [
          'rel' => 'modulepreload',
          'fetchpriority' => 'high',
          'href' => \sprintf('%s/ui/lib/astro-hydration/dist/signals.module.js?2.1.0-alpha3', $module_path),
        ],
      ],
      [
        [
          'rel' => 'modulepreload',
          'fetchpriority' => 'high',
          'href' => \sprintf('%s/ui/lib/astro-hydration/dist/preload-helper.js?2.1.0-alpha3', $module_path),
        ],
      ],
    ];
    $default_imports = [
      ImportMapResponseAttachmentsProcessor::GLOBAL_IMPORTS => [
        'preact' => \sprintf('%s/ui/lib/astro-hydration/dist/preact.module.js?2.1.0-alpha3', $module_path),
        'preact/hooks' => \sprintf('%s/ui/lib/astro-hydration/dist/hooks.module.js?2.1.0-alpha3', $module_path),
        'react/jsx-runtime' => \sprintf('%s/ui/lib/astro-hydration/dist/jsx-runtime-default.js?2.1.0-alpha3', $module_path),
        'react' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $module_path),
        'react-dom' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $module_path),
        'react-dom/client' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $module_path),
        'clsx' => \sprintf('%s/ui/lib/astro-hydration/dist/clsx.js?2.1.0-alpha3', $module_path),
        'class-variance-authority' => \sprintf('%s/ui/lib/astro-hydration/dist/class-variance-authority.js?2.1.0-alpha3', $module_path),
        'tailwind-merge' => \sprintf('%s/ui/lib/astro-hydration/dist/tailwind-merge.js?2.1.0-alpha3', $module_path),
        '@/lib/FormattedText' => \sprintf('%s/ui/lib/astro-hydration/dist/FormattedText.js?2.1.0-alpha3', $module_path),
        'next-image-standalone' => \sprintf('%s/ui/lib/astro-hydration/dist/next-image-standalone.js?2.1.0-alpha3', $module_path),
        '@/lib/utils' => \sprintf('%s/ui/lib/astro-hydration/dist/utils.js?2.1.0-alpha3', $module_path),
        '@drupal-api-client/json-api-client' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-client.js?2.1.0-alpha3', $module_path),
        'drupal-jsonapi-params' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-params.js?2.1.0-alpha3', $module_path),
        '@/lib/jsonapi-utils' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-utils.js?2.1.0-alpha3', $module_path),
        '@/lib/drupal-utils' => \sprintf('%s/ui/lib/astro-hydration/dist/drupal-utils.js?2.1.0-alpha3', $module_path),
        'swr' => \sprintf('%s/ui/lib/astro-hydration/dist/swr.js?2.1.0-alpha3', $module_path),
      ],
    ];

    $this->assertEquals([
      'js.canvas_test_code_components_captioned_video' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags([
            'config:canvas.js_component.canvas_test_code_components_captioned_video',
          ]),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_captioned_video',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/1PcAZQSkckmMSZ3XOvm8e4GTnc7DaSei5KVZ6t-eKG8.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
      'js.canvas_test_code_components_interactive' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags([
            'config:canvas.js_component.canvas_test_code_components_interactive',
          ]),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_interactive',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/ergmkgyMa0HG-_MF_afn4PkfQPtgiRr3e_k_vLtxkCs.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
      'js.canvas_test_code_components_using_imports' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags([
            'config:canvas.js_component.canvas_test_code_components_using_imports',
            'config:canvas.js_component.canvas_test_code_components_with_no_props',
            'config:canvas.js_component.canvas_test_code_components_with_props',
          ]),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_using_imports',
            'canvas/astro_island.canvas_test_code_components_with_no_props',
            'canvas/astro_island.canvas_test_code_components_with_props',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/OXEtkRiIQlg16fvA1lWA_1ggYYS5VOUJpRZ5r3ow2N8.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports + [
            ImportMapResponseAttachmentsProcessor::SCOPED_IMPORTS => [
              \sprintf('/%s/files/astro-island/OXEtkRiIQlg16fvA1lWA_1ggYYS5VOUJpRZ5r3ow2N8.js', $site_path) => [
                '@/components/canvas_test_code_components_with_no_props' => \sprintf('/%s/files/astro-island/axL0zkV0Jlcf3zuQfhx8HWxySMYQVoAZLwgGK-dxXWU.js', $site_path),
                '@/components/canvas_test_code_components_with_props' => \sprintf('/%s/files/astro-island/AFWyiY79ad8_Hbz1qqKz97PSpKgNHSYCcwBWz8QRChU.js', $site_path),
              ],
            ],
          ],
        ],
      ],
      'js.canvas_test_code_components_vanilla_image' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags([
            'config:canvas.js_component.canvas_test_code_components_vanilla_image',
          ]),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_vanilla_image',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/Ej9H8EwYfANZUT_jL84bUAXkK8F_p9-yZyj4Sxnz7C8.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
      'js.canvas_test_code_components_with_enums' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags(['config:canvas.js_component.canvas_test_code_components_with_enums']),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_with_enums',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/S_GMOfXPnSsDMzuP0bw4pnXmP2SWPmsg4LgfkqNMzsI.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
      'js.canvas_test_code_components_with_link_prop' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags(['config:canvas.js_component.canvas_test_code_components_with_link_prop']),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_with_link_prop',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/9R7mSubaIqZ03U019LY2_xnqOKyDzLzQ0y11jg724VY.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
      'js.canvas_test_code_components_with_no_props' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags(['config:canvas.js_component.canvas_test_code_components_with_no_props']),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_with_no_props',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/axL0zkV0Jlcf3zuQfhx8HWxySMYQVoAZLwgGK-dxXWU.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
      'js.canvas_test_code_components_with_props' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags(['config:canvas.js_component.canvas_test_code_components_with_props']),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_with_props',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/AFWyiY79ad8_Hbz1qqKz97PSpKgNHSYCcwBWz8QRChU.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
      'js.canvas_test_code_components_with_slots' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags([
            'config:canvas.js_component.canvas_test_code_components_with_slots',
          ]),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_with_slots',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/8gFwFAotFPDb2BVs6lhX-1X9SQtNYUoW5eN8qV6KM64.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
      'js.canvas_test_code_components_using_drupalsettings_get_site_data' => [
        'cacheability' => (clone $default_cacheability)
          ->setCacheTags(['config:canvas.js_component.canvas_test_code_components_using_drupalsettings_get_site_data']),
        'attachments' => [
          'library' => [
            'canvas/astro_island.canvas_test_code_components_using_drupalsettings_get_site_data',
            ...$default_libraries,
          ],
          'html_head_link' => [
            ...$default_html_head_links,
            [
              [
                'rel' => 'modulepreload',
                'fetchpriority' => 'high',
                'href' => \sprintf('/%s/files/astro-island/Bqd05shWDg_CVBJn_oQu0IFbb8Cz27jiqEZcqqAPfr8.js', $site_path),
              ],
            ],
          ],
          'import_maps' => $default_imports,
        ],
      ],
    ], $rendered_without_html);
  }

  /**
   * For JavaScript components, auto-saves create an extra testing dimension!
   *
   * @depends testDiscovery
   * @testWith [false, false, "live", []]
   *           [false, true, "live", []]
   *           [true, false, "draft", ["canvas__auto_save"]]
   *           [true, true, "draft", ["canvas__auto_save"]]
   */
  public function testRenderJsComponent(bool $preview_requested, bool $auto_save_exists, string $expected_result, array $additional_expected_cache_tags, array $component_ids): void {
    // We need to force the cache busting query to ensure we use it correctly.
    $this->setCacheBustingQueryString($this->container, '2.1.0-alpha3');

    $this->generateComponentConfig();
    foreach ($this->componentStorage->loadMultiple($component_ids) as $component) {
      assert($component instanceof Component);
      $source = $component->getComponentSource();
      \assert($source instanceof JsComponent);
      $expected_cacheability = (new CacheableMetadata())
        ->addCacheTags($additional_expected_cache_tags)
        ->addCacheableDependency($source->getJavaScriptComponent());
      $this->assertRenderedAstroIsland($component, $preview_requested, $auto_save_exists, $expected_result, $expected_cacheability);
    }
  }

  /**
   * Helper function to render a component and assert the result.
   *
   * @param \Drupal\canvas\Entity\Component $component
   * @param bool $preview_requested
   * @param bool $auto_save_exists
   * @param string $expected_result
   *
   * @return void
   */
  private function assertRenderedAstroIsland(
    Component $component,
    bool $preview_requested,
    bool $auto_save_exists,
    string $expected_result,
    CacheableDependencyInterface $expected_cacheability,
  ): void {
    $source = $component->getComponentSource();
    \assert($source instanceof JsComponent);
    $js_component_id = $component->get('source_local_id');
    $js_component = $source->getJavaScriptComponent();
    $expected_component_compiled_js = $js_component->getJs();
    $expected_component_compiled_css = $js_component->getCss();
    $expected_component_props = $js_component->getProps();

    // Create auto-save entry if that's expected by this test case.
    if ($auto_save_exists) {
      // 'importedJsComponents' is a value sent by the client that is used to
      // determine Javascript Code component dependencies and is not saved
      // directly on the backend.
      // Ensure that the current set of imported JS components continues to
      // be respected.
      // @see \Drupal\canvas\Entity\JavaScriptComponent::addJavaScriptComponentsDependencies().
      $css = $js_component->get('css');
      // We need to make this different to the saved value.
      $css['original'] .= '/**/';
      $js_component->set('css', $css);
      $js_component->updateFromClientSide([
        'importedJsComponents' => array_map(
          fn (string $config_name): string => str_replace('canvas.js_component.', '', $config_name),
          $js_component->toArray()['dependencies']['enforced']['config'] ?? []
        ),
        'compiled_js' => $js_component->getJs(),
      ]);
      $this->container->get(AutoSaveManager::class)->saveEntity($js_component);
    }

    $island = $source->renderComponent([
      'props' => $expected_component_props,
    ], 'some-uuid', $preview_requested);

    $this->assertEquals($expected_cacheability, CacheableMetadata::createFromRenderArray($island));

    $crawler = $this->crawlerForRenderArray($island);

    $element = $crawler->filter('canvas-island');
    self::assertCount(1, $element);

    // Note that ::renderComponent adds both canvas_uuid and canvas_slot_ids props but
    // they should not be present as props in the canvas-island element.
    // Ternary because empty arrays are encoded as '[]' in Json::encode().
    $json_expected = (empty($expected_component_props)) ? '{}' :
      Json::encode(\array_map(static fn(mixed $value): array => [
        'raw',
        $value,
      ], $expected_component_props));
    self::assertJsonStringEqualsJsonString($json_expected, $element->attr('props') ?? '');

    // Assert rendered code component's JS.
    $asset_wrapper = $this->container->get(StreamWrapperManagerInterface::class)->getViaScheme('assets');
    \assert($asset_wrapper instanceof StreamWrapperInterface);
    \assert(\method_exists($asset_wrapper, 'getDirectoryPath'));
    $directory_path = $asset_wrapper->getDirectoryPath();
    $js_hash = Crypt::hmacBase64($expected_component_compiled_js, $js_component->uuid());
    // @phpstan-ignore-next-line
    $expected_js_filename = match ($expected_result) {
      'live' => \sprintf('/%s/astro-island/%s.js', $directory_path, $js_hash),
      'draft' => \sprintf('/canvas/api/v0/auto-saves/js/%s/%s', JavaScriptComponent::ENTITY_TYPE_ID, $js_component_id),
    };
    $element_js_script = $element->attr('component-url');
    self::assertEquals($expected_js_filename, $element_js_script);

    $preloads = \array_column($island['#attached']['html_head_link'], 0);
    $hrefs = \array_column($preloads, 'href');
    self::assertContains($expected_js_filename, $hrefs);

    // Assert import maps are attached.
    $preact_import = NestedArray::getValue($island, ['#attached', 'import_maps', ImportMapResponseAttachmentsProcessor::GLOBAL_IMPORTS, 'preact']);
    self::assertNotNull($preact_import);

    // Assert rendered code component's CSS, if any.
    if ($source->getJavaScriptComponent()->hasCss()) {
      // @phpstan-ignore-next-line
      $expected_css_asset_library = match ($expected_result) {
        'live' => 'canvas/astro_island.%s',
        'draft' => 'canvas/astro_island.%s.draft',
      };
      self::assertContains(\sprintf($expected_css_asset_library, $js_component_id), $island['#attached']['library']);

      // Assert rendered code component's CSS.
      $css_asset = $this->assetResolver->getCssAssets(AttachedAssets::createFromRenderArray($island), FALSE);
      // @phpstan-ignore-next-line
      $css_filename = match ($expected_result) {
        'live' => \sprintf(
          'assets://astro-island/%s.css',
          Crypt::hmacBase64($expected_component_compiled_css, $js_component->uuid()),
        ),
        'draft' => "canvas/api/v0/auto-saves/css/js_component/$js_component_id",
      };
      self::assertEquals($css_filename, reset($css_asset)['data']);
    }
  }

  public function testRewriteExampleUrl(): void {
    self::assertNull(Component::load('js.canvas_test_code_components_captioned_video'));
    $this->generateComponentConfig();
    $video_component = Component::load('js.canvas_test_code_components_captioned_video');
    // @phpstan-ignore-next-line staticMethod.impossibleType
    self::assertInstanceOf(ComponentInterface::class, $video_component);

    $source = $video_component->getComponentSource();
    self::assertInstanceOf(JsComponent::class, $source);

    // Assert that the two example videos Canvas ships with are rewritten to include
    // the relative path on the current site.
    $module_path = \Drupal::service(ModuleExtensionList::class)->getPath('canvas');
    self::assertSame(\base_path() . $module_path . JsComponent::EXAMPLE_VIDEO_HORIZONTAL, $source->rewriteExampleUrl(JsComponent::EXAMPLE_VIDEO_HORIZONTAL));
    self::assertSame(\base_path() . $module_path . JsComponent::EXAMPLE_VIDEO_VERTICAL, $source->rewriteExampleUrl(JsComponent::EXAMPLE_VIDEO_VERTICAL));

    // Assert that full URLs are left alone.
    self::assertSame('https://www.example.com/', $source->rewriteExampleUrl('https://www.example.com/'));

    // Assert that any other `/ui/assets/…` URL is disallowed, not even one to
    // the containing directory.
    // Rationale: avoid security concerns by not relying on file_exists(),
    // potential bypasses of that, and instead only have 2 allowed examples.
    try {
      self::assertSame('/ui/assets/videos', dirname(JsComponent::EXAMPLE_VIDEO_VERTICAL));
      $source->rewriteExampleUrl('/ui/assets/videos');
      $this->fail();
    }
    catch (\InvalidArgumentException $e) {
      self::assertSame('Default images for Javascript Components must be a fully-qualified URL with both scheme and host.', $e->getMessage());
    }

    // Assert that neither a prefix nor a suffix is tolerated: only these exact
    // 2 strings are allowed.
    // Rationale: configuration management DX is degraded if the example is
    // environment-dependent (Drupal served from root vs subdir, Canvas module
    // installation location).
    try {
      $source->rewriteExampleUrl('/subdir' . JsComponent::EXAMPLE_VIDEO_VERTICAL);
      $this->fail();
    }
    catch (\InvalidArgumentException $e) {
      self::assertSame('Default images for Javascript Components must be a fully-qualified URL with both scheme and host.', $e->getMessage());
    }
    try {
      $source->rewriteExampleUrl(JsComponent::EXAMPLE_VIDEO_VERTICAL . '?foo=bar');
      $this->fail();
    }
    catch (\InvalidArgumentException $e) {
      self::assertSame('Default images for Javascript Components must be a fully-qualified URL with both scheme and host.', $e->getMessage());
    }
  }

  /**
   * @covers ::calculateDependencies()
   * @depends testDiscovery
   */
  public function testCalculateDependencies(array $component_ids): void {
    self::assertSame([
      'js.canvas_test_code_components_captioned_video' => [
        'config' => [
          'field.field.media.video.field_media_video_file',
          'media.type.video',
          'canvas.js_component.canvas_test_code_components_captioned_video',
        ],
        'content' => [],
        'module' => [
          'core',
          'file',
          'media',
          'media_library',
          'options',
        ],
      ],
      'js.canvas_test_code_components_interactive' => [
        'module' => [
          'core',
        ],
        'config' => [
          'canvas.js_component.canvas_test_code_components_interactive',
        ],
      ],
      'js.canvas_test_code_components_using_drupalsettings_get_site_data' => [
        'config' => [
          'canvas.js_component.canvas_test_code_components_using_drupalsettings_get_site_data',
        ],
      ],
      'js.canvas_test_code_components_using_imports' => [
        'config' => [
          'canvas.js_component.canvas_test_code_components_using_imports',
        ],
      ],
      'js.canvas_test_code_components_vanilla_image' => [
        'config' => [
          'image.style.canvas_parametrized_width',
          'canvas.js_component.canvas_test_code_components_vanilla_image',
        ],
        'module' => [
          'file',
          'image',
        ],
      ],
      'js.canvas_test_code_components_with_enums' => [
        'module' => [
          'core',
          'options',
        ],
        'config' => [
          'canvas.js_component.canvas_test_code_components_with_enums',
        ],
      ],
      'js.canvas_test_code_components_with_link_prop' => [
        'module' => [
          'core',
          'link',
        ],
        'config' => [
          'canvas.js_component.canvas_test_code_components_with_link_prop',
        ],
      ],
      'js.canvas_test_code_components_with_no_props' => [
        'config' => [
          'canvas.js_component.canvas_test_code_components_with_no_props',
        ],
      ],
      'js.canvas_test_code_components_with_props' => [
        'module' => [
          'core',
        ],
        'config' => [
          'canvas.js_component.canvas_test_code_components_with_props',
        ],
      ],
      'js.canvas_test_code_components_with_slots' => [
        'module' => [
          'core',
        ],
        'config' => [
          'canvas.js_component.canvas_test_code_components_with_slots',
        ],
      ],
    ], $this->callSourceMethodForEach('calculateDependencies', $component_ids));
  }

  /**
   * {@inheritdoc}
   */
  public static function providerRenderComponentFailure(): \Generator {
    $component_id = JsComponent::componentIdFromJavascriptComponentId('canvas_test_code_components_with_props');
    yield "JS Component with valid props, without exception" => [
      'component_id' => $component_id,
      'inputs' => [
        'age' => 19,
        'name' => 'Tilly',
      ],
      'expected_validation_errors' => [],
      'expected_exception' => NULL,
      'expected_output_selector' => \sprintf('canvas-island[uid="%s"][props*="Tilly"][props*="19"]', self::UUID_CRASH_TEST_DUMMY),
    ];

    yield "JS Component with valid props, JSON encoding exception" => [
      'component_id' => $component_id,
      'inputs' => [
        'age' => 19,
        'name' => IslandCastaway::WILSON,
      ],
      'expected_validation_errors' => [],
      'expected_exception' => [
        'class' => \Error::class,
        'message' => 'Wilson is a ball, not a person',
      ],
      'expected_output_selector' => NULL,
    ];

    yield "JS Component with invalid props, validation error" => [
      'component_id' => $component_id,
      'inputs' => [
        'age' => [
          'sourceType' => "static:field_item:string",
          'value' => "It's rude to ask",
          'expression' => (string) new FieldTypePropExpression('string', 'value'),
        ],
        'name' => 'Tilly',
      ],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.age', self::UUID_CRASH_TEST_DUMMY) => 'String value found, but an integer or an object is required. The provided value is: "It\'s rude to ask".',
      ],
      'expected_exception' => NULL,
      // JsComponents can recover from invalid inputs.
      'expected_output_selector' => \sprintf('canvas-island[uid="%s"]', self::UUID_CRASH_TEST_DUMMY),
    ];

    yield "JS Component with missing props, validation error" => [
      'component_id' => $component_id,
      'inputs' => [],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.name', self::UUID_CRASH_TEST_DUMMY) => 'The property name is required.',
      ],
      'expected_exception' => NULL,
      // JsComponents can recover from invalid inputs.
      'expected_output_selector' => \sprintf('canvas-island[uid="%s"]', self::UUID_CRASH_TEST_DUMMY),
    ];
  }

  /**
   * Tests that component dependencies are properly added to import maps.
   *
   * @testWith [false, false, false, "live"]
   *           [false, false, true, "live"]
   *           [false, true, false, "live"]
   *           [false, true, true, "live"]
   *           [true, false, false, "draft"]
   *           [true, false, true, "draft"]
   *           [true, true, false, "draft"]
   *           [true, true, true, "draft"]
   */
  public function testImportMaps(bool $preview, bool $create_auto_save, bool $create_dependency_auto_save, string $dependencies_expected_result): void {
    assert(in_array($dependencies_expected_result, ['draft', 'live'], TRUE));
    $file_generator = $this->container->get(FileUrlGeneratorInterface::class);
    \assert($file_generator instanceof FileUrlGeneratorInterface);

    $nested_dependency_js_component = JavaScriptComponent::create([
      'machineName' => 'nested_dependency_component',
      'name' => 'Nested Dependency Component',
      'status' => TRUE,
      'props' => [],
      'slots' => [],
      'css' => [
        'original' => '.dependency { color: blue; }',
        'compiled' => '.dependency{color:blue;}',
      ],
      'js' => [
        'original' => 'console.log("nested dependency loaded");',
        'compiled' => 'console.log("nested dependency loaded");',
      ],
      'dataDependencies' => [],
    ]);
    $nested_dependency_js_component->save();
    // Create a dependency component first
    $dependency_js_component = JavaScriptComponent::create([
      'machineName' => 'dependency_component',
      'name' => 'Dependency Component',
      'status' => TRUE,
      'props' => [],
      'slots' => [],
      'css' => [
        'original' => '.dependency { color: blue; }',
        'compiled' => '.dependency{color:blue;}',
      ],
      'js' => [
        'original' => 'console.log("dependency loaded");',
        'compiled' => 'console.log("dependency loaded");',
      ],
      'dataDependencies' => [],
    ]);
    $dependency_js_component->save();
    $js_component_data = $dependency_js_component->normalizeForClientSide()->values;
    $js_component_data['importedJsComponents'] = ['nested_dependency_component'];
    $dependency_js_component->updateFromClientSide($js_component_data);
    $dependency_js_component->save();

    $dependency_js_component_without_css = JavaScriptComponent::create([
      'machineName' => 'dependency_component_no_css',
      'name' => 'Dependency Component No CSS',
      'status' => TRUE,
      'props' => [],
      'slots' => [],
      'css' => [
        'original' => '',
        'compiled' => '',
      ],
      'js' => [
        'original' => 'console.log("dependency with no css loaded");',
        'compiled' => 'console.log("dependency with no css loaded");',
      ],
      'dataDependencies' => [],
    ]);
    $dependency_js_component_without_css->save();

    // Create the main component that depends on the dependency component.
    $js_component = JavaScriptComponent::create([
      'machineName' => $this->randomMachineName(),
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => TRUE,
      'props' => [
        'title' => [
          'type' => 'string',
          'title' => 'Title',
          'examples' => ['A title'],
        ],
      ],
      'required' => ['title'],
      'slots' => [],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test{display:none;}',
      ],
      'js' => [
        'original' => 'console.log( "hey" );',
        'compiled' => 'console.log("hey");',
      ],
      'dataDependencies' => [],
    ]);
    // Add the dependency through client API.
    $js_component_data = $js_component->normalizeForClientSide()->values;
    $js_component_data['importedJsComponents'] = ['dependency_component', 'dependency_component_no_css'];
    $js_component->updateFromClientSide($js_component_data);
    $js_component->save();

    $autoSave = $this->container->get(AutoSaveManager::class);
    assert($autoSave instanceof AutoSaveManager);
    $touch_component = function (JavaScriptComponent $component) {
      $css = $component->get('css');
      // We need to make this different to the saved value.
      $css['original'] .= '/**/';
      $component->set('css', $css);
    };
    if ($create_auto_save) {
      $touch_component($js_component);
      $js_component->updateFromClientSide([
        'importedJsComponents' => [
          'dependency_component',
          'dependency_component_no_css',
        ],
        'compiledJs' => $js_component->getJs(),
      ]);
      $autoSave->saveEntity($js_component);
    }
    if ($create_dependency_auto_save) {
      $touch_component($dependency_js_component);
      $dependency_js_component->updateFromClientSide([
        'importedJsComponents' => ['nested_dependency_component'],
        'compiledJs' => $dependency_js_component->getJs(),
      ]
      );
      $autoSave->saveEntity($dependency_js_component);

      $touch_component($dependency_js_component_without_css);
      $dependency_js_component_without_css->updateFromClientSide([
        'importedJsComponents' => [],
        'compiledJs' => $dependency_js_component_without_css->getJs(),
      ]);

      $autoSave->saveEntity($dependency_js_component_without_css);

      $touch_component($nested_dependency_js_component);
      $nested_dependency_js_component->updateFromClientSide([
        'importedJsComponents' => [],
        'compiledJs' => $nested_dependency_js_component->getJs(),
      ]);
      $autoSave->saveEntity($nested_dependency_js_component);
    }

    $component = Component::load(JsComponent::componentIdFromJavascriptComponentId((string) $js_component->id()));
    \assert($component instanceof ComponentInterface);
    $source = $component->getComponentSource();
    $rendered_component = $source->renderComponent([], 'test-uuid', $preview);
    self::assertArrayHasKey('#import_maps', $rendered_component);
    self::assertArrayHasKey(ImportMapResponseAttachmentsProcessor::SCOPED_IMPORTS, $rendered_component['#import_maps']);
    $scoped_import_maps = $rendered_component['#import_maps']['scopes'];
    $dependency_import_key = $dependency_js_component->getComponentUrl($file_generator, $preview);
    $nested_dependency_key = $nested_dependency_js_component->getComponentUrl($file_generator, $preview);
    $dependency_without_css_import_key = $dependency_js_component_without_css->getComponentUrl($file_generator, $preview);
    self::assertArrayHasKey($dependency_import_key, $scoped_import_maps);
    self::assertNotEmpty($rendered_component['#attached']['library']);
    $attached_libraries = $rendered_component['#attached']['library'];
    // The dependency without CSS should ALSO have its library attached, because
    // that is how every code component's dependency on the global asset library
    // is declared.
    if ($preview) {
      self::assertContains('canvas/astro_island.dependency_component_no_css.draft', $attached_libraries);
      self::assertNotContains('canvas/astro_island.dependency_component_no_css', $attached_libraries);
    }
    else {
      self::assertNotContains('canvas/astro_island.dependency_component_no_css.draft', $attached_libraries);
      self::assertContains('canvas/astro_island.dependency_component_no_css', $attached_libraries);
    }
    if ($dependencies_expected_result === 'draft') {
      $nested_dependency_js_path = base_path() . 'canvas/api/v0/auto-saves/js/js_component/nested_dependency_component';
      self::assertContains('canvas/astro_island.dependency_component.draft', $attached_libraries);
      self::assertContains('canvas/astro_island.nested_dependency_component.draft', $attached_libraries);
      self::assertNotContains('canvas/astro_island.dependency_component', $attached_libraries);
    }
    else {
      $nested_dependency_js_path = $file_generator->generateString($nested_dependency_js_component->getJsPath());
      self::assertContains('canvas/astro_island.dependency_component', $attached_libraries);
      self::assertNotContains('canvas/astro_island.dependency_component.draft', $attached_libraries);
    }
    self::assertEquals(['@/components/nested_dependency_component' => $nested_dependency_js_path], $scoped_import_maps[$dependency_import_key]);
    self::assertArrayNotHasKey($nested_dependency_key, $scoped_import_maps);
    self::assertArrayNotHasKey($dependency_without_css_import_key, $scoped_import_maps);

    // If we created an auto-save entry for the main component, and we are in
    // preview ensure that if the dependencies are changed in the auto-save
    // entry it is reflected in the import map and attached libraries.
    if ($create_auto_save && $preview) {
      // Remove both dependencies from the auto-save entry.
      $touch_component($js_component);
      $js_component->updateFromClientSide([
        'importedJsComponents' => [],
        'compiledJs' => $js_component->getJs(),
      ]);
      $autoSave->saveEntity(
        $js_component,
      );
      $rendered_component = $source->renderComponent([], 'test-uuid', $preview);
      self::assertArrayHasKey('#import_maps', $rendered_component);
      self::assertArrayNotHasKey(ImportMapResponseAttachmentsProcessor::SCOPED_IMPORTS, $rendered_component['#import_maps']);
      self::assertNotEmpty($rendered_component['#attached']['library']);
      self::assertEmpty(array_filter(
        $rendered_component['#attached']['library'],
        static fn($library) => str_contains($library, 'dependency_component')
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getExpectedClientSideInfo(): array {
    return [
      'js.canvas_test_code_components_captioned_video' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="Captioned video"][props*="bird_vertical"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'video' => [
            'required' => TRUE,
            'jsonSchema' => [
              'title' => 'video',
              'type' => 'object',
              'required' => ['src'],
              'properties' => [
                'src' => [
                  'title' => 'Video URL',
                  'type' => 'string',
                  'format' => 'uri-reference',
                  'contentMediaType' => 'video/*',
                  'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$',
                ],
                'poster' => [
                  'title' => 'Image URL',
                  'type' => 'string',
                  'format' => 'uri-reference',
                  'contentMediaType' => 'image/*',
                  'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$',
                ],
              ],
            ],
            'sourceType' => 'static:field_item:entity_reference',
            'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:video␝field_media_video_file␞␟entity␜␜entity:file␝uri␞␟url}',
            'sourceTypeSettings' => [
              'storage' => [
                'target_type' => 'media',
              ],
              'instance' => [
                'handler' => 'default:media',
                'handler_settings' => [
                  'target_bundles' => [
                    'video' => 'video',
                  ],
                ],
              ],
            ],
            'default_values' => [
              'source' => [],
              'resolved' => [
                'src' => rtrim(\base_path(), '/') . self::getCiModulePath() . '/ui/assets/videos/bird_vertical.mp4',
                'poster' => 'https://placehold.co/1080x1920.png?text=Vertical',
              ],
            ],
          ],
          'displayWidth' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'integer',
              'enum' => [200, 300, 400, 500],
            ],
            'sourceType' => 'static:field_item:list_integer',
            'expression' => 'ℹ︎list_integer␟value',
            'sourceTypeSettings' => [
              'storage' => [
                'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
              ],
            ],
            'default_values' => [
              'source' => [
                0 => ['value' => 400],
              ],
              'resolved' => 400,
            ],
          ],
          'caption' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 'A video'],
              ],
              'resolved' => 'A video',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_interactive' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="Interactive"][props*="name"][props*="Count"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => [
          'slots' => [
            'description' => [
              'title' => 'Description',
              'examples' => ['<p>Example description</p>'],
            ],
          ],
        ],
        'propSources' => [
          'name' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 'Count'],
              ],
              'resolved' => 'Count',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_using_drupalsettings_get_site_data' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="Using drupalSettings getSiteData"][props="{}"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => ['slots' => []],
        'propSources' => [],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_using_imports' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="using imports"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => ['slots' => []],
        'propSources' => [],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_vanilla_image' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="Vanilla Image"][props*="placehold.co"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'image' => [
            'required' => FALSE,
            'jsonSchema' => [
              'title' => 'image',
              'type' => 'object',
              'required' => [
                0 => 'src',
              ],
              'properties' => [
                'src' => [
                  'title' => 'Image URL',
                  'type' => 'string',
                  'format' => 'uri-reference',
                  'contentMediaType' => 'image/*',
                  'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$',
                ],
                'alt' => [
                  'title' => 'Alternative text',
                  'type' => 'string',
                ],
                'width' => [
                  'title' => 'Image width',
                  'type' => 'integer',
                ],
                'height' => [
                  'title' => 'Image height',
                  'type' => 'integer',
                ],
              ],
            ],
            'sourceType' => 'static:field_item:image',
            'expression' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
            'default_values' => [
              'source' => [],
              'resolved' => [
                'src' => 'https://placehold.co/1200x900@2x.png',
                'width' => 1200,
                'height' => 900,
                'alt' => 'Example image placeholder',
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_with_enums' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="With enums"][props*="red"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => [
          'slots' => [],
        ],
        'propSources' => [
          'favorite_color' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                'red',
                'green',
                'blue',
              ],
            ],
            'sourceType' => 'static:field_item:list_string',
            'expression' => 'ℹ︎list_string␟value',
            'sourceTypeSettings' => [
              'storage' => [
                'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
              ],
            ],
            'default_values' => [
              'source' => [
                [
                  'value' => 'red',
                ],
              ],
              'resolved' => 'red',
            ],
          ],
          'size' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                'small',
                'regular',
                'large',
              ],
            ],
            'sourceType' => 'static:field_item:list_string',
            'expression' => 'ℹ︎list_string␟value',
            'sourceTypeSettings' => [
              'storage' => [
                'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
              ],
            ],
            'default_values' => [
              'source' => [
                [
                  'value' => 'small',
                ],
              ],
              'resolved' => 'small',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_with_link_prop' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="My Code Component Link"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'text' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 'This is my link'],
              ],
              'resolved' => 'This is my link',
            ],
          ],
          'link' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'format' => 'uri-reference',
            ],
            'sourceType' => 'static:field_item:link',
            'expression' => 'ℹ︎link␟url',
            'sourceTypeSettings' => [
              'instance' => [
                'title' => 0,
              ],
            ],
            'default_values' => [
              'source' => [
                0 => [
                  'uri' => '/llamas',
                  'options' => [],
                ],
              ],
              'resolved' => '/llamas',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_with_no_props' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="With no props"][props="{}"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => ['slots' => []],
        'propSources' => [],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_with_props' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="With props"][props*="name"][props*="Canvas"][props*="age"][props*="40"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'name' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 'Canvas'],
              ],
              'resolved' => 'Canvas',
            ],
          ],
          'age' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'integer',
            ],
            'sourceType' => 'static:field_item:integer',
            'expression' => 'ℹ︎integer␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 40],
              ],
              'resolved' => 40,
            ],
          ],
        ],
        'transforms' => [],
      ],
      'js.canvas_test_code_components_with_slots' => [
        'expected_output_selectors' => [
          'canvas-island[opts*="With slot"][props*="name"][props*="Name"]',
          'script[blocking="render"][src*="/ui/lib/astro-hydration/dist/client.js"]',
        ],
        'source' => 'Code component',
        'metadata' => [
          'slots' => [
            'description' => [
              'title' => 'Description',
              'examples' => ['<p>Example description</p>'],
            ],
          ],
        ],
        'propSources' => [
          'name' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 'Name'],
              ],
              'resolved' => 'Name',
            ],
          ],
        ],
        'transforms' => [],
      ],
    ];
  }

  /**
   * @param array<ComponentConfigEntityId> $component_ids
   *   The component IDs to test.
   *
   * @covers ::getClientSideInfo()
   * @depends testDiscovery
   */
  public function testGetClientSideInfo(array $component_ids): void {
    parent::testGetClientSideInfo($component_ids);

    // Grab one of the test components.
    $component = Component::load(JsComponent::componentIdFromJavascriptComponentId("canvas_test_code_components_with_props"));
    assert($component instanceof ComponentInterface);
    $source = $component->getComponentSource();
    assert($source instanceof JsComponent);
    $js_component = $source->getJavaScriptComponent();
    // Create an auto-save entry for this test code component.
    $js_component->set('name', 'With props - Draft');
    $autoSave = $this->container->get(AutoSaveManager::class);
    $autoSave->saveEntity($js_component);

    $client_side_info_when_auto_save_exists = $source->getClientSideInfo($component);
    $this->assertRenderArrayMatchesSelectors($client_side_info_when_auto_save_exists['build'], ['canvas-island[opts*="With props - Draft"][props*="name"][props*="Canvas"][props*="age"][props*="40"]']);
  }

  protected function createAndSaveInUseComponentForFallbackTesting(): ComponentInterface {
    $js_component_id = $this->randomMachineName();
    $js_component = JavaScriptComponent::create([
      'machineName' => $js_component_id,
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => FALSE,
      'props' => [],
      'required' => [],
      'slots' => [
        'slot1' => [
          'title' => 'Slot 1',
          'description' => 'Slot 1 innit.',
        ],
        'slot2' => [
          'title' => 'Slot 2',
          'description' => 'This is slot 2.',
        ],
      ],
      'js' => [
        'original' => 'console.log("hey");',
        'compiled' => 'console.log("hey");',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test { display: none; }',
      ],
      'dataDependencies' => [],
    ]);
    $js_component->enable()->save();
    $component_id = JsComponent::componentIdFromJavascriptComponentId($js_component_id);
    /** @var \Drupal\canvas\Entity\ComponentInterface */
    return Component::load($component_id);
  }

  protected function createAndSaveUnusedComponentForFallbackTesting(): ComponentInterface {
    $js_component_id = $this->randomMachineName();
    $js_component = JavaScriptComponent::create([
      'machineName' => $js_component_id,
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => FALSE,
      'props' => [],
      'required' => [],
      'slots' => [],
      'js' => [
        'original' => 'console.log("hey");',
        'compiled' => 'console.log("hey");',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test { display: none; }',
      ],
      'dataDependencies' => [],
    ]);
    $js_component->enable()->save();
    $component_id = JsComponent::componentIdFromJavascriptComponentId($js_component_id);
    /** @var \Drupal\canvas\Entity\ComponentInterface */
    return Component::load($component_id);
  }

  protected function deleteConfigAndTriggerComponentFallback(ComponentInterface $used_component, ComponentInterface $unused_component): void {
    $source = $used_component->getComponentSource();
    \assert($source instanceof JsComponent);
    $source->getJavaScriptComponent()->delete();
    $source = $unused_component->getComponentSource();
    \assert($source instanceof JsComponent);
    $source->getJavaScriptComponent()->delete();
  }

  protected function recoverComponentFallback(ComponentInterface $component): void {
    $component_id = $component->id();
    \assert(\is_string($component_id));
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent::componentIdFromJavascriptComponentId()
    [, $js_component_id] = \explode('.', $component_id, 2);
    $js_component = JavaScriptComponent::create([
      'machineName' => $js_component_id,
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => FALSE,
      'props' => [],
      'required' => [],
      'slots' => [
        'slot1' => [
          'title' => 'Slot 1',
          'description' => 'Slot 1 innit.',
        ],
        'slot2' => [
          'title' => 'Slot 2',
          'description' => 'This is slot 2.',
        ],
      ],
      'js' => [
        'original' => 'console.log("hey");',
        'compiled' => 'console.log("hey");',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test { display: none; }',
      ],
      'dataDependencies' => [],
    ]);
    $js_component->enable()->save();
  }

  public function testVersionDeterminability(): void {
    $js_component = JavaScriptComponent::create([
      'machineName' => 'joy_is_everything',
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => FALSE,
      'props' => [],
      'required' => [],
      'slots' => [
        'joy' => [
          'title' => 'Joy',
          'description' => "I see eyes like sunken ships, falling slowly in the waters.",
          'examples' => [
            'Even the deepest anchor in the middle of the ocean will yield to times of slaughter',
          ],
        ],
      ],
      'js' => [
        'original' => 'console.log("hey");',
        'compiled' => 'console.log("hey");',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test { display: none; }',
      ],
      'dataDependencies' => [],
    ]);
    $violations = $js_component->getTypedData()->validate();
    self::assertCount(0, $violations);

    // Save and enable to create a component.
    $js_component->enable()->save();
    $corresponding_component = Component::load(JsComponent::SOURCE_PLUGIN_ID . '.joy_is_everything');
    assert($corresponding_component instanceof Component);

    $original_version = $corresponding_component->getActiveVersion();
    $versions = [$original_version];
    self::assertCount(1, array_unique($versions));

    // Change the slot example.
    $js_component->set('slots', [
      'joy' => [
        'title' => 'Joy',
        'description' => "I see eyes like sunken ships, falling slowly in the waters.",
        'examples' => [
          'A pilot light of hope spins around, it illuminates the strobe',
        ],
      ],
    ])->save();
    $second_version_component = Component::load(JsComponent::SOURCE_PLUGIN_ID . '.joy_is_everything');
    assert($second_version_component instanceof Component);

    $second_version = $second_version_component->getActiveVersion();
    self::assertNotEquals($original_version, $second_version);
    $versions[] = $second_version;
    self::assertCount(2, array_unique($versions));

    // Add a slot.
    $js_component->set('slots', [
      'joy' => [
        'title' => 'Joy',
        'description' => "I see eyes like sunken ships, falling slowly in the waters.",
        'examples' => [
          'A pilot light of hope spins around, it illuminates the strobe',
        ],
      ],
      'road' => [
        'title' => 'Road ahead',
        'description' => "Somewhere in space and time when I'm looking ahead",
        'examples' => [
          "There's a road that could change everything",
        ],
      ],
    ])->save();

    $third_version_component = Component::load(JsComponent::SOURCE_PLUGIN_ID . '.joy_is_everything');
    assert($third_version_component instanceof Component);

    $third_version = $third_version_component->getActiveVersion();
    $versions[] = $third_version;
    self::assertCount(3, array_unique($versions));

    // Changing the slot description should not trigger a new version.
    $js_component->set('slots', [
      'joy' => [
        'title' => 'Joy',
        'description' => "I see eyes like sunken ships, falling slowly in the waters.",
        'examples' => [
          'A pilot light of hope spins around, it illuminates the strobe',
        ],
      ],
      'road' => [
        'title' => 'Road ahead',
        'description' => "A woven maze that can even catch the spider within",
        'examples' => [
          "There's a road that could change everything",
        ],
      ],
    ])->save();

    $fourth_version_component = Component::load(JsComponent::SOURCE_PLUGIN_ID . '.joy_is_everything');
    assert($fourth_version_component instanceof Component);

    $fourth_version = $fourth_version_component->getActiveVersion();
    self::assertEquals($fourth_version, $third_version);
    $versions[] = $fourth_version;
    self::assertCount(3, array_unique($versions));

    // Add a prop.
    $js_component->setProps([
      'title' => [
        'type' => 'string',
        'title' => 'Title',
      ],
    ])->save();

    $fifth_version_component = Component::load(JsComponent::SOURCE_PLUGIN_ID . '.joy_is_everything');
    assert($fifth_version_component instanceof Component);

    $fifth_version = $fifth_version_component->getActiveVersion();
    $versions[] = $fifth_version;
    self::assertCount(4, array_unique($versions));
  }

  protected function createAndSaveInUseComponentForUninstallValidationTesting(): ComponentInterface {
    $js_component_id = $this->randomMachineName();
    $js_component = JavaScriptComponent::create([
      'machineName' => $js_component_id,
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => FALSE,
      'props' => [
        'text' => [
          'type' => 'string',
          'title' => 'Text',
          'enum' => ['hello', 'goodbye'],
          'meta:enum' => ['hello' => 'Hello!', 'goodbye' => 'Good bye!'],
        ],
      ],
      'required' => [],
      'slots' => [],
      'js' => [
        'original' => 'console.log("hey");',
        'compiled' => 'console.log("hey");',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test { display: none; }',
      ],
      'dataDependencies' => [],
    ]);
    $js_component->enable()->save();
    $component_id = JsComponent::componentIdFromJavascriptComponentId($js_component_id);
    /** @var \Drupal\canvas\Entity\ComponentInterface */
    return Component::load($component_id);
  }

  protected function createAndSaveUnusedComponentForUninstallValidationTesting(): ComponentInterface {
    return $this->createAndSaveUnusedComponentForFallbackTesting();
  }

  protected function getNotAllowedModuleForUninstallValidatorTesting(): string {
    // Provides the field type for the enum.
    return 'options';
  }

  protected function getAllowedModuleForUninstallValidatorTesting(): string {
    $this->markTestSkipped('Uninstall is not valid for JS Components as they only depend on config, not optional modules.');
  }

}
