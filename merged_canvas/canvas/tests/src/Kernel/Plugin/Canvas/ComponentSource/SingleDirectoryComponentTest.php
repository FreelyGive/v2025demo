<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\canvas\Entity\Component;
use Drupal\Core\Plugin\Component as SdcPlugin;
use Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropSource\PropSource;
use Drupal\canvas\PropSource\StaticPropSource;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\SingleDirectoryComponentTreeTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\canvas\Traits\CrawlerTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Twig\Error\Error;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @coversDefaultClass \Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent
 * @group canvas
 * @phpstan-import-type ComponentConfigEntityId from \Drupal\canvas\Entity\Component
 */
final class SingleDirectoryComponentTest extends ComponentSourceTestBase {

  use ConstraintViolationsTestTrait;
  use ContribStrictConfigSchemaTestTrait;
  use SingleDirectoryComponentTreeTestTrait;
  use GenerateComponentConfigTrait;
  use CiModulePathTrait;
  use CrawlerTrait;
  use MediaTypeCreationTrait;
  use TestFileCreationTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas_test_sdc',
    'image',
    'media',
    'node',
    'path',
    'user',
    'field',
    'text',
  ];

  /**
   * Setup tests.
   */
  public function setUp(): void {
    parent::setUp();
    // Fixate the private key & hash salt to get predictable `itok`.
    $this->container->get('state')->set('system.private_key', 'dynamic_image_style_private_key');
    $settings_class = new \ReflectionClass(Settings::class);
    $instance_property = $settings_class->getProperty('instance');
    $settings = new Settings([
      'hash_salt' => 'dynamic_image_style_hash_salt',
    ]);
    $instance_property->setValue(NULL, $settings);

    // We need to ensure the public://balloons.png image exists in the test
    // environment for the "Card with stream wrapper image" tests.
    $file_system = \Drupal::service('file_system');
    $extension_path_resolver = \Drupal::service('extension.path.resolver');
    $module_path = $extension_path_resolver->getPath('module', 'canvas_test_sdc');
    $source = $module_path . '/components/card/balloons.png';
    $destination = 'public://balloons.png';
    $directory = 'public://';
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $file_system->copy($source, $destination, FileExists::Replace);
  }

  /**
   * @depends testDiscovery
   */
  public function testGetClientSideInfo(array $component_ids): void {
    $this->installEntitySchema('node');
    $this->installConfig('node');
    $this->createContentType(['type' => 'article']);
    parent::testGetClientSideInfo($component_ids);
  }

  /**
   * All test module SDCs must either have a Component or a reason why not.
   *
   * @covers ::checkRequirements()
   * @covers \Drupal\canvas\Plugin\ComponentPluginManager::setCachedDefinitions()
   */
  public function testDiscovery(): array {
    // Nothing discovered initially.
    self::assertSame([], $this->findIneligibleComponents(SingleDirectoryComponent::SOURCE_PLUGIN_ID, 'canvas_test_sdc'));
    self::assertSame([], $this->findCreatedComponentConfigEntities(SingleDirectoryComponent::SOURCE_PLUGIN_ID, 'canvas_test_sdc'));

    // Trigger component generation, as if the test module was just installed.
    // (Kernel tests don't trigger all hooks that are triggered in reality.)
    $this->generateComponentConfig();

    self::assertSame([
      'sdc.canvas_test_sdc.component-mismatch-meta-enum' => [
        'The "meta:enum" keys for the "style" prop enum cannot contain a dot. Offending key: "contains.dots"',
        'The "meta:enum" keys for the "numbers" prop enum cannot contain a dot. Offending key: "3.14"',
        'The values for the "numbers" prop enum must be defined in "meta:enum". Missing keys: "3_14"',
      ],
      'sdc.canvas_test_sdc.empty-enum' => [
        'Prop "pets" has an empty enum value.',
      ],
      'sdc.canvas_test_sdc.html-invalid-format' => [
        'Invalid value "invalid" for "x-formatting-context". Valid values are "inline" and "block".',
      ],
      'sdc.canvas_test_sdc.image-required-with-invalid-example' => [
        'Prop "image" has invalid example value: [src] The property src is required',
      ],
      'sdc.canvas_test_sdc.image-required-without-example' => [
        'Prop "image" is required, but does not have example value',
      ],
      'sdc.canvas_test_sdc.image-srcset-candidate-template-uri' => [
        // This test SDC does indeed not have a corresponding StaticPropSource,
        // because its purpose is to test the ability for components to opt in
        // to consuming additional computed properties on a field instance.
        // See the `the image-srcset-candidate-template-uri component` test case
        // in FieldForComponentSuggesterTest.
        // @see \Drupal\Tests\canvas\Kernel\FieldForComponentSuggesterTest
        'Drupal Canvas does not know of a field type/widget to allow populating the <code>srcSetCandidateTemplate</code> prop, with the shape <code>{"type":"string","format":"uri-template","x-required-variables":["width"]}</code>.',
      ],
      'sdc.canvas_test_sdc.obsolete' => [
        'Component has "obsolete" status',
      ],
      'sdc.canvas_test_sdc.props-invalid-shapes' => [
        'Prop "invalid_shape" is of type "object" without a $ref, which is not supported',
      ],
      'sdc.canvas_test_sdc.props-no-examples' => [
        'Prop "heading" is required, but does not have example value',
      ],
      'sdc.canvas_test_sdc.props-no-title' => [
        'Prop "heading" must have title',
      ],
      'sdc.canvas_test_sdc.shoe_button' => [
        'Drupal Canvas does not know of a field type/widget to allow populating the <code>icon</code> prop, with the shape <code>{"type":"object","$ref":"json-schema-definitions://canvas.module/shoe-icon"}</code>.',
      ],
      'sdc.canvas_test_sdc.shoe_details' => [
        'Drupal Canvas does not know of a field type/widget to allow populating the <code>expand_icon</code> prop, with the shape <code>{"type":"object","$ref":"json-schema-definitions://canvas.module/shoe-icon"}</code>.',
      ],
      'sdc.canvas_test_sdc.shoe_icon' => [
        'Prop "size" has an empty enum value.',
        'Prop "color" has an empty enum value.',
      ],
      'sdc.canvas_test_sdc.slots-no-title' => [
        'Slot "the_footer" must have title',
      ],
      'sdc.canvas_test_sdc.sparkline_min_2' => [
        // Drupal core's Field API only supports specifying "required or not",
        // and required means ">=1 value". There's no (native) ability to
        // configure a minimum number of values for a field.
        // @see https://www.drupal.org/project/unlimited_field_settings
        'Drupal Canvas does not know of a field type/widget to allow populating the <code>data</code> prop, with the shape <code>{"type":"array","items":{"type":"integer","minimum":-100,"maximum":100},"maxItems":100,"minItems":2}</code>.',
      ],
    ], $this->findIneligibleComponents(SingleDirectoryComponent::SOURCE_PLUGIN_ID, 'canvas_test_sdc'));
    $auto_created_components = $this->findCreatedComponentConfigEntities(SingleDirectoryComponent::SOURCE_PLUGIN_ID, 'canvas_test_sdc');
    self::assertSame([
      'sdc.canvas_test_sdc.attributes',
      'sdc.canvas_test_sdc.card',
      'sdc.canvas_test_sdc.card-with-local-image',
      'sdc.canvas_test_sdc.card-with-remote-image',
      'sdc.canvas_test_sdc.card-with-stream-wrapper-image',
      'sdc.canvas_test_sdc.component-no-meta-enum',
      'sdc.canvas_test_sdc.crash',
      'sdc.canvas_test_sdc.deprecated',
      'sdc.canvas_test_sdc.druplicon',
      'sdc.canvas_test_sdc.experimental',
      'sdc.canvas_test_sdc.grid-container',
      'sdc.canvas_test_sdc.heading',
      'sdc.canvas_test_sdc.image',
      'sdc.canvas_test_sdc.image-gallery',
      'sdc.canvas_test_sdc.image-optional-with-example',
      'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop',
      'sdc.canvas_test_sdc.image-optional-without-example',
      'sdc.canvas_test_sdc.image-required-with-example',
      'sdc.canvas_test_sdc.my-cta',
      'sdc.canvas_test_sdc.my-hero',
      'sdc.canvas_test_sdc.my-section',
      'sdc.canvas_test_sdc.one_column',
      'sdc.canvas_test_sdc.props-no-slots',
      'sdc.canvas_test_sdc.props-slots',
      'sdc.canvas_test_sdc.shoe_badge',
      'sdc.canvas_test_sdc.shoe_tab',
      'sdc.canvas_test_sdc.shoe_tab_group',
      'sdc.canvas_test_sdc.shoe_tab_panel',
      'sdc.canvas_test_sdc.sparkline',
      'sdc.canvas_test_sdc.two_column',
      'sdc.canvas_test_sdc.video',
    ], $auto_created_components);

    return array_combine($auto_created_components, $auto_created_components);
  }

  /**
   * Tests the shape-matched `prop_field_definitions` for the eligible SDCs.
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

  /**
   * Tests that all SDCs use the SdcPlugin class.
   *
   * @param array<ComponentConfigEntityId> $component_ids
   *
   * @covers ::getReferencedPluginClass()
   * @depends testDiscovery
   */
  public function testGetReferencedPluginClass(array $component_ids): void {
    self::assertSame(
      // All SDCs use the same plugin class!
      array_fill_keys($component_ids, SdcPlugin::class),
      $this->getReferencedPluginClasses($component_ids)
    );
  }

  /**
   * Tests rendering an SDC component.
   *
   * @param array<ComponentConfigEntityId> $component_ids
   *
   * @covers ::renderComponent()
   * @depends testDiscovery
   */
  public function testRenderComponentLive(array $component_ids): void {
    $this->installEntitySchema('node');
    $this->installConfig('node');
    $this->createContentType(['type' => 'article']);
    $this->assertNotEmpty($component_ids);

    $rendered = $this->renderComponentsLive(
      $component_ids,
      get_default_input: [__CLASS__, 'getDefaultInputForGeneratedInputUx'],
    );

    $default_render_cache_contexts = [
      'languages:language_interface',
      'theme',
      'user.permissions',
    ];
    $default_cacheability = (new CacheableMetadata())
      ->setCacheContexts($default_render_cache_contexts);
    $this->assertEquals([
      'sdc.canvas_test_sdc.attributes' => [
        'html' => <<<HTML
<div data-component-id="canvas_test_sdc:attributes" class="sdc-standardized-attributes">
  <div class="additional-attributes-for-this-sdc">
    The not-attributes SDC prop!
  </div>
</div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--attributes',
            'core/components.canvas_test_sdc--attributes',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.card' => [
        'html' => <<<HTML
<article class="card">
  <header>
    <h2>Card</h2>
  </header>

  <img
   class="card--image"
   src="::CANVAS_MODULE_PATH::/tests/modules/canvas_test_sdc/components/card/balloons.png"
           alt="Hot air balloons"
           width="640"
           height="427"
      loading="eager"
    data-testid="card-component-image" data-component-id="canvas:image"
/>

  <div class="content">
    <p>In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.</p>
  </div>
  <footer>I have a footer!</footer>
</article>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--card',
            'core/components.canvas--image',
            'core/components.canvas_test_sdc--card',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.card-with-local-image' => [
        'html' => <<<HTML
<article class="card--with-local-image">
  <header>
    <h2>Card with local image</h2>
  </header>

  <img
   class="card--image"
   src="/core/misc/druplicon.png"
           alt="A classic druplicon"
           width="88"
           height="100"
      loading="lazy"
    data-testid="card-component-image" data-component-id="canvas:image"
/>

  <div class="content">
    <p>In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.</p>
  </div>
  <footer>I have a footer!</footer>
</article>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--card-with-local-image',
            'core/components.canvas--image',
            'core/components.canvas_test_sdc--card',
            'core/components.canvas_test_sdc--card-with-local-image',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.card-with-remote-image' => [
        'html' => <<<HTML
<article class="card--with-remote-image">
  <header>
    <h2>Card with remote image</h2>
  </header>

  <img
   class="card--image"
   src="https://mdn.github.io/shared-assets/images/examples/balloons.jpg"
           alt="Hot air balloons"
           width="640"
           height="427"
      loading="lazy"
    data-testid="card-component-image" data-component-id="canvas:image"
/>

  <div class="content">
    <p>In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.</p>
  </div>
  <footer>I have a footer!</footer>
</article>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--card-with-remote-image',
            'core/components.canvas--image',
            'core/components.canvas_test_sdc--card',
            'core/components.canvas_test_sdc--card-with-remote-image',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.card-with-stream-wrapper-image' => [
        'html' => <<<HTML
<article class="card--with-stream-wrapper-image">
  <header>
    <h2>Card with stream wrapper</h2>
  </header>

  <img
   class="card--image"
   src="::SITE_DIR_BASE_URL::/files/balloons.png"
        srcset="::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--16/public/balloons.png.webp?itok=uQqtnjV1 16w, ::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--32/public/balloons.png.webp?itok=uQqtnjV1 32w, ::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--48/public/balloons.png.webp?itok=uQqtnjV1 48w, ::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--64/public/balloons.png.webp?itok=uQqtnjV1 64w, ::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--96/public/balloons.png.webp?itok=uQqtnjV1 96w, ::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--128/public/balloons.png.webp?itok=uQqtnjV1 128w, ::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--256/public/balloons.png.webp?itok=uQqtnjV1 256w, ::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--384/public/balloons.png.webp?itok=uQqtnjV1 384w, ::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--640/public/balloons.png.webp?itok=uQqtnjV1 640w"
     sizes="auto 100vw"
           alt="Hot air balloons"
           width="640"
           height="427"
      loading="lazy"
    data-testid="card-component-image" data-component-id="canvas:image"
/>

  <div class="content">
    <p>In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.</p>
  </div>
  <footer>I have a footer!</footer>
</article>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--card-with-stream-wrapper-image',
            'core/components.canvas--image',
            'core/components.canvas_test_sdc--card-with-stream-wrapper-image',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.deprecated' => [
        'html' => <<<HTML
<div  data-component-id="canvas_test_sdc:deprecated">
  <h1>Deprecated SDC component</h1>
  <div>A text field</div>
</div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--deprecated',
            'core/components.canvas_test_sdc--deprecated',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.experimental' => [
        'html' => <<<HTML
<div  data-component-id="canvas_test_sdc:experimental">
  <h1>Experimental SDC component</h1>
  <div>A text field</div>
</div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--experimental',
            'core/components.canvas_test_sdc--experimental',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.grid-container' => [
        'html' => '
<div  data-component-id="canvas_test_sdc:grid-container" class="grid-container direction-horizontal">

  </div>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--grid-container',
            'core/components.canvas_test_sdc--grid-container',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.image-gallery' => [
        'html' => '<figure>
      <img src="::CANVAS_MODULE_PATH::/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg" alt="A good dog" />
      <img src="::CANVAS_MODULE_PATH::/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg" alt="Still a good dog" />
      <img src="::CANVAS_MODULE_PATH::/tests/modules/canvas_test_sdc/components/image-gallery/UPPERCASE-GRACIE.JPG" alt="THE BEST DOG!" />
    </figure>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--image-gallery',
            'core/components.canvas_test_sdc--image-gallery',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.image-optional-with-example' => [
        'html' => '<img src="https://example.com/cat.jpg" alt="Boring placeholder" />',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--image-optional-with-example',
            'core/components.canvas_test_sdc--image-optional-with-example',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop' => [
        'html' => '<img src="::CANVAS_MODULE_PATH::/tests/modules/canvas_test_sdc/components/image-optional-with-example-and-additional-prop/gracie.jpg" alt="A good dog" width="601" height="402"></img>',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--image-optional-with-example-and-additional-prop',
            'core/components.canvas_test_sdc--image-optional-with-example-and-additional-prop',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.image-optional-without-example' => [
        'html' => '',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--image-optional-without-example',
            'core/components.canvas_test_sdc--image-optional-without-example',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.image-required-with-example' => [
        'html' => '<img src="https://example.com/cat.jpg" alt="Boring placeholder" />',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--image-required-with-example',
            'core/components.canvas_test_sdc--image-required-with-example',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.props-no-slots' => [
        'html' => <<<HTML
<div  data-component-id="canvas_test_sdc:props-no-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;">There goes my hero</h1>
</div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--props-no-slots',
            'core/components.canvas_test_sdc--props-no-slots',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.props-slots' => [
        'html' => '<div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;">There goes my hero</h1>
  <div class="component--props-slots--body">
          </div>
  <div class="component--props-slots--footer">
          </div>
  <div class="component--props-slots--colophon">
          </div>
</div>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--props-slots',
            'core/components.canvas_test_sdc--props-slots',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.crash' => [
        'html' => '<h1>test</h1>

',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--crash',
            'core/components.canvas_test_sdc--crash',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.sparkline' => [
        'html' => '

<div class="sparkline-container" style="width: 100px; height: 20px;">
  <svg width="100" height="20" viewBox="0 0 100 20" preserveAspectRatio="none">

            <polygon points="0,20 0,7.5 12.5,5 25,2.5 37.5,0 50,17.5 62.5,20 75,6.25 87.5,5.75 100,5.25 100,20" fill="rgba(26, 115, 232, 0.2)" />

            <polyline points="0,7.5 12.5,5 25,2.5 37.5,0 50,17.5 62.5,20 75,6.25 87.5,5.75 100,5.25" fill="none" stroke="#1a73e8" stroke-width="1" />
      </svg>
</div>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--sparkline',
            'core/components.canvas_test_sdc--sparkline',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.my-cta' => [
        'cacheability' => $default_cacheability,
        'html' => '<a  data-component-id="canvas_test_sdc:my-cta" href="https://www.drupal.org">
  Press
</a>
',
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--my-cta',
            'core/components.canvas_test_sdc--my-cta',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.component-no-meta-enum' => [
        'cacheability' => $default_cacheability,
        'html' => '<span  data-component-id="canvas_test_sdc:component-no-meta-enum">
It\'s me, and I\'m small!
</span>
',
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--component-no-meta-enum',
            'core/components.canvas_test_sdc--component-no-meta-enum',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.druplicon' => [
        'html' => <<<HTML
 <!-- sourced from https://www.drupal.org/about/media-kit/logos -->
 <svg enable-background="new 0 0 755 826" viewBox="0 0 755 826" xmlns="http://www.w3.org/2000/svg"><title>Druplicon</title><path d="m539.2 167.8c-39.8-24.8-77.2-34.5-114.8-59.2-23.2-15.8-55.5-53.2-82.5-85.5-5.2 51.8-21 72.8-39 87.8-38.2 30-62.2 39-95.2 57-27.8 14.2-178.5 104.2-178.5 297.7s162.8 336 343.5 336 337.5-131.2 337.5-330-147-288.8-171-303.8z" fill="#00598e"/><path d="m478.2 633.5c12 0 24.8.8 33.8 6.8s14.2 19.5 17.2 27 0 12-6 15c-5.2 3-6 1.5-11.2-8.2s-9.8-19.5-36-19.5-34.5 9-47.2 19.5-17.2 14.2-21.8 8.2-3-12 5.2-19.5 21.8-19.5 34.5-24.8 19.5-4.5 31.5-4.5z" fill="#fff"/><path d="m353.8 719c15 12 37.5 21.8 85.5 21.8s81.7-13.6 96.7-24.8c6.8-5.2 9.8-.8 10.5 2.2s2.2 7.5-3 12.8c-3.8 3.8-38.2 27.8-78.8 31.5s-95.2 6-128.2-24c-5.2-5.2-3.8-12.8 0-15.8s6.8-5.2 11.2-5.2 3.8 0 6.1 1.5z" fill="#fff"/><path d="m170 662c57-.8 67.5-10.5 117.8-33 271.5-121.5 321.8-232.5 331.5-258s24-66.8 9-112.5c-2.9-8.8-5-15.9-6.5-21.6-36.1-40.3-71.9-62.4-82.7-69.1-39-24.8-77.3-34.5-114.8-59.2-23.2-15-55.5-53.2-82.5-85.5-5.2 51.8-20.2 73.5-39 87.8-38.2 30-62.2 39-95.2 57-27.8 14.9-178.6 104.1-178.6 297.6 0 61.8 16.6 118.4 45.1 166.8l7.4-.3c15.7 14.2 40.5 30.8 88.5 30z" fill="#0073ba"/><path d="m539 167.8c-39-24.8-77.2-34.5-114.8-59.2-23.2-15-55.5-53.2-82.5-85.5-5.2 51.8-20.2 73.5-39 87.8-38.2 30-62.2 39-95.2 57-27.7 14.9-178.5 104.1-178.5 297.6 0 61.8 16.6 118.4 45.1 166.8 60.7 103.2 175.4 169.2 298.4 169.2 180.8 0 337.5-131.2 337.5-330 0-109.1-44.3-185.5-88.3-234.6-36.1-40.4-71.9-62.4-82.7-69.1zm91.2 87.7c49.2 61.6 74.2 134.2 74.2 216 0 47.4-9 92.2-26.8 133.2-16.9 38.8-41.2 73.2-72.3 102.3-61.5 57.4-144.1 89-232.7 89-43.8 0-86.8-8.4-127.8-24.9-40.3-16.2-76.5-39.4-107.8-69-66.1-62.4-102.4-146.4-102.4-236.6 0-80.3 26.1-151.7 77.5-212.2 39.3-46.2 81.7-71.8 98-80.6 8-4.3 15.4-8.2 22.6-11.9 22.6-11.6 44-22.6 73.4-45.6 15.7-11.9 32.4-30.8 39.5-78.7 24.8 29.5 53.5 62.6 75.5 76.8 19.5 12.9 39.5 21.9 58.8 30.6 18.3 8.2 37.2 16.8 55.9 28.7 0 0 .7.4.7.4 54.9 34.1 84.1 70.6 93.7 82.5z" fill="#004975"/><path d="m345.5 38c10.5 30.8 9 46.5 9 53.2s-3.8 24.8-15.8 33.8c-5.2 3.8-6.8 6.8-6.8 7.5 0 3 6.8 5.2 6.8 12 0 8.2-3.8 24.8-43.5 64.5s-96.8 75-141 96.8-65.2 20.2-71.2 9.7 2.2-33.8 30-64.5 115.5-75 115.5-75l109.5-76.5 6-29.2" fill="#93c5e4"/><path d="m345.5 37.2c-6.8 49.5-21.8 64.5-42 80.2-33.8 25.5-66.8 41.2-74.2 45-19.5 9.8-90 48.8-126.8 105-11.2 17.2 0 24 2.2 25.5s27.8 4.5 82.5-28.5 78.8-52.4 109.6-84.6c16.5-17.2 18.8-27 18.8-31.5 0-5.2-3.8-7.5-9.8-9-3-.8-3.8-2.2 0-4.5s19.4-9.8 23.2-12.8 21.8-15 22.5-34.5-.7-33-6-50.3z" fill="#fff"/><path d="m176.8 582.5c.8-58.5 55.5-113.2 124.5-114 87.8-.8 148.5 87 192.8 86.2 37.5-.8 109.5-74.2 144.8-74.2 37.5 0 48 39 48 62.2s-7.5 65.2-25.5 91.5-29.2 36-50.2 34.5c-27-2.2-81-86.2-115.5-87.8-43.5-1.5-138 90.8-212.2 90.8-45 0-58.5-6.8-73.5-16.5-22.8-15.7-34-39.7-33.2-72.7z" fill="#fff"/><path d="m628.2 258.5c15 45.8.8 87-9 112.5s-60 136.5-331.5 258c-50.2 22.5-60.8 32.2-117.8 33-48 .8-72.8-15.8-88.5-30l-7.4.3c60.7 103.2 175.4 169.2 298.4 169.2 180.8 0 337.5-131.2 337.5-330 0-109.1-44.3-185.5-88.3-234.6 1.6 5.7 3.8 12.8 6.6 21.6z" fill="none"/></svg>\n
 HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--druplicon',
            'core/components.canvas_test_sdc--druplicon',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.heading' => [
        'html' => <<<HTML
<h1  data-component-id="canvas_test_sdc:heading" class="primary">A heading element</h1>\n
HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--heading',
            'core/components.canvas_test_sdc--heading',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.image' => [
        'html' => '
<img
  class="image"
  src="::CANVAS_MODULE_PATH::/tests/modules/canvas_test_sdc/components/image/600x400.png"
    alt="Boring placeholder"
  width="600"
  height="400"
  loading="lazy"
/>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--image',
            'core/components.canvas_test_sdc--image',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.my-hero' => [
        'html' => <<<HTML
<div  data-component-id="canvas_test_sdc:my-hero" class="my-hero__container">
  <h1 class="my-hero__heading">There goes my hero</h1>
  <p class="my-hero__subheading">Watch him as he goes!</p>
  <div class="my-hero__actions">
    <a href="https://example.com" class="my-hero__cta my-hero__cta--primary">View</a>
    <button class="my-hero__cta">Click</button>
  </div>
</div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--my-hero',
            'core/components.canvas_test_sdc--my-hero',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.my-section' => [
        'html' => <<<HTML
<h2 class="my-section__h2">Our Mission</h2>
<div class="my-section__wrapper">
  <div class="my-section__content-wrapper">
    <p class="my-section__paragraph">
      Our mission is to deliver the best products and services to our customers. We strive to exceed expectations and continuously improve our offerings.
    </p>
    <p class="my-section__paragraph">
      Join us on our journey to innovation and excellence. Your satisfaction is our priority.
    </p>
  </div>
  <div class="my-section__image-wrapper">
    <img alt="Placeholder Image" class="my-section__img" width="500" height="500" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAfQAAAH0CAYAAADL1t+KAAAAAXNSR0IArs4c6QAAIABJREFUeF7t3QnT9ETVBuBBXHAF3HBDFsFdBP3//8AN3ABRBAUUV1BxRb46T9VQeZuemczMCUfOd6WKKotn5nT66pZ7knSS277zne+8sbMRIECAAAEC72iB2wT6O3r87DwBAgQIELgREOgmAgECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4QIECAAIEGAgK9wSDqAgECBAgQEOjmAAECBAgQaCAg0BsMoi4QIECAAAGBbg4Q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">
  </div>
</div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--my-section',
            'core/components.canvas_test_sdc--my-section',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.one_column' => [
        'html' => <<<HTML
<div  data-component-id="canvas_test_sdc:one_column" class="width-full">
      The contents of the example block
  </div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--one_column',
            'core/components.canvas_test_sdc--one_column',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.two_column' => [
        'html' => <<<HTML
<div  data-component-id="canvas_test_sdc:two_column">
          <div class="column-one width-25">
        The contents of the one column.

      </div>
              <div class="column-two width-75">
        The contents of the two column.

      </div>
    </div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--two_column',
            'core/components.canvas_test_sdc--two_column',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.shoe_badge' => [
        'html' => '<sl-badge
   data-component-id="canvas_test_sdc:shoe_badge" data-component-variant="primary"
  variant="primary"
   pulse    pill >
      The contents of the example block
  </sl-badge>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--shoe_badge',
            'core/components.canvas_test_sdc--shoe_badge',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab' => [
        'html' => '<sl-tab  data-component-id="canvas_test_sdc:shoe_tab"
panel="tab_1">
  Tab 1
</sl-tab>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--shoe_tab',
            'core/components.canvas_test_sdc--shoe_tab',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab_group' => [
        'html' => '<sl-tab-group  data-component-id="canvas_test_sdc:shoe_tab_group"
placement="top"
activation="auto">
  <div slot="nav">
          </div>


</sl-tab-group>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--shoe_tab_group',
            'core/components.canvas_test_sdc--shoe_tab_group',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab_panel' => [
        'html' => '<sl-tab-panel  data-component-id="canvas_test_sdc:shoe_tab_panel"
  name="tab_name"  >  </sl-tab-panel>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--shoe_tab_panel',
            'core/components.canvas_test_sdc--shoe_tab_panel',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.video' => [
        'html' => '<video controls poster="https://example.com/600x400.png" width="">
  <source src="https://media.istockphoto.com/id/1340051874/video/aerial-top-down-view-of-a-container-cargo-ship.mp4?s=mp4-640x640-is&amp;k=20&amp;c=5qPpYI7TOJiOYzKq9V2myBvUno6Fq2XM3ITPGFE8Cd8=">
</video>
',
        'cacheability' => $default_cacheability,
        'attachments' => [
          'library' => [
            'core/components.canvas_test_sdc--video',
            'core/components.canvas_test_sdc--video',
          ],
        ],
      ],
    ], $rendered);
  }

  /**
   * Tests that relative file URLs are rewritten to reference the correct file path.
   */
  public function testRewriteExampleUrl(): void {
    $plugin = \Drupal::service(ComponentPluginManager::class)->createInstance('canvas_test_sdc:image');
    $component = SingleDirectoryComponent::createConfigEntity($plugin);
    $source = $component->getComponentSource();
    self::assertInstanceOf(SingleDirectoryComponent::class, $source);
    // Assert that existing files are rewritten to include the module path.
    $canvas_test_sdc_module_path = \Drupal::service(ModuleExtensionList::class)->getPath('canvas_test_sdc');

    self::assertStringEndsWith($canvas_test_sdc_module_path . '/components/image/600x400.png', $source->rewriteExampleUrl('600x400.png'));
    self::assertStringEndsWith($canvas_test_sdc_module_path . '/components/image/600x400.png', $source->rewriteExampleUrl('/600x400.png'));
    self::assertStringEndsWith('/tests/fixtures/600x400.png', $source->rewriteExampleUrl('../../tests/fixtures/600x400.png'));

    // Assert that non-existing links have a leading slash but do not include the module nor SDC path.
    $url = $source->rewriteExampleUrl('test/path');
    self::assertStringEndsWith('/test/path', $url);
    self::assertStringNotContainsString($canvas_test_sdc_module_path, $url);
    self::assertStringNotContainsString('components', $url);

    // Assert that non-existing links with a leading slash are not doubled.
    $url = $source->rewriteExampleUrl('/test/path');
    self::assertStringEndsWith('/test/path', $url);
    self::assertStringNotContainsString('//', $url);

    // Assert that full URLs are left alone.
    self::assertSame('https://www.example.com/', $source->rewriteExampleUrl('https://www.example.com/'));
  }

  /**
   * @covers ::getExplicitInput()
   * @dataProvider providerComponentResolving
   */
  public function testGetExplicitInput(array $component_item_value, array $expected_props_for_uuids): void {
    $this->generateComponentConfig();
    $this->installEntitySchema('node');
    $this->container->get('module_installer')->install(['canvas_test_config_node_article']);
    $node = Node::create([
      'title' => 'Test node',
      'type' => 'article',
      'field_canvas_test' => $component_item_value,
    ]);
    $canvas_field_item = $node->field_canvas_test[0];
    $this->assertInstanceOf(ComponentTreeItem::class, $canvas_field_item);
    $actual_props = array_combine(
      array_keys($expected_props_for_uuids),
      array_map(
        fn (string $uuid) => $canvas_field_item->getComponent()?->getComponentSource()->getExplicitInput($uuid, $canvas_field_item)['resolved'],
        array_keys($expected_props_for_uuids)
      )
    );
    $this->assertSame($expected_props_for_uuids, $actual_props);
  }

  public static function providerComponentResolving(): array {
    $test_cases = static::getValidTreeTestCases();
    $invalid_test_cases = static::getInvalidTreeTestCases();
    // Only 1 invalid case will allow to call
    // \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem::resolveComponentProps()
    // without an exception.
    $test_cases['invalid UUID, missing component_id key'] = $invalid_test_cases['invalid UUID, missing component_id key'];
    $test_cases['invalid UUID, missing component_id key'][] = [];
    $test_cases['valid values using static inputs'][] = [
      'dynamic-static-card2df' => [
        'heading' => 'They say I am static, but I want to believe I can change!',
      ],
    ];
    $test_cases['valid values for propless component'][] = [
      'propless-component-uuid' => [],
    ];
    $test_cases['valid value for optional explicit input using an URL prop shape, with default value'][] = [
      'optional-url-with-default-value' => [
        'heading' => 'Gracie says hi!',
        'image' => [
          'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-optional-with-example-and-additional-prop/gracie.jpg',
          'alt' => 'A good dog',
          'width' => 601,
          'height' => 402,
        ],
      ],
    ];
    return $test_cases;
  }

  protected function generateCrashTestDummyComponentTree(string $component_id, array $inputs, bool $assertCount = TRUE): ComponentTreeItemList {
    if ($component_id === 'sdc.canvas_broken_sdcs.invalid-filter') {
      // This component needs an extra module.
      $this->assertCount(0, $this->componentStorage->loadMultiple());
      \Drupal::service(ModuleInstallerInterface::class)->install(['canvas_broken_sdcs']);

      // Now call the parent, but don't assert the count of components, as we've
      // done it here, and installing a module will generate component config.
      return parent::generateCrashTestDummyComponentTree($component_id, $inputs, assertCount: FALSE);
    }
    return parent::generateCrashTestDummyComponentTree($component_id, $inputs);
  }

  public static function providerRenderComponentFailure(): \Generator {
    yield "SDC with valid props, without exception" => [
      'component_id' => 'sdc.canvas_test_sdc.crash',
      'inputs' => [
        'crash' => FALSE,
      ],
      'expected_validation_errors' => [],
      'expected_exception' => NULL,
      'expected_output_selector' => 'h1:contains("test")',
    ];

    yield "SDC with valid props, with exception" => [
      'component_id' => 'sdc.canvas_test_sdc.crash',
      'inputs' => [
        'crash' => TRUE,
      ],
      'expected_validation_errors' => [],
      'expected_exception' => [
        'class' => Error::class,
        'message' => 'Intentional test exception in "canvas_test_sdc:crash" at line 2.',
      ],
      'expected_output_selector' => NULL,
    ];

    yield "SDC with invalid prop, with exception" => [
      'component_id' => 'sdc.canvas_test_sdc.crash',
      'inputs' => [
        'crash' => [
          'sourceType' => "static:field_item:string",
          'value' => 'this is an invalid value for the SDC prop',
          'expression' => (string) new FieldTypePropExpression('string', 'value'),
        ],
      ],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.crash', self::UUID_CRASH_TEST_DUMMY) => 'String value found, but a boolean or an object is required. The provided value is: "this is an invalid value for the SDC prop".',
      ],
      'expected_exception' => [
        'class' => RuntimeError::class,
        'message' => 'An exception has been thrown during the rendering of a template ("[canvas_test_sdc:crash/crash] String value found, but a boolean or an object is required. The provided value is: "this is an invalid value for the SDC prop".") in "canvas_test_sdc:crash" at line 1.',
      ],
      'expected_output_selector' => NULL,
    ];

    yield "SDC with missing prop, with exception" => [
      'component_id' => 'sdc.canvas_test_sdc.crash',
      'inputs' => [],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.crash', self::UUID_CRASH_TEST_DUMMY) => 'The property crash is required.',
      ],
      'expected_exception' => [
        'class' => RuntimeError::class,
        'message' => 'An exception has been thrown during the rendering of a template ("[canvas_test_sdc:crash/crash] The property crash is required.") in "canvas_test_sdc:crash" at line 1.',
      ],
      'expected_output_selector' => NULL,
    ];

    yield "SDC with invalid Twig" => [
      'component_id' => 'sdc.canvas_broken_sdcs.invalid-filter',
      'inputs' => [
        'name' => 'Gaia',
      ],
      'expected_validation_errors' => [],
      'expected_exception' => [
        'class' => SyntaxError::class,
        'message' => 'Unknown "invalidFilter" filter',
      ],
      'expected_output_selector' => NULL,
    ];
  }

  public static function getExpectedSettings(): array {
    return [
      'sdc.canvas_test_sdc.attributes' => [
        'prop_field_definitions' => [
          'not_attributes' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'The not-attributes SDC prop!']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.card' => [
        'prop_field_definitions' => [
          'content' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'footer' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'I have a footer!',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'heading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Card',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'image' => [
            'field_type' => 'image',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'image_image',
            'default_value' => [],
            'expression' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
          'loading' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              [
                'value' => 'eager',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
          'sizes' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'auto 50vw',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.card-with-local-image' => [
        'prop_field_definitions' => [
          'alt' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'A classic druplicon',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'content' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'footer' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'I have a footer!',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'heading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Card with local image',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'loading' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              [
                'value' => 'lazy',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
          'src' => [
            'field_type' => 'image',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'image_image',
            'default_value' => [],
            'expression' => 'ℹ︎image␟src_with_alternate_widths',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.card-with-remote-image' => [
        'prop_field_definitions' => [
          'alt' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Hot air balloons',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'content' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'footer' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'I have a footer!',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'heading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Card with remote image',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'height' => [
            'field_type' => 'integer',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'number',
            'default_value' => [
              0 => [
                'value' => 427,
              ],
            ],
            'expression' => 'ℹ︎integer␟value',
          ],
          'loading' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              [
                'value' => 'lazy',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
          'src' => [
            'field_type' => 'image',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'image_image',
            'default_value' => [],
            'expression' => 'ℹ︎image␟src_with_alternate_widths',
          ],
          'width' => [
            'field_type' => 'integer',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'number',
            'default_value' => [
              0 => [
                'value' => 640,
              ],
            ],
            'expression' => 'ℹ︎integer␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.card-with-stream-wrapper-image' => [
        'prop_field_definitions' => [
          'alt' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Hot air balloons',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'content' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'footer' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'I have a footer!',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'heading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Card with stream wrapper',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'loading' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              [
                'value' => 'lazy',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
          'src' => [
            'field_type' => 'image',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'image_image',
            'default_value' => [],
            'expression' => 'ℹ︎image␟entity␜␜entity:file␝uri␞␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.component-no-meta-enum' => [
        'prop_field_definitions' => [
          'style' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [0 => ['value' => 'small']],
            'expression' => 'ℹ︎list_string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.crash' => [
        'prop_field_definitions' => [
          'crash' => [
            'field_type' => 'boolean',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'boolean_checkbox',
            'default_value' => [0 => ['value' => FALSE]],
            'expression' => 'ℹ︎boolean␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.deprecated' => [
        'prop_field_definitions' => [
          'text' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'A text field']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.druplicon' => [
        'prop_field_definitions' => [],
      ],
      'sdc.canvas_test_sdc.experimental' => [
        'prop_field_definitions' => [
          'text' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'A text field']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.grid-container' => [
        'prop_field_definitions' => [
          'direction' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [0 => ['value' => 'horizontal']],
            'expression' => 'ℹ︎list_string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.heading' => [
        'prop_field_definitions' => [
          'element' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              0 => [
                'value' => 'h1',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
          'style' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              0 => [
                'value' => 'primary',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
          'text' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'A heading element',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.image' => [
        'prop_field_definitions' => [
          'image' => [
            'field_type' => 'image',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'image_image',
            'default_value' => [],
            'expression' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.image-gallery' => [
        'prop_field_definitions' => [
          'caption' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => NULL,
            'expression' => 'ℹ︎string␟value',
          ],
          'images' => [
            'field_type' => 'image',
            'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
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
      'sdc.canvas_test_sdc.image-optional-with-example' => [
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
      'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop' => [
        'prop_field_definitions' => [
          'heading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => NULL,
            'expression' => 'ℹ︎string␟value',
          ],
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
      'sdc.canvas_test_sdc.image-optional-without-example' => [
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
      'sdc.canvas_test_sdc.image-required-with-example' => [
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
      'sdc.canvas_test_sdc.my-cta' => [
        'prop_field_definitions' => [
          'href' => [
            'field_type' => 'link',
            'field_storage_settings' => [],
            'field_instance_settings' => [
              'title' => 0,
            ],
            'field_widget' => 'link_default',
            'default_value' => [
              0 => [
                'uri' => 'https://www.drupal.org',
                'options' => [],
              ],
            ],
            'expression' => 'ℹ︎link␟url',
          ],
          'target' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => NULL,
            'expression' => 'ℹ︎list_string␟value',
          ],
          'text' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Press',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.my-hero' => [
        'prop_field_definitions' => [
          'cta1' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'View',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'cta1href' => [
            'field_type' => 'link',
            'field_storage_settings' => [],
            'field_instance_settings' => [
              'title' => 0,
            ],
            'field_widget' => 'link_default',
            'default_value' => [
              0 => [
                'uri' => 'https://example.com',
                'options' => [],
              ],
            ],
            'expression' => 'ℹ︎link␟url',
          ],
          'cta2' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Click',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'heading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'There goes my hero',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'subheading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Watch him as he goes!',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.my-section' => [
        'prop_field_definitions' => [
          'text' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Our mission is to deliver the best products and services to our customers. We strive to exceed expectations and continuously improve our offerings.',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.one_column' => [
        'prop_field_definitions' => [
          'width' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              0 => [
                'value' => 'full',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.props-no-slots' => [
        'prop_field_definitions' => [
          'heading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'There goes my hero']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.props-slots' => [
        'prop_field_definitions' => [
          'heading' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [0 => ['value' => 'There goes my hero']],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.shoe_badge' => [
        'prop_field_definitions' => [
          'pill' => [
            'field_type' => 'boolean',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'boolean_checkbox',
            'default_value' => [
              0 => [
                'value' => TRUE,
              ],
            ],
            'expression' => 'ℹ︎boolean␟value',
          ],
          'pulse' => [
            'field_type' => 'boolean',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'boolean_checkbox',
            'default_value' => [
              0 => [
                'value' => TRUE,
              ],
            ],
            'expression' => 'ℹ︎boolean␟value',
          ],
          'variant' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              0 => [
                'value' => 'primary',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab' => [
        'prop_field_definitions' => [
          'active' => [
            'field_type' => 'boolean',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'boolean_checkbox',
            'default_value' => NULL,
            'expression' => 'ℹ︎boolean␟value',
          ],
          'closable' => [
            'field_type' => 'boolean',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'boolean_checkbox',
            'default_value' => NULL,
            'expression' => 'ℹ︎boolean␟value',
          ],
          'disabled' => [
            'field_type' => 'boolean',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'boolean_checkbox',
            'default_value' => NULL,
            'expression' => 'ℹ︎boolean␟value',
          ],
          'label' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'Tab 1',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
          'panel' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'tab_1',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab_group' => [
        'prop_field_definitions' => [
          'activation' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              0 => [
                'value' => 'auto',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
          'no_scroll' => [
            'field_type' => 'boolean',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'boolean_checkbox',
            'default_value' => [
              0 => [
                'value' => FALSE,
              ],
            ],
            'expression' => 'ℹ︎boolean␟value',
          ],
          'placement' => [
            'field_type' => 'list_string',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              0 => [
                'value' => 'top',
              ],
            ],
            'expression' => 'ℹ︎list_string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab_panel' => [
        'prop_field_definitions' => [
          'active' => [
            'field_type' => 'boolean',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'boolean_checkbox',
            'default_value' => NULL,
            'expression' => 'ℹ︎boolean␟value',
          ],
          'name' => [
            'field_type' => 'string',
            'field_storage_settings' => [],
            'field_instance_settings' => [],
            'field_widget' => 'string_textfield',
            'default_value' => [
              0 => [
                'value' => 'tab_name',
              ],
            ],
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.sparkline' => [
        'prop_field_definitions' => [
          'data' => [
            'field_type' => 'integer',
            'cardinality' => 100,
            'field_storage_settings' => [],
            'field_instance_settings' => [
              'min' => -100,
              'max' => 100,
            ],
            'field_widget' => 'number',
            'default_value' => [
              0 => ['value' => 0],
              1 => ['value' => 10],
              2 => ['value' => 20],
              3 => ['value' => 30],
              4 => ['value' => -40],
              5 => ['value' => -50],
              6 => ['value' => 5],
              7 => ['value' => 7],
              8 => ['value' => 9],
            ],
            'expression' => 'ℹ︎integer␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.two_column' => [
        'prop_field_definitions' => [
          'width' => [
            'field_type' => 'list_integer',
            'field_storage_settings' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
            'field_instance_settings' => [],
            'field_widget' => 'options_select',
            'default_value' => [
              0 => [
                'value' => 25,
              ],
            ],
            'expression' => 'ℹ︎list_integer␟value',
          ],
        ],
      ],
      'sdc.canvas_test_sdc.video' => [
        'prop_field_definitions' => [
          'display_width' => [
            'field_type' => 'integer',
            'field_storage_settings' => [],
            'field_instance_settings' => [
              'min' => 1,
              'max' => NULL,
            ],
            'field_widget' => 'number',
            'default_value' => NULL,
            'expression' => 'ℹ︎integer␟value',
          ],
          'video' => [
            'field_type' => 'file',
            'field_storage_settings' => [],
            'field_instance_settings' => [
              'file_extensions' => 'mp4',
            ],
            'field_widget' => 'file_generic',
            'default_value' => [],
            'expression' => 'ℹ︎file␟{src↝entity␜␜entity:file␝uri␞␟url}',
          ],
        ],
      ],
    ];
  }

  /**
   * @covers ::calculateDependencies()
   * @depends testDiscovery
   */
  public function testCalculateDependencies(array $component_ids): void {
    self::assertSame([
      'sdc.canvas_test_sdc.attributes' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.card' => [
        'config' => [
          'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'core',
          'file',
          'image',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.card-with-local-image' => [
        'config' => [
          0 => 'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'core',
          'file',
          'image',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.card-with-remote-image' => [
        'config' => [
          0 => 'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'core',
          'file',
          'image',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.card-with-stream-wrapper-image' => [
        'config' => [
          0 => 'image.style.canvas_parametrized_width',
        ],
        'content' => [],
        'module' => [
          'core',
          'file',
          'image',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.component-no-meta-enum' => [
        'module' => [
          'core',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.crash' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.deprecated' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.druplicon' => [
        'module' => [
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.experimental' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.grid-container' => [
        'module' => [
          'core',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.heading' => [
        'module' => [
          'core',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.image' => [
        'config' => [
          0 => 'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'file',
          'image',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.image-gallery' => [
        'config' => [
          'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'core',
          'file',
          'image',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.image-optional-with-example' => [
        'config' => [
          'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'file',
          'image',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop' => [
        'config' => [
          'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'core',
          'file',
          'image',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.image-optional-without-example' => [
        'config' => [
          'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'file',
          'image',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.image-required-with-example' => [
        'config' => [
          'image.style.canvas_parametrized_width',
        ],
        'module' => [
          'file',
          'image',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.my-cta' => [
        'module' => [
          'core',
          'link',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.my-hero' => [
        'module' => [
          'core',
          'link',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.my-section' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.one_column' => [
        'module' => [
          'core',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.props-no-slots' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.props-slots' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.shoe_badge' => [
        'module' => [
          'core',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab_group' => [
        'module' => [
          'core',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.shoe_tab_panel' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.sparkline' => [
        'module' => [
          'core',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.two_column' => [
        'module' => [
          'core',
          'options',
          'canvas_test_sdc',
        ],
      ],
      'sdc.canvas_test_sdc.video' => [
        'content' => [],
        'module' => [
          0 => 'core',
          1 => 'file',
          2 => 'canvas_test_sdc',
        ],
      ],
    ], $this->callSourceMethodForEach('calculateDependencies', $component_ids));
  }

  /**
   * {@inheritdoc}
   */
  public static function getExpectedClientSideInfo(): array {
    return [
      'sdc.canvas_test_sdc.attributes' => [
        'expected_output_selectors' => [
          'div>div:contains("The not-attributes SDC prop!")',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'not_attributes' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 'The not-attributes SDC prop!'],
              ],
              'resolved' => 'The not-attributes SDC prop!',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.card' => [
        'expected_output_selectors' => [
          'article.card',
          'article.card img.card--image',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'heading' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Card',
                ],
              ],
              'resolved' => 'Card',
            ],
          ],
          'content' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
                ],
              ],
              'resolved' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
            ],
          ],
          'footer' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'I have a footer!',
                ],
              ],
              'resolved' => 'I have a footer!',
            ],
          ],
          'image' => [
            'required' => TRUE,
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
                'src' => '/' . \Drupal::service(ExtensionPathResolver::class)->getPath('module', 'canvas_test_sdc') . '/components/card/balloons.png',
                'alt' => 'Hot air balloons',
                'width' => 640,
                'height' => 427,
              ],
            ],
          ],
          'sizes' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'auto 50vw',
                ],
              ],
              'resolved' => 'auto 50vw',
            ],
          ],
          'loading' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'lazy',
                1 => 'eager',
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
                0 => [
                  'value' => 'eager',
                ],
              ],
              'resolved' => 'eager',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.card-with-local-image' => [
        'expected_output_selectors' => [
          'article.card--with-local-image',
          'article.card--with-local-image img.card--image',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'heading' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Card with local image',
                ],
              ],
              'resolved' => 'Card with local image',
            ],
          ],
          'content' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
                ],
              ],
              'resolved' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
            ],
          ],
          'footer' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'I have a footer!',
                ],
              ],
              'resolved' => 'I have a footer!',
            ],
          ],
          'src' => [
            'required' => TRUE,
            'jsonSchema' => [
              'title' => 'Image URL',
              'type' => 'string',
              'format' => 'uri-reference',
              'contentMediaType' => 'image/*',
              'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$',
            ],
            'sourceType' => 'static:field_item:image',
            'expression' => 'ℹ︎image␟src_with_alternate_widths',
            'default_values' => [
              'source' => [],
              'resolved' => '/core/misc/druplicon.png',
            ],
          ],
          'alt' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                [
                  'value' => 'A classic druplicon',
                ],
              ],
              'resolved' => 'A classic druplicon',
            ],
          ],
          'loading' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'lazy',
                1 => 'eager',
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
                0 => [
                  'value' => 'lazy',
                ],
              ],
              'resolved' => 'lazy',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.card-with-remote-image' => [
        'expected_output_selectors' => [
          'article.card--with-remote-image',
          'article.card--with-remote-image img.card--image',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'heading' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Card with remote image',
                ],
              ],
              'resolved' => 'Card with remote image',
            ],
          ],
          'content' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
                ],
              ],
              'resolved' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
            ],
          ],
          'footer' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'I have a footer!',
                ],
              ],
              'resolved' => 'I have a footer!',
            ],
          ],
          'src' => [
            'required' => TRUE,
            'jsonSchema' => [
              'title' => 'Image URL',
              'type' => 'string',
              'format' => 'uri-reference',
              'contentMediaType' => 'image/*',
              'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$',
            ],
            'sourceType' => 'static:field_item:image',
            'expression' => 'ℹ︎image␟src_with_alternate_widths',
            'default_values' => [
              'source' => [],
              'resolved' => 'https://mdn.github.io/shared-assets/images/examples/balloons.jpg',
            ],
          ],
          'alt' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                [
                  'value' => 'Hot air balloons',
                ],
              ],
              'resolved' => 'Hot air balloons',
            ],
          ],
          'width' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'integer',
            ],
            'sourceType' => 'static:field_item:integer',
            'expression' => 'ℹ︎integer␟value',
            'default_values' => [
              'source' => [
                [
                  'value' => 640,
                ],
              ],
              'resolved' => 640,
            ],
          ],
          'height' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'integer',
            ],
            'sourceType' => 'static:field_item:integer',
            'expression' => 'ℹ︎integer␟value',
            'default_values' => [
              'source' => [
                [
                  'value' => 427,
                ],
              ],
              'resolved' => 427,
            ],
          ],
          'loading' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'lazy',
                1 => 'eager',
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
                0 => [
                  'value' => 'lazy',
                ],
              ],
              'resolved' => 'lazy',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.card-with-stream-wrapper-image' => [
        'expected_output_selectors' => [
          'article.card--with-stream-wrapper-image',
          'article.card--with-stream-wrapper-image img.card--image',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'heading' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Card with stream wrapper',
                ],
              ],
              'resolved' => 'Card with stream wrapper',
            ],
          ],
          'content' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
                ],
              ],
              'resolved' => 'In a curious work, published in Paris in 1863 by Delaville Dedreux, there is a suggestion for reaching the North Pole by an aerostat.',
            ],
          ],
          'footer' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'I have a footer!',
                ],
              ],
              'resolved' => 'I have a footer!',
            ],
          ],
          'src' => [
            'required' => TRUE,
            'jsonSchema' => [
              'title' => 'Stream wrapper image URI',
              'type' => 'string',
              'format' => 'uri',
              'contentMediaType' => 'image/*',
              'pattern' => '^(?!https?://)[\w\-]+://',
            ],
            'sourceType' => 'static:field_item:image',
            'expression' => 'ℹ︎image␟entity␜␜entity:file␝uri␞␟value',
            'default_values' => [
              'source' => [],
              'resolved' => 'public://balloons.png',
            ],
          ],
          'alt' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                [
                  'value' => 'Hot air balloons',
                ],
              ],
              'resolved' => 'Hot air balloons',
            ],
          ],
          'loading' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'lazy',
                1 => 'eager',
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
                0 => [
                  'value' => 'lazy',
                ],
              ],
              'resolved' => 'lazy',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.component-no-meta-enum' => [
        'expected_output_selectors' => [
          'span:contains("me")',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'style' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                'small',
                'big',
                'huge',
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
                0 => ['value' => 'small'],
              ],
              'resolved' => 'small',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.crash' => [
        'expected_output_selectors' => [
          'h1:contains("test")',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'crash' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'boolean',
            ],
            'sourceType' => 'static:field_item:boolean',
            'expression' => 'ℹ︎boolean␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => FALSE],
              ],
              'resolved' => FALSE,
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.deprecated' => [
        'expected_output_selectors' => [
          'h1:contains("Deprecated SDC component")',
          'div[data-component-id="canvas_test_sdc:deprecated"]',
        ],
        'source' => 'Module component',
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
                0 => ['value' => 'A text field'],
              ],
              'resolved' => 'A text field',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.druplicon' => [
        'expected_output_selectors' => [
          'svg',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.experimental' => [
        'expected_output_selectors' => [
          'h1:contains("Experimental SDC component")',
          'div[data-component-id="canvas_test_sdc:experimental"]',
        ],
        'source' => 'Module component',
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
                0 => ['value' => 'A text field'],
              ],
              'resolved' => 'A text field',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.grid-container' => [
        'expected_output_selectors' => [
          'div[data-component-id="canvas_test_sdc:grid-container"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [
            'content' => [
              'title' => 'Content',
              'description' => 'The contents of the grid container.',
              'examples' => [
                '<div class="empty-slot">Empty grid slot</div>',
              ],
            ],
          ],
        ],
        'propSources' => [
          'direction' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'horizontal',
                1 => 'vertical',
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
                0 => ['value' => 'horizontal'],
              ],
              'resolved' => 'horizontal',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.heading' => [
        'expected_output_selectors' => [
          'h1:contains("A heading element")',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [],
        ],
        'propSources' => [
          'text' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'A heading element',
                ],
              ],
              'resolved' => 'A heading element',
            ],
          ],
          'style' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'primary',
                1 => 'secondary',
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
                0 => [
                  'value' => 'primary',
                ],
              ],
              'resolved' => 'primary',
            ],
          ],
          'element' => [
            'required' => TRUE,
            'jsonSchema' => [
              'title' => 'Heading element',
              'type' => 'string',
              'enum' => [
                0 => 'div',
                1 => 'h1',
                2 => 'h2',
                3 => 'h3',
                4 => 'h4',
                5 => 'h5',
                6 => 'h6',
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
                0 => [
                  'value' => 'h1',
                ],
              ],
              'resolved' => 'h1',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.image' => [
        'expected_output_selectors' => [
          'img',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [],
        ],
        'propSources' => [
          'image' => [
            'required' => TRUE,
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
                'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image/600x400.png',
                'alt' => 'Boring placeholder',
                'width' => 600,
                'height' => 400,
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.image-gallery' => [
        'expected_output_selectors' => [
          'figure > img[src="' . self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg"][alt="A good dog"]',
          'figure > img[src="' . self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg"][alt="Still a good dog"]',
          'figure > img[src="' . self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/UPPERCASE-GRACIE.JPG"][alt="THE BEST DOG!"]',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'caption' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
          ],
          'images' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'array',
              'items' => [
                'title' => 'image',
                'type' => 'object',
                'required' => [
                  'src',
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
            ],
            'sourceType' => 'static:field_item:image',
            'expression' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
            'sourceTypeSettings' => [
              'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
            ],
            'default_values' => [
              'source' => [],
              'resolved' => [
                0 => [
                  'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg',
                  'alt' => 'A good dog',
                  'width' => 601,
                  'height' => 402,
                ],
                1 => [
                  'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg',
                  'alt' => 'Still a good dog',
                  'width' => 601,
                  'height' => 402,
                ],
                2 => [
                  'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/UPPERCASE-GRACIE.JPG',
                  'alt' => 'THE BEST DOG!',
                  'width' => 601,
                  'height' => 402,
                ],
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.image-optional-with-example' => [
        'expected_output_selectors' => [
          'img[src="https://example.com/cat.jpg"]',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'image' => [
            'required' => FALSE,
            'jsonSchema' => [
              'title' => 'image',
              'type' => 'object',
              'required' => [
                'src',
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
                'src' => 'https://example.com/cat.jpg',
                'alt' => 'Boring placeholder',
                'width' => 600,
                'height' => 400,
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop' => [
        'expected_output_selectors' => [
          'img[src="' . self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-optional-with-example-and-additional-prop/gracie.jpg"]',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'heading' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
          ],
          'image' => [
            'required' => FALSE,
            'jsonSchema' => [
              'title' => 'image',
              'type' => 'object',
              'required' => [
                'src',
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
                'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-optional-with-example-and-additional-prop/gracie.jpg',
                'alt' => 'A good dog',
                'width' => 601,
                'height' => 402,
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.image-optional-without-example' => [
        'expected_output_selectors' => [],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'image' => [
            'required' => FALSE,
            'jsonSchema' => [
              'title' => 'image',
              'type' => 'object',
              'required' => [
                'src',
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
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.image-required-with-example' => [
        'expected_output_selectors' => [
          'img[src="https://example.com/cat.jpg"]',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'image' => [
            'required' => TRUE,
            'jsonSchema' => [
              'title' => 'image',
              'type' => 'object',
              'required' => [
                'src',
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
                'src' => 'https://example.com/cat.jpg',
                'alt' => 'Boring placeholder',
                'width' => 600,
                'height' => 400,
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.my-cta' => [
        'expected_output_selectors' => [
          'a:contains("Press")',
          'a[data-component-id="canvas_test_sdc:my-cta"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [],
        ],
        'propSources' => [
          'text' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Press',
                ],
              ],
              'resolved' => 'Press',
            ],
          ],
          'href' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'format' => 'uri',
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
                  'uri' => 'https://www.drupal.org',
                  'options' => [],
                ],
              ],
              'resolved' => 'https://www.drupal.org',
            ],
          ],
          'target' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => '_self',
                1 => '_blank',
              ],
            ],
            'sourceType' => 'static:field_item:list_string',
            'expression' => 'ℹ︎list_string␟value',
            'sourceTypeSettings' => [
              'storage' => [
                'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.my-hero' => [
        'expected_output_selectors' => [
          'h1:contains("There goes my hero")',
          'p:contains("Watch him as he goes!")',
          'div[data-component-id="canvas_test_sdc:my-hero"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [],
        ],
        'propSources' => [
          'heading' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'There goes my hero',
                ],
              ],
              'resolved' => 'There goes my hero',
            ],
          ],
          'subheading' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Watch him as he goes!',
                ],
              ],
              'resolved' => 'Watch him as he goes!',
            ],
          ],
          'cta1' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'View',
                ],
              ],
              'resolved' => 'View',
            ],
          ],
          'cta1href' => [
            'required' => TRUE,
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
                  'uri' => 'https://example.com',
                  'options' => [],
                ],
              ],
              'resolved' => 'https://example.com',
            ],
          ],
          'cta2' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Click',
                ],
              ],
              'resolved' => 'Click',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.my-section' => [
        'expected_output_selectors' => [
          'h2:contains("Our Mission")',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [],
        ],
        'propSources' => [
          'text' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'minLength' => 2,
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Our mission is to deliver the best products and services to our customers. We strive to exceed expectations and continuously improve our offerings.',
                ],
              ],
              'resolved' => 'Our mission is to deliver the best products and services to our customers. We strive to exceed expectations and continuously improve our offerings.',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.one_column' => [
        'expected_output_selectors' => [
          'div[data-component-id="canvas_test_sdc:one_column"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [
            'content' => [
              'title' => 'Content',
              'description' => 'The contents of the column.',
            ],
          ],
        ],
        'propSources' => [
          'width' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'full',
                1 => 'wide',
                2 => 'normal',
                3 => 'narrow',
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
                0 => [
                  'value' => 'full',
                ],
              ],
              'resolved' => 'full',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.props-no-slots' => [
        'expected_output_selectors' => [
          'h1:contains("There goes my hero")',
          'div[data-component-id="canvas_test_sdc:props-no-slots"]',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'heading' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'minLength' => 2,
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 'There goes my hero'],
              ],
              'resolved' => 'There goes my hero',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.props-slots' => [
        'expected_output_selectors' => [
          'div[data-component-id="canvas_test_sdc:props-slots"]',
          'h1:contains("There goes my hero")',
          'div.component--props-slots--body',
          'div.component--props-slots--footer',
          'div.component--props-slots--colophon',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [
            'the_body' => [
              'title' => 'The Body',
              'description' => 'The contents of the body.',
              'examples' => [
                '<p>Example value for <strong>the_body</strong> slot in <strong>prop-slots</strong> component.</p>',
              ],
            ],
            'the_footer' => [
              'title' => 'The Footer',
              'description' => 'The contents of the footer.',
              'examples' => [
                'Example value for <strong>the_footer</strong>.',
              ],
            ],
            'the_colophon' => [
              'title' => 'The Colophon',
              'description' => 'The contents of the colophon.',
              'examples' => [],
            ],
          ],
        ],
        'propSources' => [
          'heading' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'minLength' => 2,
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => ['value' => 'There goes my hero'],
              ],
              'resolved' => 'There goes my hero',
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.shoe_badge' => [
        'expected_output_selectors' => [
          'sl-badge[data-component-variant="primary"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [
            'content' => [
              'title' => 'Content',
              'description' => 'The contents of the badge.',
              'examples' => [
                0 => 'Badge',
              ],
            ],
          ],
        ],
        'propSources' => [
          'variant' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'primary',
                1 => 'success',
                2 => 'neutral',
                3 => 'warning',
                4 => 'danger',
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
                0 => [
                  'value' => 'primary',
                ],
              ],
              'resolved' => 'primary',
            ],
          ],
          'pill' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'boolean',
            ],
            'sourceType' => 'static:field_item:boolean',
            'expression' => 'ℹ︎boolean␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => TRUE,
                ],
              ],
              'resolved' => TRUE,
            ],
          ],
          'pulse' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'boolean',
            ],
            'sourceType' => 'static:field_item:boolean',
            'expression' => 'ℹ︎boolean␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => TRUE,
                ],
              ],
              'resolved' => TRUE,
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.shoe_tab' => [
        'expected_output_selectors' => [
          'sl-tab[data-component-id="canvas_test_sdc:shoe_tab"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [],
        ],
        'propSources' => [
          'label' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'Tab 1',
                ],
              ],
              'resolved' => 'Tab 1',
            ],
          ],
          'panel' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
            ],
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => 'tab_1',
                ],
              ],
              'resolved' => 'tab_1',
            ],
          ],
          'active' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'boolean',
            ],
            'sourceType' => 'static:field_item:boolean',
            'expression' => 'ℹ︎boolean␟value',
          ],
          'closable' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'boolean',
            ],
            'sourceType' => 'static:field_item:boolean',
            'expression' => 'ℹ︎boolean␟value',
          ],
          'disabled' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'boolean',
            ],
            'sourceType' => 'static:field_item:boolean',
            'expression' => 'ℹ︎boolean␟value',
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.shoe_tab_group' => [
        'expected_output_selectors' => [
          'sl-tab-group[data-component-id="canvas_test_sdc:shoe_tab_group"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [
            'tabs' => [
              'title' => 'Tab Nav',
              'description' => 'The tabs.',
            ],
            'tab_panels' => [
              'title' => 'Tab Panels',
              'description' => 'The tab panels.',
            ],
          ],
        ],
        'propSources' => [
          'placement' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'top',
                1 => 'bottom',
                2 => 'start',
                3 => 'end',
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
                0 => [
                  'value' => 'top',
                ],
              ],
              'resolved' => 'top',
            ],
          ],
          'activation' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'string',
              'enum' => [
                0 => 'auto',
                1 => 'manual',
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
                0 => [
                  'value' => 'auto',
                ],
              ],
              'resolved' => 'auto',
            ],
          ],
          'no_scroll' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'boolean',
            ],
            'sourceType' => 'static:field_item:boolean',
            'expression' => 'ℹ︎boolean␟value',
            'default_values' => [
              'source' => [
                0 => [
                  'value' => FALSE,
                ],
              ],
              'resolved' => FALSE,
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.shoe_tab_panel' => [
        'expected_output_selectors' => [
          'sl-tab-panel[data-component-id="canvas_test_sdc:shoe_tab_panel"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [
            'content' => [
              'title' => 'Tab Content',
              'description' => 'The contents of the tab.',
              'examples' => [
                0 => '<p>This is tab content</p>',
              ],
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
                0 => [
                  'value' => 'tab_name',
                ],
              ],
              'resolved' => 'tab_name',
            ],
          ],
          'active' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'boolean',
            ],
            'sourceType' => 'static:field_item:boolean',
            'expression' => 'ℹ︎boolean␟value',
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.sparkline' => [
        'expected_output_selectors' => [
          'div.sparkline-container > svg > polygon[points="0,20 0,7.5 12.5,5 25,2.5 37.5,0 50,17.5 62.5,20 75,6.25 87.5,5.75 100,5.25 100,20"]',
        ],
        'source' => 'Module component',
        'metadata' => ['slots' => []],
        'propSources' => [
          'data' => [
            'required' => TRUE,
            'jsonSchema' => [
              'type' => 'array',
              'items' => [
                'type' => 'integer',
                'minimum' => -100,
                'maximum' => 100,
              ],
              'maxItems' => 100,
            ],
            'sourceType' => 'static:field_item:integer',
            'expression' => 'ℹ︎integer␟value',
            'sourceTypeSettings' => [
              'instance' => ['min' => -100, 'max' => 100],
              'cardinality' => 100,
            ],
            'default_values' => [
              'source' => [
                0 => ['value' => 0],
                1 => ['value' => 10],
                2 => ['value' => 20],
                3 => ['value' => 30],
                4 => ['value' => -40],
                5 => ['value' => -50],
                6 => ['value' => 5],
                7 => ['value' => 7],
                8 => ['value' => 9],
              ],
              'resolved' => [
                0, 10, 20, 30, -40, -50, 5, 7, 9,
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.two_column' => [
        'expected_output_selectors' => [
          'div[data-component-id="canvas_test_sdc:two_column"]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [
            'column_one' => [
              'title' => 'Column One',
              'description' => 'The contents of the first column.',
              'examples' => [
                0 => '<p>This is column 1 content</p>',
              ],
            ],
            'column_two' => [
              'title' => 'Column Two',
              'description' => 'The contents of the second column.',
              'examples' => [
                0 => '<p>This is column 2 content</p>',
              ],
            ],
          ],
        ],
        'propSources' => [
          'width' => [
            'required' => TRUE,
            'jsonSchema' => [
              'title' => 'Column Width',
              'type' => 'integer',
              'enum' => [
                0 => 25,
                1 => 33,
                2 => 50,
                3 => 66,
                4 => 75,
              ],
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
                0 => [
                  'value' => 25,
                ],
              ],
              'resolved' => 25,
            ],
          ],
        ],
        'transforms' => [],
      ],
      'sdc.canvas_test_sdc.video' => [
        'expected_output_selectors' => [
          'video[poster="https://example.com/600x400.png"]',
          'video > source[src="https://media.istockphoto.com/id/1340051874/video/aerial-top-down-view-of-a-container-cargo-ship.mp4?s=mp4-640x640-is&k=20&c=5qPpYI7TOJiOYzKq9V2myBvUno6Fq2XM3ITPGFE8Cd8="]',
        ],
        'source' => 'Module component',
        'metadata' => [
          'slots' => [],
        ],
        'propSources' => [
          'video' => [
            'required' => TRUE,
            'jsonSchema' => [
              'title' => 'video',
              'type' => 'object',
              'required' => [
                0 => 'src',
              ],
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
            'sourceType' => 'static:field_item:file',
            'expression' => 'ℹ︎file␟{src↝entity␜␜entity:file␝uri␞␟url}',
            'sourceTypeSettings' => [
              'instance' => [
                'file_extensions' => 'mp4',
              ],
            ],
            'default_values' => [
              'source' => [],
              'resolved' => [
                'src' => 'https://media.istockphoto.com/id/1340051874/video/aerial-top-down-view-of-a-container-cargo-ship.mp4?s=mp4-640x640-is&k=20&c=5qPpYI7TOJiOYzKq9V2myBvUno6Fq2XM3ITPGFE8Cd8=',
                'poster' => 'https://example.com/600x400.png',
              ],
            ],
          ],
          'display_width' => [
            'required' => FALSE,
            'jsonSchema' => [
              'type' => 'integer',
              'minimum' => 1,
            ],
            'sourceType' => 'static:field_item:integer',
            'expression' => 'ℹ︎integer␟value',
            'sourceTypeSettings' => [
              'instance' => [
                'min' => 1,
                'max' => NULL,
              ],
            ],
          ],
        ],
        'transforms' => [],
      ],
    ];
  }

  /**
   * @covers ::inputToClientModel()
   * @dataProvider explicitsInputsProvider
   */
  public function testInputToClientModel(string $component_id, array $explicit_input, array $expected_client_model):void {
    $this->generateComponentConfig();

    // Ensure the explicit input is valid; helps catch incorrect test cases that
    // could lead to misleading test results.
    foreach ($explicit_input['source'] as $prop_name => $prop_source_array) {
      $resolved_source = PropSource::parse($prop_source_array)->evaluate(NULL, is_required: TRUE);
      self::assertSame($resolved_source, $explicit_input['resolved'][$prop_name]);
    }

    $component = Component::load($component_id);
    \assert($component instanceof Component);
    $component_source = $component->getComponentSource();
    // It can only be Code components or SDC.
    \assert($component_source instanceof GeneratedFieldExplicitInputUxComponentSourceBase);
    $actual_model_client = $component_source->inputToClientModel($explicit_input);
    $this->assertEquals($expected_client_model, $actual_model_client);
  }

  public static function explicitsInputsProvider(): \Generator {
    yield 'image' => [
      'sdc.canvas_test_sdc.image',
      [
        'source' => [
          'image' => [
            'sourceType' => 'default-relative-url',
            'value' => [
              'src' => '/modules/contrib/canvas_test_sdc/components/image/600x400.png',
              'alt' => 'Boring placeholder',
              'width' => 600,
              'height' => 400,
            ],
            'jsonSchema' => [
              'type' => 'object',
              'properties' => [
                'src' => [
                  'type' => 'string',
                  'contentMediaType' => 'image/*',
                  'format' => 'uri-reference',
                  'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$',
                ],
                'alt' => [
                  'type' => 'string',
                ],
                'width' => [
                  'type' => 'integer',
                ],
                'height' => [
                  'type' => 'integer',
                ],
              ],
              'required' => [
                'src',
              ],
            ],
            'componentId' => 'sdc.canvas_test_sdc.image',
          ],
        ],
        'resolved' => [
          'image' => [
            'src' => '/modules/contrib/canvas_test_sdc/components/image/600x400.png',
            'alt' => 'Boring placeholder',
            'width' => 600,
            'height' => 400,
          ],
        ],
      ],
      [
        'source' => [
          'image' => [
            'sourceType' => 'static:field_item:image',
            'expression' => 'ℹ︎image␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
            'value' => NULL,
          ],
        ],
        'resolved' => [
          'image' => [
            'src' => '/modules/contrib/canvas_test_sdc/components/image/600x400.png',
            'alt' => 'Boring placeholder',
            'width' => 600,
            'height' => 400,
          ],
        ],
      ],
    ];
  }

  protected function createAndSaveInUseComponentForFallbackTesting(): ComponentInterface {
    // Media library depends on the views module and media depends on field
    // config.
    $this->enableModules(['media', 'media_library', 'views', 'field']);
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    /** @var \Drupal\canvas\Entity\ComponentInterface */
    return Component::load('sdc.canvas_test_sdc.image');
  }

  protected function createAndSaveUnusedComponentForFallbackTesting(): ComponentInterface {
    /** @var \Drupal\canvas\Entity\ComponentInterface */
    return Component::load('sdc.canvas_test_sdc.image-optional-without-example');
  }

  protected function deleteConfigAndTriggerComponentFallback(ComponentInterface $used_component, ComponentInterface $unused_component): void {
    $type = MediaType::load('image');
    \assert($type instanceof MediaType);
    $type->delete();
  }

  protected static function getPropsForComponentFallbackTesting(): array {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_uri = 'public://image-2.jpg';
    if (!\file_exists($file_uri)) {
      $file_system->copy(\Drupal::root() . '/core/tests/fixtures/files/image-2.jpg', PublicStream::basePath(), FileExists::Replace);
    }
    $file = File::create([
      'uri' => $file_uri,
      'status' => 1,
    ]);
    $file->save();
    $image = Media::create([
      'bundle' => 'image',
      'name' => 'Amazing image',
      'field_media_image' => [
        [
          'target_id' => $file->id(),
          'alt' => 'An image so amazing that to gaze upon it would melt your face',
          'title' => 'This is an amazing image, just look at it and you will be amazed',
        ],
      ],
    ]);
    $image->save();
    return [
      'image' => [
        'sourceType' => 'static:field_item:entity_reference',
        'value' => ['target_id' => $image->id()],
        // This expression resolves `src` to the image's public URL.
        'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
        'sourceTypeSettings' => [
          'storage' => ['target_type' => 'media'],
          'instance' => [
            'handler' => 'default:media',
            'handler_settings' => [
              'target_bundles' => ['image' => 'image'],
            ],
          ],
        ],
      ],
    ];
  }

  protected function recoverComponentFallback(ComponentInterface $component): void {
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);
    $this->generateComponentConfig();
  }

  protected function createAndSaveInUseComponentForUninstallValidationTesting(): ComponentInterface {
    $this->enableModules(['sdc_test']);
    $this->generateComponentConfig();
    /** @var \Drupal\canvas\Entity\ComponentInterface */
    return Component::load('sdc.sdc_test.no-props');
  }

  protected function createAndSaveUnusedComponentForUninstallValidationTesting(): ComponentInterface {
    /** @var \Drupal\canvas\Entity\ComponentInterface */
    return Component::load('sdc.canvas_test_sdc.props-slots');
  }

  protected function getNotAllowedModuleForUninstallValidatorTesting(): string {
    return 'sdc_test';
  }

  protected function getAllowedModuleForUninstallValidatorTesting(): string {
    return 'canvas_test_sdc';
  }

}
