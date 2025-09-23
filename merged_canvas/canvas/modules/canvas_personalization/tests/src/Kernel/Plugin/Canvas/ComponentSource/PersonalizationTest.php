<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Kernel\Plugin\Canvas\ComponentSource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource\ComponentSourceTestBase;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\SingleDirectoryComponentTreeTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\canvas\Traits\CrawlerTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\canvas_personalization\Entity\Segment;
use Drupal\canvas_personalization\Plugin\Canvas\ComponentSource\Personalization;

/**
 * @coversDefaultClass \Drupal\canvas_personalization\Plugin\Canvas\ComponentSource\Personalization
 * @group canvas_personalization
 * @phpstan-import-type ComponentConfigEntityId from \Drupal\canvas\Entity\Component
 */
final class PersonalizationTest extends ComponentSourceTestBase {

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
    'canvas_personalization',
    'canvas_test_sdc',
    'image',
    'media',
    'node',
    'path',
    'user',
    'field',
    'text',
    // @todo Remove once ComponentSourceInterface is a public API, i.e. after https://www.drupal.org/i/3520484#stable is done.
    'canvas_dev_mode',
  ];

  /**
   * This ComponentSource does not need any discovery.
   *
   * @see ::testDiscovery()
   * @see modules/canvas_personalization/config/install/canvas.component.p13n.case.yml
   * @see modules/canvas_personalization/config/install/canvas.component.p13n.switch.yml
   */
  protected int $expectedDefaultComponentInstallCount = 2;

  /**
   * Setup tests.
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig('canvas_personalization');
    Segment::create([
      'id' => 'halloween',
      'label' => 'Halloween',
    ])->save();
    Segment::create([
      'id' => 'andalusian_visitors',
      'label' => 'Andalusian Visitors',
    ])->save();
  }

  /**
   * Test our case and switch personalization Components are installed.
   *
   * @covers ::checkRequirements()
   */
  public function testDiscovery(): array {
    $provided_components = [
      // @see modules/canvas_personalization/config/install/canvas.component.p13n.case.yml
      'p13n.case',
      // @see modules/canvas_personalization/config/install/canvas.component.p13n.switch.yml
      'p13n.switch',
    ];

    // This source does not need discovery, because the Components are known.
    self::assertSame([], $this->findIneligibleComponents(Personalization::SOURCE_PLUGIN_ID, ''));
    self::assertSame($provided_components, $this->findCreatedComponentConfigEntities(Personalization::SOURCE_PLUGIN_ID, ''));

    return array_combine($provided_components, $provided_components);
  }

  /**
   * Tests settings for the personalization components.
   *
   * @depends testDiscovery
   */
  public function testSettings(array $component_ids): void {
    $settings = $this->getAllSettings($component_ids);
    self::assertSame(self::getExpectedSettings(), $settings);
  }

  /**
   * Tests that personalization components don't provide a class.
   *
   * @param array<ComponentConfigEntityId> $component_ids
   *
   * @covers ::getReferencedPluginClass()
   * @depends testDiscovery
   */
  public function testGetReferencedPluginClass(array $component_ids): void {
    self::assertSame(
      // No personalization components define a plugin class.
      array_fill_keys($component_ids, NULL),
      $this->getReferencedPluginClasses($component_ids)
    );
  }

  /**
   * Tests rendering personalization components.
   *
   * @param array<ComponentConfigEntityId> $component_ids
   *
   * @covers ::renderComponent()
   * @depends testDiscovery
   */
  public function testRenderComponentLive(array $component_ids): void {
    $this->assertNotEmpty($component_ids);

    $rendered = $this->renderComponentsLive(
      $component_ids,
      get_default_input: [__CLASS__, 'getDefaultInput'],
    );

    // @see core.services.yml
    $default_render_cache_contexts = [
      'languages:language_interface',
      'theme',
      'user.permissions',
    ];
    $default_cacheability = (new CacheableMetadata())
      ->setCacheContexts($default_render_cache_contexts);

    $this->assertEquals([
      'p13n.case' => [
        'html' => <<<HTML
<div canvas_uuid="some-uuid" canvas_type="case" canvas_slot_ids="content"></div>

HTML,
        'cacheability' => (clone $default_cacheability)->setCacheContexts([
          ...array_slice($default_render_cache_contexts, 0, 2),
          // @todo This should depend on the segments. Fix in https://www.drupal.org/project/canvas/issues/3525797
          'url.query_args:utm_campaign',
          ...array_slice($default_render_cache_contexts, 2),
        ]),
        'attachments' => [],
      ],
      'p13n.switch' => [
        'html' => '',
        // Note this has no cacheability (beyond the render system's default),
        // because this renders to nothing (the empty string above).
        // @see Personalization::renderComponent()
        // Take into account that e.g. if a tree changed because of new added
        // variants, the tree host itself would be invalidated (e.g. node:23
        // would be invalidated).
        'cacheability' => new CacheableMetadata(),
        'attachments' => [],
      ],
    ], $rendered);
  }

  /**
   * Tests rendering personalization component previews.
   *
   * @param array<ComponentConfigEntityId> $component_ids
   *
   * @covers ::renderComponent()
   * @depends testDiscovery
   */
  public function testRenderComponentPreview(array $component_ids): void {
    $this->assertNotEmpty($component_ids);

    $rendered = $this->renderComponentsPreview(
      $component_ids,
      get_default_input: [__CLASS__, 'getDefaultInput'],
    );

    $default_render_cache_contexts = [
      'languages:language_interface',
      'theme',
      'user.permissions',
    ];
    $default_cacheability = (new CacheableMetadata())
      ->setCacheContexts($default_render_cache_contexts);
    $this->assertEquals([
      'p13n.case' => [
        'html' => <<<HTML
<div canvas_uuid="some-uuid" canvas_type="case" canvas_slot_ids="content"></div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [],
      ],
      'p13n.switch' => [
        'html' => <<<HTML
<div canvas_uuid="some-uuid" canvas_type="switch" canvas_slot_ids="content"></div>

HTML,
        'cacheability' => $default_cacheability,
        'attachments' => [],
      ],
    ], $rendered);
  }

  /**
   * @param array<ComponentConfigEntityId> $component_ids
   */
  protected function renderComponentsPreview(array $component_ids, callable $get_default_input): array {
    $this->assertCount($this->expectedDefaultComponentInstallCount, $this->componentStorage->loadMultiple());
    $this->generateComponentConfig();

    $rendered = [];
    foreach ($this->componentStorage->loadMultiple($component_ids) as $component_id => $component) {
      assert($component instanceof ComponentInterface);
      $build = $component->getComponentSource()->renderComponent(
        $get_default_input($component),
        'some-uuid',
        // Preview: `isPreview: TRUE`.
        TRUE,
      );
      $html = (string) $this->renderer->renderInIsolation($build);
      // Strip trailing whitespace to make heredocs easier to write.
      $html = preg_replace('/ +$/m', '', $html);
      assert(is_string($html));
      // Make it easier to write expectations containing root-relative URLs
      // pointing somewhere into the site-specific directory.
      $html = str_replace(base_path() . $this->siteDirectory, '::SITE_DIR_BASE_URL::', $html);
      $html = str_replace(self::getCiModulePath(), '::CANVAS_MODULE_PATH::', $html);
      // Ensure predictable order of cache contexts & tags.
      // @see https://www.drupal.org/node/3230171
      sort($build['#cache']['contexts']);
      sort($build['#cache']['tags']);
      $rendered[$component_id] = [
        'html' => $html,
        'cacheability' => CacheableMetadata::createFromRenderArray($build),
        'attachments' => BubbleableMetadata::createFromRenderArray($build)->getAttachments(),
      ];
    }
    return $rendered;
  }

  /**
   * For use with ::renderComponentsLive() and ::renderComponentsPreview() with some input.
   *
   * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::exampleValueRequiresEntity()
   * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::getDefaultStaticPropSource()
   */
  protected static function getDefaultInput(Component $component): array {
    $type = $component->id();
    if ($type === 'p13n.case') {
      return [
        'variant_id' => 'my_variation',
        'segments' => [
          'andalusian_visitors',
          'halloween',
          Segment::DEFAULT_ID,
        ],
      ];
    }
    elseif ($type === 'p13n.switch') {
      return [
        'variants' => [
          'my_variation' => [
            'label' => 'My variation',
            'segments' => [
              'andalusian_visitors',
            ],
          ],
          'default' => [
            'label' => 'Default',
            'segments' => [
              Segment::DEFAULT_ID,
            ],
          ],
        ],
      ];
    }
    return [];
  }

  public static function providerRenderComponentFailure(): \Generator {
    yield 'p13n.switch with no inputs' => [
      'component_id' => 'p13n.switch',
      'inputs' => [],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.[variants]', self::UUID_CRASH_TEST_DUMMY) => 'This field is missing.',
      ],
      'expected_exception' => NULL,
      'expected_output_selector' => 'div',
    ];
    yield 'p13n.switch with no variants' => [
      'component_id' => 'p13n.switch',
      'inputs' => [
        'variants' => [],
      ],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.[variants]', self::UUID_CRASH_TEST_DUMMY) => 'This value should not be blank.',
      ],
      'expected_exception' => NULL,
      'expected_output_selector' => 'div',
    ];
    yield 'p13n.case with no inputs' => [
      'component_id' => 'p13n.switch',
      'inputs' => [],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.[variants]', self::UUID_CRASH_TEST_DUMMY) => 'This field is missing.',
      ],
      'expected_exception' => NULL,
      'expected_output_selector' => 'div',
    ];
    yield 'p13n.case with invalid variant_id' => [
      'component_id' => 'p13n.case',
      'inputs' => [
        'variant_id' => 'this-does-not-exist-in-parent',
        'segments' => ['default'],
      ],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.[variant_id]', self::UUID_CRASH_TEST_DUMMY) => 'This value is not valid.',
      ],
      'expected_exception' => NULL,
      'expected_output_selector' => 'div',
    ];
    yield 'p13n.case with no variant_id' => [
      'component_id' => 'p13n.case',
      'inputs' => [
        'segments' => ['default'],
      ],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.[variant_id]', self::UUID_CRASH_TEST_DUMMY) => 'This field is missing.',
      ],
      'expected_exception' => NULL,
      'expected_output_selector' => 'div',
    ];
    yield 'p13n.case with empty segments' => [
      'component_id' => 'p13n.case',
      'inputs' => [
        'variant_id' => 'default',
        'segments' => [],
      ],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.[segments]', self::UUID_CRASH_TEST_DUMMY)  => 'This value should not be blank.',
      ],
      'expected_exception' => NULL,
      'expected_output_selector' => 'div',
    ];
    yield 'p13n.case pointing to a non-existing Segment config entity' => [
      'component_id' => 'p13n.case',
      'inputs' => [
        'variant_id' => 'default',
        'segments' => [
          'to_be_or_not_to_be',
        ],
      ],
      'expected_validation_errors' => [
        \sprintf('2.inputs.%s.[segments][0]', self::UUID_CRASH_TEST_DUMMY)  => "The 'canvas_personalization.segment.to_be_or_not_to_be' config does not exist.",
      ],
      'expected_exception' => NULL,
      'expected_output_selector' => 'div',
    ];
  }

  public static function getExpectedSettings(): array {
    // These Components have no settings.
    // @see `type: canvas.component_source_settings.*`
    return [
      'p13n.case' => [],
      'p13n.switch' => [],
    ];
  }

  /**
   * @covers ::calculateDependencies()
   * @depends testDiscovery
   */
  public function testCalculateDependencies(array $component_ids): void {
    self::assertSame([
      'p13n.case' => [
        'module' => [
          'canvas_personalization',
        ],
      ],
      'p13n.switch' => [
        'module' => [
          'canvas_personalization',
        ],
      ],
    ], $this->callSourceMethodForEach('calculateDependencies', $component_ids));
  }

  /**
   * {@inheritdoc}
   */
  public static function getExpectedClientSideInfo(): array {
    return [
      'p13n.case' => [
        'expected_output_selectors' => [
          'h1',
        ],
        'metadata' => [
          'slots' => [
            'content' => [
              'title' => 'Content',
              'description' => 'The component tree for this variant',
              'examples' => [''],
            ],
          ],
        ],
      ],
      'p13n.switch' => [
        'expected_output_selectors' => [
          'h1',
        ],
        'metadata' => [
          'slots' => [
            'content' => [
              'title' => 'Content',
              'description' => 'The variants',
              'examples' => [''],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * @covers ::inputToClientModel()
   * @covers ::clientModelToInput()
   * @dataProvider explicitsInputsProvider
   */
  public function testInputToClientModel(string $component_id, array $explicit_input): void {
    $this->generateComponentConfig();

    $component = Component::load($component_id);
    \assert($component instanceof Component);
    $component_source = $component->getComponentSource();
    $actual_model_client = $component_source->inputToClientModel($explicit_input);

    // ⚠️ Note how ::inputToClientModel() and ::clientModelToInput() are no-ops
    // in this ComponentSource plugin!
    self::assertSame(['resolved' => $explicit_input], $actual_model_client);
    // @phpstan-ignore-next-line argument.type
    self::assertSame($explicit_input, $component_source->clientModelToInput('20a189a3-8bf2-4384-b2f4-495a6812f372', $component, $actual_model_client, NULL));
  }

  public static function explicitsInputsProvider(): \Generator {
    // @todo Eliminate `variants` explicit input from `switch` component instances if they turn out to be unnecessary.
    yield 'p13n.switch' => [
      'p13n.switch',
      [
        'variants' => [
          'belgian_fries_lovers',
          Segment::DEFAULT_ID,
        ],
      ],
    ];
    yield 'p13n.case' => [
      'p13n.case',
      [
        'variant_id' => 'belgian_fries_lovers',
        'segments' => [
          'belgian',
        ],
      ],
    ];
  }

  public function testFallback(): void {
    $this->markTestSkipped('Fallbacks make sense for ComponentSource plugins that have a dynamic set of components available. But here, it is literally impossible for the 2 components provided by this ComponentSource to disappear. Unless the module itself is uninstalled, but then due to enforced dependencies the default Component config entities, the fallback metadata would be lost too.');
  }

  protected function createAndSaveInUseComponentForFallbackTesting(): ComponentInterface {
    // @see ::testFallback()
    throw new \OutOfRangeException();
  }

  protected function createAndSaveUnusedComponentForFallbackTesting(): ComponentInterface {
    // @see ::testFallback()
    throw new \OutOfRangeException();
  }

  protected function deleteConfigAndTriggerComponentFallback(ComponentInterface $used_component, ComponentInterface $unused_component): void {
    // @see ::testFallback()
    throw new \OutOfRangeException();
  }

  protected function recoverComponentFallback(ComponentInterface $component): void {
    // @see ::testFallback()
    throw new \OutOfRangeException();
  }

  protected function createAndSaveInUseComponentForUninstallValidationTesting(): ComponentInterface {
    // @see ::testFallback()
    throw new \OutOfRangeException();
  }

  protected function createAndSaveUnusedComponentForUninstallValidationTesting(): ComponentInterface {
    // @see ::testFallback()
    throw new \OutOfRangeException();
  }

  protected function getNotAllowedModuleForUninstallValidatorTesting(): string {
    // @see ::testFallback()
    throw new \OutOfRangeException();
  }

  protected function getAllowedModuleForUninstallValidatorTesting(): string {
    // @see ::testFallback()
    throw new \OutOfRangeException();
  }

  public function testUninstallValidator(): void {
    $this->markTestSkipped('Uninstalling make sense for ComponentSource plugins that have a dynamic set of components available. But here, it is literally impossible for the 2 components provided by this ComponentSource to disappear. Unless the module itself is uninstalled, but then due to enforced dependencies the default Component config entities, the fallback metadata would be lost too.');
  }

}
