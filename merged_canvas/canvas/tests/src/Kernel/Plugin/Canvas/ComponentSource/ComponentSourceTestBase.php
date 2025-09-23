<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource;

// cspell:ignore Druplicons

use Drupal\Component\Utility\Html;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\canvas\ComponentIncompatibilityReasonRepository;
use Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\canvas\PropSource\StaticPropSource;
use Drupal\canvas\Storage\ComponentTreeLoader;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Drupal\Tests\canvas\Kernel\Traits\VfsPublicStreamUrlTrait;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\CrawlerTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\canvas\Traits\UninstallValidatorTestTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Provides the basic infrastructure for consistently testing component sources.
 *
 * Every ComponentSource plugin should subclass this. Each must implement
 * `::testDiscovery()`. Most other test methods should depend on it, and test
 * critical ComponentSource plugin functionality, such as:
 * - getting the plugin class (if any) for each component, critical for
 *   restricting Canvas component trees
 * - a component instance that crashes during rendering due to logic or invalid
 *   input does not result in complete failure
 * - rendering of component instances on the live site
 * - generating client-side info that powers the Canvas UI
 * - the source-specific settings that were generated for the discovered
 *   Component config entity
 * - calculating of source-specific dependencies
 * - et cetera
 *
 * @phpstan-import-type ComponentConfigEntityId from \Drupal\canvas\Entity\Component
 */
abstract class ComponentSourceTestBase extends KernelTestBase implements LoggerInterface {

  use RfcLoggerTrait;
  use UninstallValidatorTestTrait;
  use VfsPublicStreamUrlTrait;

  protected const string UUID_CRASH_TEST_DUMMY = '3204a711-a1bd-401d-9ce0-895665487eaa';

  private const string UUID_FALLBACK_ROOT = 'd61651f3-e46b-45fa-aff1-beb95c64a886';

  protected array $logMessages = [];

  /**
   * The number of Component config entities to expect prior to discovery.
   *
   * @var int
   */
  protected int $expectedDefaultComponentInstallCount = 0;

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    if ($level <= RfcLogLevel::ERROR) {
      $this->logMessages[] = $message;
    }
  }

  use CiModulePathTrait;
  use CrawlerTrait;
  use ComponentTreeItemListInstantiatorTrait;
  use ConstraintViolationsTestTrait;
  use ContribStrictConfigSchemaTestTrait;
  use GenerateComponentConfigTrait;

  protected readonly EntityStorageInterface $componentStorage;
  protected readonly ComponentIncompatibilityReasonRepository $componentReasonRepository;
  protected readonly ConfigFactoryInterface $configFactory;
  protected readonly ComponentTreeLoader $componentTreeLoader;
  protected readonly RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'file',
    'image',
    'link',
    'options',
    'system',
    'media',
    'path',
    'canvas_test_sdc',
    'block',
    'datetime',
    'user',
    'filter',
    'ckeditor5',
    'editor',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->componentReasonRepository = $this->container->get(ComponentIncompatibilityReasonRepository::class);
    $this->componentStorage = $this->container->get(EntityTypeManagerInterface::class)->getStorage(Component::ENTITY_TYPE_ID);
    $this->configFactory = $this->container->get(ConfigFactoryInterface::class);
    $this->componentTreeLoader = $this->container->get(ComponentTreeLoader::class);
    $this->renderer = $this->container->get(RendererInterface::class);
    $this->installEntitySchema('user');
    $this->installSchema('user', 'users_data');
    $this->installConfig('canvas');
  }

  /**
   * @see ::findCreatedComponentConfigEntities()
   * @see ::findIneligibleComponents()
   */
  abstract public function testDiscovery(): array;

  /**
   * @see ::renderComponentsLive()
   */
  abstract public function testRenderComponentLive(array $component_ids): void;

  /**
   * @see ::getReferencedPluginClasses()
   * @see \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeMeetsRequirementsConstraint
   */
  abstract public function testGetReferencedPluginClass(array $component_ids): void;

  /**
   * @see ::getAllSettings()
   */
  abstract public function testSettings(array $component_ids): void;

  /**
   * @see ::getAllCalculatedDependencies()
   */
  abstract public function testCalculateDependencies(array $component_ids): void;

  /**
   * @param array<ComponentConfigEntityId> $component_ids
   * @return array<ComponentConfigEntityId, array>
   */
  protected function getAllSettings(array $component_ids): array {
    $this->assertNotEmpty($component_ids);
    $this->assertCount($this->expectedDefaultComponentInstallCount, $this->componentStorage->loadMultiple());
    $this->generateComponentConfig();
    $components = $this->componentStorage->loadMultiple($component_ids);

    $settings = [];
    foreach ($components as $component_id => $component) {
      assert($component instanceof Component);
      $settings[$component_id] = $component->getSettings();
    }
    return $settings;
  }

  /**
   * @param string $method_name
   * @param array<ComponentConfigEntityId> $component_ids
   * @return array<ComponentConfigEntityId, array>
   */
  protected function callSourceMethodForEach(string $method_name, array $component_ids): array {
    $this->assertNotEmpty($component_ids);
    $this->assertCount($this->expectedDefaultComponentInstallCount, $this->componentStorage->loadMultiple());
    $this->generateComponentConfig();
    $components = $this->componentStorage->loadMultiple($component_ids);

    $return_values = [];
    foreach ($components as $component_id => $component) {
      assert($component instanceof Component);
      $return_values[$component_id] = match ($method_name) {
        'getClientSideInfo' => $component->getComponentSource()->getClientSideInfo($component),
        default => $component->getComponentSource()->$method_name(),
      };
    }
    return $return_values;
  }

  public function findCreatedComponentConfigEntities(string $component_source_plugin_id, string $test_module): array {
    // @phpstan-ignore-next-line
    $component_config_entity_type_prefix = $this->componentStorage->getEntityType()->getConfigPrefix();

    // Construct a config prefix to discover all Component config entities
    // created for the tested ComponentSource's test module.
    $prefix = sprintf(
      '%s.%s.%s',
      $component_config_entity_type_prefix,
      $component_source_plugin_id,
      $test_module,
    );

    // Transform from `canvas.component.<ID>` to just `<ID>`.
    $discovered_component_config_names = $this->configFactory->listAll($prefix);
    $discovered_component_entity_ids = array_map(
      fn(string $config_name) => str_replace("$component_config_entity_type_prefix.", '', $config_name),
      $discovered_component_config_names
    );

    ksort($discovered_component_entity_ids);
    return $discovered_component_entity_ids;
  }

  public function findIneligibleComponents(string $component_source_plugin_id, string $test_module): array {
    $ineligible_components = $this->componentReasonRepository->getReasons()[$component_source_plugin_id] ?? [];
    ksort($ineligible_components);
    return array_filter(
      $ineligible_components,
      fn (string $id) => str_starts_with($id, $component_source_plugin_id . '.' . $test_module),
      ARRAY_FILTER_USE_KEY,
    );
  }

  /**
   * @param array<ComponentConfigEntityId> $component_ids
   * @return array<ComponentConfigEntityId, class-string|null>
   */
  protected function getReferencedPluginClasses(array $component_ids): array {
    $this->assertCount($this->expectedDefaultComponentInstallCount, $this->componentStorage->loadMultiple());
    $this->generateComponentConfig();

    $actual_classes = [];
    foreach ($this->componentStorage->loadMultiple($component_ids) as $component_id => $component) {
      assert($component instanceof Component);
      $actual_classes[$component_id] = $component->getComponentSource()->getReferencedPluginClass();
    }
    return $actual_classes;
  }

  /**
   * @param array<ComponentConfigEntityId> $component_ids
   */
  protected function renderComponentsLive(array $component_ids, callable $get_default_input): array {
    $this->assertCount($this->expectedDefaultComponentInstallCount, $this->componentStorage->loadMultiple());
    $this->generateComponentConfig();

    $rendered = [];
    foreach ($this->componentStorage->loadMultiple($component_ids) as $component_id => $component) {
      assert($component instanceof ComponentInterface);
      $build = $component->getComponentSource()->renderComponent(
        $get_default_input($component),
        'some-uuid',
        // Live: `isPreview: FALSE`.
        FALSE,
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
      if (isset($build['#cache']['contexts'])) {
        sort($build['#cache']['contexts']);
      }
      if (isset($build['#cache']['tags'])) {
        sort($build['#cache']['tags']);
      }
      $rendered[$component_id] = [
        'html' => $html,
        'cacheability' => CacheableMetadata::createFromRenderArray($build),
        'attachments' => BubbleableMetadata::createFromRenderArray($build)->getAttachments(),
      ];
    }
    return $rendered;
  }

  /**
   * For use with ::renderComponentsLive() for Sources with generated input UX.
   *
   * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::exampleValueRequiresEntity()
   * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::getDefaultStaticPropSource()
   */
  protected static function getDefaultInputForGeneratedInputUx(Component $component): array {
    assert($component->getComponentSource() instanceof GeneratedFieldExplicitInputUxComponentSourceBase);
    $explicit_inputs = [];
    foreach ($component->getSettings()['prop_field_definitions'] as $sdc_prop_name => $prop_field_definition) {
      if ($prop_field_definition['default_value'] === NULL) {
        continue;
      }

      // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::exampleValueRequiresEntity()
      // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::getDefaultStaticPropSource()
      if ($prop_field_definition['default_value'] === []) {
        // @phpstan-ignore-next-line
        $client_side_info_for_prop = $component->getComponentSource()
          ->getClientSideInfo($component)['propSources'][$sdc_prop_name];

        // The prop might be optional without a default value.
        if (!array_key_exists('default_values', $client_side_info_for_prop)) {
          continue;
        }

        $explicit_inputs[$sdc_prop_name] = $client_side_info_for_prop['default_values']['resolved'];
        continue;
      }

      $explicit_inputs[$sdc_prop_name] = StaticPropSource::parse([
        'sourceType' => 'static:field_item:' . $prop_field_definition['field_type'],
        'value' => $prop_field_definition['default_value'],
        'expression' => $prop_field_definition['expression'],
        'sourceTypeSettings' => [
          'cardinality' => $prop_field_definition['cardinality'] ?? 1,
          'storage' => $prop_field_definition['field_storage_settings'] ?? [],
          'instance' => $prop_field_definition['field_instance_settings'] ?? [],
        ],
      ])
        // Static prop sources can be evaluated without a host entity.
        ->evaluate(NULL, is_required: TRUE);
    }
    return [GeneratedFieldExplicitInputUxComponentSourceBase::EXPLICIT_INPUT_NAME => $explicit_inputs];
  }

  /**
   * Constructs the component tree to use for testing crash resistance.
   *
   * Renders the potentially crashing component:
   * - nested (not in the root level), to be able to assert that a parent
   *   component instance still renders
   * - with a component instance in an adjacent slot
   * - with a component instance both immediately before and after it
   *
   * The containing component is always the "two-column" SDC. All the other non-
   * crash component instances are the "Druplicon" SDCs.
   * The use of SDCs does not make this dummy component tree SDC-specific,
   * because the crashing component instance will be provided by the tested
   * ComponentSource plugin. Not every ComponentSource plugin supports slots.
   *
   * In other words: if there's 3 Druplicons detected, then all is good!
   *
   * @return \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
   */
  protected function generateCrashTestDummyComponentTree(string $component_id, array $inputs, bool $assertCount = TRUE): ComponentTreeItemList {
    if ($assertCount) {
      $this->assertCount($this->expectedDefaultComponentInstallCount, $this->componentStorage->loadMultiple());
      $this->generateComponentConfig();
    }

    $field_item = $this->createDanglingComponentTreeItemList();
    $field_item->setValue([
      [
        'uuid' => '38b79bf8-53d0-4307-b9ef-221c6a63023a',
        'component_id' => 'sdc.canvas_test_sdc.two_column',
        'inputs' => [
          'width' => 33,
        ],
      ],
      [
        // Before crash component.
        'uuid' => 'ab72924b-a1f6-4b07-a0e7-6a3d3b03d8f7',
        'component_id' => 'sdc.canvas_test_sdc.druplicon',
        'inputs' => [],
        'parent_uuid' => '38b79bf8-53d0-4307-b9ef-221c6a63023a',
        'slot' => 'column_one',
      ],
      [
        // @see https://en.wikipedia.org/wiki/Crash_test_dummy
        'uuid' => self::UUID_CRASH_TEST_DUMMY,
        'component_id' => $component_id,
        'inputs' => $inputs,
        'parent_uuid' => '38b79bf8-53d0-4307-b9ef-221c6a63023a',
        'slot' => 'column_one',
      ],
      [
        'uuid' => 'c3a4f459-7a8d-4dcd-88f7-ea353c9ec99a',
        'component_id' => 'sdc.canvas_test_sdc.druplicon',
        'inputs' => [],
        'parent_uuid' => '38b79bf8-53d0-4307-b9ef-221c6a63023a',
        'slot' => 'column_one',
      ],
      [
        'uuid' => 'c16945de-c27b-463c-89a9-0b79af684c0a',
        'component_id' => 'sdc.canvas_test_sdc.druplicon',
        'inputs' => [],
        'parent_uuid' => '38b79bf8-53d0-4307-b9ef-221c6a63023a',
        'slot' => 'column_two',
      ],
    ]);
    return $field_item;
  }

  /**
   * @dataProvider providerRenderComponentFailure
   *
   * @phpstan-param array{'class': string, 'message': string}|NULL $expected_exception
   */
  public function testRenderComponentFailure(string $component_id, array $inputs, array $expected_validation_errors, ?array $expected_exception, ?string $expected_output_selector): void {
    $component_tree = $this->generateCrashTestDummyComponentTree($component_id, $inputs);
    // Child implementations of ::generateCrashTestDummyComponentTree may
    // install additional modules, which will rebuild the container. So we add
    // the logger afterward.
    $this->container->get('logger.factory')->addLogger($this);

    // Unless explicitly expected to be invalid, inputs should be valid.
    $this->assertSame($expected_validation_errors, $this->violationsToArray($component_tree->validate()), 'Unrealistic test case encountered: it must still represent a valid component tree!');
    $page = Page::create([
      'title' => 'A page',
    ]);
    $exception_output = [
      // When preview is TRUE, should refer user to logs.
      'Component failed to render, check logs for more detail.' => TRUE,
      // When preview is FALSE, should show a more user-friendly message.
      'Oops, something went wrong! Site admins have been notified.' => FALSE,
    ];
    foreach ($exception_output as $displayedMessage => $isPreview) {
      $this->logMessages = [];
      // Make sure we don't get incremented IDs when rendering blocks.
      Html::resetSeenIds();
      $build = $component_tree->toRenderable($page, $isPreview);
      if (is_array($expected_exception)) {
        $crawler = $this->crawlerForRenderArray($build);
        self::assertCount(1, $this->logMessages, \implode(',', $this->logMessages));
        $message = \reset($this->logMessages);
        \assert(\is_string($message));
        self::assertStringContainsString($expected_exception['message'], $message);
        self::assertStringContainsString('Page A page (-)', $message);
        self::assertStringContainsString($expected_exception['class'], $message);
        self::assertCount(1, $crawler->filter(\sprintf('[data-component-uuid="%s"]:contains("%s")', self::UUID_CRASH_TEST_DUMMY, $displayedMessage)));
      }
      else {
        $crawler = self::assertRenderArrayMatchesSelectors($build, [$expected_output_selector]);
        \assert(!\is_null($crawler));
        // All 3 surrounding Druplicons must also be present, as proof
        // that any problem remains isolated!
        self::assertCount(3, $crawler->filter('svg title:contains("Druplicon")'));
      }
    }
  }

  protected function assertRenderArrayMatchesSelectors(array $build, array $selectors): ?Crawler {
    if ([] === $selectors) {
      self::assertSame('', (string) $this->renderer->renderInIsolation($build));
      return NULL;
    }
    $crawler = $this->crawlerForRenderArray($build);
    foreach ($selectors as $selector) {
      self::assertGreaterThanOrEqual(
        1,
        $crawler->filter($selector)->count(),
        "Failed finding selector '$selector'"
      );
    }
    return $crawler;
  }

  abstract public static function providerRenderComponentFailure(): \Generator;

  /**
   * @param array<ComponentConfigEntityId> $component_ids
   *   The component IDs to test.
   *
   * @covers ::getClientSideInfo()
   * @depends testDiscovery
   */
  public function testGetClientSideInfo(array $component_ids): void {
    $expected_client_side_info = static::getExpectedClientSideInfo();
    $actual_client_side_info = $this->callSourceMethodForEach('getClientSideInfo', $component_ids);

    // Test `build` using `expected_output_selectors`.
    foreach ($component_ids as $component_id) {
      if (!array_key_exists($component_id, $expected_client_side_info)) {
        throw new \OutOfRangeException(sprintf('Test expectations missing for %s.', $component_id));
      }
      $expected_output_selectors = $expected_client_side_info[$component_id]['expected_output_selectors'];
      unset($expected_client_side_info[$component_id]['expected_output_selectors']);
      $build = $actual_client_side_info[$component_id]['build'];
      unset($actual_client_side_info[$component_id]['build']);
      $this->assertRenderArrayMatchesSelectors($build, $expected_output_selectors);
    }

    // Test all other expected client-side info.
    self::assertSame($expected_client_side_info, $actual_client_side_info);
  }

  /**
   * Return the associative array of the expected build on each component.
   */
  abstract public static function getExpectedClientSideInfo(): array;

  /**
   * Build and save a component that can be used for testing fallback behavior.
   *
   * @return \Drupal\canvas\Entity\ComponentInterface
   */
  abstract protected function createAndSaveInUseComponentForFallbackTesting(): ComponentInterface;

  /**
   * Build and save a component that is not in use for testing fallback behavior.
   *
   * @return \Drupal\canvas\Entity\ComponentInterface
   */
  abstract protected function createAndSaveUnusedComponentForFallbackTesting(): ComponentInterface;

  /**
   * Delete config that will cause a fallback for the given components.
   */
  abstract protected function deleteConfigAndTriggerComponentFallback(ComponentInterface $used_component, ComponentInterface $unused_component): void;

  /**
   * Build and save a used component for testing uninstall validation.
   *
   * @return \Drupal\canvas\Entity\ComponentInterface
   */
  abstract protected function createAndSaveInUseComponentForUninstallValidationTesting(): ComponentInterface;

  /**
   * Build and save an unused component for testing uninstall validation.
   *
   * @return \Drupal\canvas\Entity\ComponentInterface
   */
  abstract protected function createAndSaveUnusedComponentForUninstallValidationTesting(): ComponentInterface;

  /**
   * Perform an action that will cause a component to recover from the fallback.
   *
   * @param \Drupal\canvas\Entity\ComponentInterface $component
   */
  abstract protected function recoverComponentFallback(ComponentInterface $component): void;

  /**
   * Return a module machine name that should not be able to be uninstalled.
   *
   * @return string
   */
  abstract protected function getNotAllowedModuleForUninstallValidatorTesting(): string;

  /**
   * Return a module machine name that should be able to be uninstalled.
   *
   * @return string
   */
  abstract protected function getAllowedModuleForUninstallValidatorTesting(): string;

  protected static function getPropsForComponentFallbackTesting(): array {
    return [];
  }

  protected static function getPropsForUninstallValidationTesting(): array {
    return [];
  }

  public function testFallback(): void {
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $this->generateComponentConfig();
    $used_component = $this->createAndSaveInUseComponentForFallbackTesting();
    $unused_component = $this->createAndSaveUnusedComponentForFallbackTesting();
    $component_label = $used_component->label();
    $source = $used_component->getComponentSource();
    $slots = [];
    if ($source instanceof ComponentSourceWithSlotsInterface) {
      $slots = \array_keys($source->getSlotDefinitions());
    }

    $entity = Page::create([
      'title' => $this->randomMachineName(),
      'components' => self::generateFallbackOrUninstallValidationComponentTree($used_component, $slots, static::getPropsForComponentFallbackTesting()),
    ]);
    // Save this so the usage can be queried.
    $entity->save();
    $renderable = $entity->getComponentTree()->toRenderable($entity, TRUE);
    $out = $this->crawlerForRenderArray($renderable);
    // Should be no fallback container.
    self::assertCount(0, $out->filter('[data-fallback]'));
    foreach ($slots as $slot) {
      // Children should render in the slots.
      self::assertCount(1, $out->filter(\sprintf('h1:contains("This is %s")', $slot)));
    }

    // Trigger an action that causes the components to perform
    // ::onDependencyRemoval and update its source plugin to use the fallback.
    $this->deleteConfigAndTriggerComponentFallback($used_component, $unused_component);
    $component_storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage(Component::ENTITY_TYPE_ID);
    self::assertNotNull($used_component->id());
    $used_component = $component_storage->loadUnchanged($used_component->id());
    \assert($used_component instanceof ComponentInterface);
    // Assert that the component has the same label, despite being dropped back
    // to a fallback.
    self::assertEquals($component_label, $used_component->label());
    self::assertFalse($used_component->status());
    // Assert that the component without any usage was cascade-deleted.
    self::assertNotNull($unused_component->id());
    self::assertNull($component_storage->loadUnchanged($unused_component->id()));
    // Assert that we can still render the fallback component and any children
    // in its slots.
    $renderable = $entity->getComponentTree()->toRenderable($entity, TRUE);
    $out = $this->crawlerForRenderArray($renderable);
    // Should be a fallback container.
    self::assertGreaterThanOrEqual(1, $out->filter('[data-fallback]')->count());
    foreach ($slots as $slot) {
      // Children should still render in the slots even though it is a fallback.
      self::assertCount(1, $out->filter(\sprintf('h1:contains("This is %s")', $slot)));
    }
    // We should also have the HTML comments that allow overlays to work.
    $html = \trim(\preg_replace('/\s+/', ' ', $out->html()) ?: '');
    foreach ($slots as $slot_name) {
      self::assertMatchesRegularExpression(sprintf('/<!-- canvas-slot-start-%s\/%s -->/', self::UUID_FALLBACK_ROOT, $slot_name), $html);
      self::assertMatchesRegularExpression(sprintf('/canvas-slot-end-(.*)\/%s -->/', $slot_name), $html);
    }

    if (static::class === BlockComponentTest::class) {
      // @todo Update Component entities with BlockComponent source plugin: https://drupal.org/i/3484682
      $this->markTestIncomplete('Block components do not yet update component config entities');
    }
    // Now perform an action that causes the component to recover from the
    // fallback.
    $this->recoverComponentFallback($used_component);
    $renderable = $entity->getComponentTree()->toRenderable($entity, TRUE);
    $out = $this->crawlerForRenderArray($renderable);
    // Should be no fallback container.
    self::assertCount(0, $out->filter('[data-fallback]'));
    foreach ($slots as $slot) {
      // Children should still render in the slots.
      self::assertCount(1, $out->filter(\sprintf('h1:contains("This is %s")', $slot)));
    }
  }

  private static function generateFallbackOrUninstallValidationComponentTree(ComponentInterface $component, array $slots, array $inputs): array {
    $items = [
      // Place the component that will become a fallback in the items.
      [
        'uuid' => self::UUID_FALLBACK_ROOT,
        'component_id' => $component->id(),
        'inputs' => $inputs,
      ],
    ];
    // Ensure we have something in each slot. When we trigger the conditions
    // that result in the component switching to use the 'fallback' plugin, we
    // want to ensure that any components placed in slots as children continue
    // to render.
    foreach ($slots as $slot) {
      // Generate a unique ID for each child component.
      $uuid = \Drupal::service(UuidInterface::class)->generate();
      // And place it inside the parent slot.
      $items[] = [
        'parent_uuid' => self::UUID_FALLBACK_ROOT,
        'uuid' => $uuid,
        'slot' => $slot,
        'component' => 'sdc.canvas_test_sdc.heading',
        'inputs' => [
          // Give it some inputs we can assert still exist when the fallback
          // conditions are triggered.
          'text' => [
            'sourceType' => 'static:field_item:string',
            'value' => \sprintf('This is %s', $slot),
            'expression' => 'ℹ︎string␟value',
          ],
          'element' => [
            'sourceType' => 'static:field_item:list_string',
            'value' => 'h1',
            'expression' => 'ℹ︎list_string␟value',
          ],
        ],
      ];
    }
    // Return component values.
    return $items;
  }

  public function testUninstallValidator(): void {
    // Setup some content with this component source plugin in use.
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $this->generateComponentConfig();
    $used_component = $this->createAndSaveInUseComponentForUninstallValidationTesting();
    $unused_component = $this->createAndSaveUnusedComponentForUninstallValidationTesting();
    $component_label = $used_component->label();
    $source = $used_component->getComponentSource();
    $slots = [];
    if ($source instanceof ComponentSourceWithSlotsInterface) {
      $slots = \array_keys($source->getSlotDefinitions());
    }

    $entity = Page::create([
      'title' => $this->randomMachineName(),
      'components' => self::generateFallbackOrUninstallValidationComponentTree($used_component, $slots, static::getPropsForUninstallValidationTesting()),
    ]);
    // Save this so the usage can be queried.
    $entity->save();

    $this->assertUninstallFailureReasons([
      (string) new TranslatableMarkup(
        'Is required by the %component component, that is in use in the 1 content entity - <a href=":url">View usage</a>',
        [
          '%component' => $component_label,
          ':url' => Url::fromRoute('entity.component.audit', ['component' => $used_component->id()])->toString(),
        ],
      ),
    ], modules: [$this->getNotAllowedModuleForUninstallValidatorTesting()]);

    // Should be no issue uninstalling this module.
    $this->assertUninstallFailureReasons([], modules: [$this->getAllowedModuleForUninstallValidatorTesting()]);

    $component_storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage(Component::ENTITY_TYPE_ID);
    // Assert that the component without any usage was cascade-deleted.
    self::assertNotNull($unused_component->id());
    self::assertNull($component_storage->loadUnchanged($unused_component->id()));
  }

}
