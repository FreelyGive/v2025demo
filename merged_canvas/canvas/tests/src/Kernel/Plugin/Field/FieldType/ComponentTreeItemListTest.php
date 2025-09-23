<?php

declare(strict_types=1);

// cspell:ignore vlaquxuup
namespace Drupal\Tests\canvas\Kernel\Plugin\Field\FieldType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Element\RenderSafeComponentContainer;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Exception\SubtreeInjectionException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\canvas\Render\ImportMapResponseAttachmentsProcessor;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\CacheBustingTrait;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\CreateTestJsComponentTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * @coversDefaultClass \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
 * @group canvas
 */
class ComponentTreeItemListTest extends KernelTestBase {

  use ConstraintViolationsTestTrait;
  use CreateTestJsComponentTrait;
  use GenerateComponentConfigTrait;
  use CiModulePathTrait;
  use UserCreationTrait;
  use ComponentTreeItemListInstantiatorTrait;
  use CacheBustingTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'canvas_test_sdc',
    'block',
    // Canvas's dependencies (modules providing field types + widgets).
    'datetime',
    'file',
    'image',
    'media',
    'options',
    'path',
    'link',
    'system',
    'user',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')
      ->set('name', 'Canvas Test Site')
      ->set('slogan', 'Drupal Canvas Test Site')
      ->save();
    $this->generateComponentConfig();
    $this->createMyCtaComponentFromSdc();
    $this->createMyCtaAutoSaveComponentFromSdc();
  }

  /**
   * @covers ::getHydratedTree()
   * @covers ::toRenderable()
   * @dataProvider provider
   */
  public function testHydrationAndRendering(array $value, array $expected_value, array $expected_renderable, string $expected_html, array $expected_cache_tags, bool $isPreview): void {
    // We need to force the cache busting query to ensure we use it correctly.
    $this->setCacheBustingQueryString($this->container, '2.1.0-alpha3');

    $typed_data_manager = $this->container->get(TypedDataManagerInterface::class);
    $list_definition = $typed_data_manager->createListDataDefinition('field_item:component_tree');
    \assert(\method_exists($list_definition, 'setCardinality'));
    $list_definition->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $item_list = $typed_data_manager->createInstance('list', [
      'name' => NULL,
      'parent' => NULL,
      'data_definition' => $list_definition,
    ]);
    assert($item_list instanceof ComponentTreeItemList);
    $item_list->setValue($value);

    // Every test case must be valid.
    $violations = $item_list->validate();
    $this->assertSame([], self::violationsToArray($violations));

    // Assert that the corresponding hydrated component tree is valid, in both
    // representations:
    // 1. raw (`::getValue()`)
    // 2. Drupal renderable (`::toRenderable()`)
    // 3. the resulting HTML markup.assert($node->field_canvas_test[0] instanceof ComponentTreeItem);
    $hydrated_value = \Closure::bind(function () {
      return $this->getHydratedTree();
    }, $item_list, $item_list)();
    $this->assertSame($expected_value, $hydrated_value->getTree());
    $page = Page::create([
      'title' => 'A page',
    ]);
    $renderable = $item_list->toRenderable($page, $isPreview);
    $vfs_site_base_url = base_path() . $this->siteDirectory;
    \array_walk_recursive($renderable, function (mixed &$value) use ($vfs_site_base_url) {
      if (\is_string($value)) {
        $value = \str_replace($vfs_site_base_url, '::SITE_DIR_BASE_URL::', $value);
      }
    });
    $this->assertEquals($expected_renderable, $renderable);

    $html = (string) $this->container->get(RendererInterface::class)->renderInIsolation($renderable);
    // Strip trailing whitespace to make heredocs easier to write.
    $html = preg_replace('/ +$/m', '', $html);
    $this->assertIsString($html);
    // Make it easier to write expectations containing root-relative URLs
    // pointing to Canvas-owned assets.
    $canvas_dir_root_relative_url = base_path() . $this->getModulePath('canvas');
    $html = str_replace($canvas_dir_root_relative_url, '::CANVAS_DIR_BASE_URL::', $html);
    // Make it easier to write expectations containing root-relative URLs
    // pointing somewhere into the site-specific directory.
    $html = str_replace($vfs_site_base_url, '::SITE_DIR_BASE_URL::', $html);
    $this->assertSame($expected_html, $html);
    $this->assertSame($expected_cache_tags, array_values(CacheableMetadata::createFromRenderArray($renderable)->getCacheTags()));
  }

  public static function modifyExpectationFromLiveToPreview(array $expectation, bool $is_preview): array {
    \array_walk_recursive($expectation['expected_renderable'], function (mixed &$value, mixed $key) use ($is_preview) {
      if ($key === '#is_preview' || $key === 'canvas_is_preview') {
        $value = $is_preview;
      }
      if (is_string($value) && str_starts_with($value, 'canvas/')) {
        $value .= '.draft';
      }
    });

    // Add slot placeholders to the expected renderable array.
    $expectation['expected_renderable'] = self::addSlotPlaceholders($expectation['expected_renderable']);
    // Update expected HTML to only show empty slot placeholder.
    $expectation['expected_html'] = preg_replace('#(<div class="canvas--slot-empty-placeholder">.*?</div>)(.*?)(?=<!--)#', '<div class="canvas--slot-empty-placeholder"></div>', $expectation['expected_html']);

    return $expectation;
  }

  public static function overwriteRenderableExpectations(array $expectation, array $overwrites): array {
    foreach ($overwrites as ['parents' => $parents, 'value' => $value]) {
      NestedArray::setValue($expectation['expected_renderable'], $parents, $value);
    }
    return $expectation;
  }

  public static function addSlotPlaceholders(mixed $expected_renderable): array {
    $expectation = [];

    foreach ($expected_renderable as $key => $value) {
      if (is_array($value)) {
        $value = self::addSlotPlaceholders($value);
      }

      if ($key === '#slots') {
        if (is_array($value)) {
          foreach ($value as $slot_key => $slot_value) {
            if (isset($slot_value["#plain_text"]) || isset($slot_value["#markup"])) {
              $expectation[$key][$slot_key] = [
                '#markup' => Markup::create('<div class="canvas--slot-empty-placeholder"></div>'),
              ];
            }
            else {
              $expectation[$key][$slot_key] = $slot_value;
            }
          }
        }
      }
      else {
        $expectation[$key] = $value;
      }
    }
    return $expectation;
  }

  public static function removePrefixSuffixKeysRecursive(mixed $expected_renderable): array {
    $expectation = [];

    foreach ($expected_renderable as $key => $value) {
      if ($key === '#prefix' || $key === '#suffix') {
        continue;
      }

      if (is_array($value)) {
        $value = self::removePrefixSuffixKeysRecursive($value);
      }
      $expectation[$key] = $value;
    }

    return $expectation;
  }

  public static function removePrefixSuffix(array $expectation): array {
    // Remove the prefix and suffix from the expected renderable array.
    $expectation['expected_renderable'] = self::removePrefixSuffixKeysRecursive($expectation['expected_renderable']);
    // Remove all html comments & empty slot placeholders from expected html.
    $expectation['expected_html'] = preg_replace(['/<!--(.*?)-->/', '#<div class="canvas--slot-empty-placeholder"></div>#'], '', $expectation['expected_html']);
    // Remove extra tabbing so expectation matches actual output.
    $expectation['expected_html'] = preg_replace('/[ \t]+\n/', "\n", $expectation['expected_html']);

    return $expectation;
  }

  public static function provider(): \Generator {
    $generate_static_prop_source = function (string $label): string {
      return "Hello, $label!";
    };
    $empty_component_tree = [
      'value' => [],
      'expected_value' => [
        ComponentTreeItemList::ROOT_UUID => [],
      ],
      'expected_renderable' => [],
      'expected_html' => '',
      'expected_cache_tags' => [],
    ];
    yield 'empty component tree' => [...$empty_component_tree, 'isPreview' => FALSE];
    yield 'empty component tree in preview' => [...$empty_component_tree, 'isPreview' => TRUE];

    $component_tree_with_single_component_with_unpopulated_slots = [
      'value' => [
        [
          'uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'inputs' => [
            'heading' => $generate_static_prop_source('world'),
          ],
        ],
      ],
      'expected_value' => [
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            'component' => 'sdc.canvas_test_sdc.props-slots',
            'props' => ['heading' => 'Hello, world!'],
            'slots' => [
              // TRICKY: this is different from the *stored* representation of a
              // component tree (where empty slots must be omitted). Since this
              // is the *hydrated* representation of
              // component tree, each slot merits being explicitly present, and
              // list its default value.
              // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent::hydrateComponent()
              // @see \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator
              // @see \Drupal\Tests\canvas\Kernel\DataType\ComponentTreeStructureTest
              'the_body' => '<p>Example value for <strong>the_body</strong> slot in <strong>prop-slots</strong> component.</p>',
              'the_footer' => 'Example value for <strong>the_footer</strong>.',
              'the_colophon' => '',
            ],
          ],
        ],
      ],
      'expected_renderable' => [
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            '#type' => RenderSafeComponentContainer::PLUGIN_ID,
            '#component_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
            '#component_context' => 'Page A page (-)',
            '#is_preview' => FALSE,
            '#component' => [
              '#type' => 'component',
              '#cache' => [
                'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-slots'],
                'contexts' => [],
                'max-age' => Cache::PERMANENT,
              ],
              '#component' => 'canvas_test_sdc:props-slots',
              '#props' => [
                'heading' => 'Hello, world!',
                'canvas_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
                'canvas_slot_ids' => ['the_body', 'the_footer', 'the_colophon'],
                'canvas_is_preview' => FALSE,
              ],
              '#slots' => [
                'the_body' => [
                  // This string is the first example value for this slot.
                  '#markup' => '<p>Example value for <strong>the_body</strong> slot in <strong>prop-slots</strong> component.</p>',
                ],
                'the_footer' => [
                  // This string is the first example value for this slot.
                  '#plain_text' => 'Example value for <strong>the_footer</strong>.',
                ],
                'the_colophon' => [
                  // This slot has no example value defined.
                  '#plain_text' => '',
                ],
              ],
              '#prefix' => Markup::create('<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 -->'),
              '#suffix' => Markup::create('<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->'),
              '#attached' => [
                'library' => [
                  'core/components.canvas_test_sdc--props-slots',
                ],
              ],
            ],
          ],
        ],
      ],
      'expected_html' => <<<HTML
<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 --><div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-41595148-e5c1-4873-b373-be3ae6e21340/heading -->Hello, world!<!-- canvas-prop-end-41595148-e5c1-4873-b373-be3ae6e21340/heading --></h1>
  <div class="component--props-slots--body">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_body --><div class="canvas--slot-empty-placeholder"></div><p>Example value for <strong>the_body</strong> slot in <strong>prop-slots</strong> component.</p><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_body -->
    </div>
  <div class="component--props-slots--footer">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_footer --><div class="canvas--slot-empty-placeholder"></div>Example value for &lt;strong&gt;the_footer&lt;/strong&gt;.<!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_footer -->
    </div>
  <div class="component--props-slots--colophon">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_colophon --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_colophon -->
    </div>
</div>
<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->
HTML,
      'expected_cache_tags' => [
        'config:canvas.component.sdc.canvas_test_sdc.props-slots',
      ],
    ];

    yield 'component tree with a single component that has unpopulated slots with default values' => [...self::removePrefixSuffix($component_tree_with_single_component_with_unpopulated_slots), 'isPreview' => FALSE];
    yield 'component tree with a single component that has unpopulated slots with default values in preview' => [...self::modifyExpectationFromLiveToPreview($component_tree_with_single_component_with_unpopulated_slots, TRUE), 'isPreview' => TRUE];

    $component_tree_with_single_block_component = [
      'value' => [
        [
          'uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
          'component_id' => 'block.system_branding_block',
          'inputs' => [
            'label' => '',
            'label_display' => '0',
            'use_site_logo' => TRUE,
            'use_site_name' => TRUE,
            'use_site_slogan' => TRUE,
          ],
        ],
      ],
      'expected_value' => [
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            'component' => 'block.system_branding_block',
            'settings' => [
              'label' => '',
              'label_display' => '0',
              'use_site_logo' => TRUE,
              'use_site_name' => TRUE,
              'use_site_slogan' => TRUE,
            ],
          ],
        ],
      ],
      'expected_renderable' => [
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            '#type' => RenderSafeComponentContainer::PLUGIN_ID,
            '#component_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
            '#component_context' => 'Page A page (-)',
            '#is_preview' => FALSE,
            '#component' => [
              '#access' => new AccessResultAllowed(),
              '#theme' => 'block',
              '#configuration' => [
                'id' => 'system_branding_block',
                'label' => '',
                'label_display' => '0',
                'provider' => 'system',
                'use_site_logo' => TRUE,
                'use_site_name' => TRUE,
                'use_site_slogan' => TRUE,
                'local_source_id' => 'system_branding_block',
                'default_settings' => [
                  'id' => 'system_branding_block',
                  'label' => 'Site branding',
                  'label_display' => '0',
                  'provider' => 'system',
                  'use_site_logo' => TRUE,
                  'use_site_name' => TRUE,
                  'use_site_slogan' => TRUE,
                ],
              ],
              '#plugin_id' => 'system_branding_block',
              '#base_plugin_id' => 'system_branding_block',
              '#derivative_plugin_id' => NULL,
              '#id' => '41595148-e5c1-4873-b373-be3ae6e21340',
              'content' => [
                'site_logo' => [
                  '#theme' => "image",
                  '#uri' => NULL,
                  '#alt' => 'Home',
                  '#access' => TRUE,
                ],
                'site_name' => [
                  '#markup' => 'Canvas Test Site',
                  '#access' => TRUE,
                ],
                'site_slogan' => [
                  '#markup' => 'Drupal Canvas Test Site',
                  '#access' => TRUE,
                ],
              ],
              '#cache' => [
                'tags' => [
                  'config:system.site',
                  'config:canvas.component.block.system_branding_block',
                ],
                'contexts' => [],
                'max-age' => Cache::PERMANENT,
              ],
              '#prefix' => '',
              '#suffix' => '',
            ],
          ],
        ],
      ],
      'expected_html' => <<<HTML
<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 --><div id="block-41595148-e5c1-4873-b373-be3ae6e21340">


          <a href="/" rel="home">Canvas Test Site</a>
    Drupal Canvas Test Site
</div>
<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->
HTML,
      'expected_cache_tags' => [
        'config:system.site',
        'config:canvas.component.block.system_branding_block',
      ],
    ];
    yield 'component tree with a single block component' => [...self::removePrefixSuffix($component_tree_with_single_block_component), 'isPreview' => FALSE];
    yield 'component tree with a single block component in preview' => [...self::modifyExpectationFromLiveToPreview($component_tree_with_single_block_component, TRUE), 'isPreview' => TRUE];

    $simplest_component_tree_without_nesting = [
      'value' => [
        [
          'uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'inputs' => [
            'heading' => $generate_static_prop_source('world'),
          ],
        ],
        [
          'uuid' => 'fcf67861-87da-45e5-916b-31f5b74be747',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'inputs' => [
            'heading' => $generate_static_prop_source('another world'),
          ],
        ],
      ],
      'expected_value' => [
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            'component' => 'sdc.canvas_test_sdc.props-no-slots',
            'props' => ['heading' => 'Hello, world!'],
          ],
          'fcf67861-87da-45e5-916b-31f5b74be747' => [
            'component' => 'sdc.canvas_test_sdc.props-no-slots',
            'props' => ['heading' => 'Hello, another world!'],
          ],
        ],
      ],
      'expected_renderable' => [
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            '#type' => RenderSafeComponentContainer::PLUGIN_ID,
            '#component_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
            '#component_context' => 'Page A page (-)',
            '#is_preview' => FALSE,
            '#component' => [
              '#type' => 'component',
              '#cache' => [
                'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-no-slots'],
                'contexts' => [],
                'max-age' => Cache::PERMANENT,
              ],
              '#component' => 'canvas_test_sdc:props-no-slots',
              '#props' => [
                'heading' => 'Hello, world!',
                'canvas_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
                'canvas_slot_ids' => [],
                'canvas_is_preview' => FALSE,
              ],
              '#prefix' => Markup::create('<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 -->'),
              '#suffix' => Markup::create('<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->'),
              '#attached' => [
                'library' => [
                  'core/components.canvas_test_sdc--props-no-slots',
                ],
              ],
            ],
          ],
          'fcf67861-87da-45e5-916b-31f5b74be747' => [
            '#type' => RenderSafeComponentContainer::PLUGIN_ID,
            '#component_uuid' => 'fcf67861-87da-45e5-916b-31f5b74be747',
            '#component_context' => 'Page A page (-)',
            '#is_preview' => FALSE,
            '#component' => [
              '#type' => 'component',
              '#cache' => [
                'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-no-slots'],
                'contexts' => [],
                'max-age' => Cache::PERMANENT,
              ],
              '#component' => 'canvas_test_sdc:props-no-slots',
              '#props' => [
                'heading' => 'Hello, another world!',
                'canvas_uuid' => 'fcf67861-87da-45e5-916b-31f5b74be747',
                'canvas_slot_ids' => [],
                'canvas_is_preview' => FALSE,
              ],
              '#prefix' => Markup::create('<!-- canvas-start-fcf67861-87da-45e5-916b-31f5b74be747 -->'),
              '#suffix' => Markup::create('<!-- canvas-end-fcf67861-87da-45e5-916b-31f5b74be747 -->'),
              '#attached' => [
                'library' => [
                  'core/components.canvas_test_sdc--props-no-slots',
                ],
              ],
            ],
          ],
        ],
      ],
      'expected_html' => <<<HTML
<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 --><div  data-component-id="canvas_test_sdc:props-no-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-41595148-e5c1-4873-b373-be3ae6e21340/heading -->Hello, world!<!-- canvas-prop-end-41595148-e5c1-4873-b373-be3ae6e21340/heading --></h1>
</div>
<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 --><!-- canvas-start-fcf67861-87da-45e5-916b-31f5b74be747 --><div  data-component-id="canvas_test_sdc:props-no-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-fcf67861-87da-45e5-916b-31f5b74be747/heading -->Hello, another world!<!-- canvas-prop-end-fcf67861-87da-45e5-916b-31f5b74be747/heading --></h1>
</div>
<!-- canvas-end-fcf67861-87da-45e5-916b-31f5b74be747 -->
HTML,
      'expected_cache_tags' => [
        'config:canvas.component.sdc.canvas_test_sdc.props-no-slots',
      ],
    ];
    yield 'simplest component tree without nesting' => [...self::removePrefixSuffix($simplest_component_tree_without_nesting), 'isPreview' => FALSE];
    yield 'simplest component tree without nesting in preview' => [...self::modifyExpectationFromLiveToPreview($simplest_component_tree_without_nesting, TRUE), 'isPreview' => TRUE];

    $simplest_component_tree_with_nesting = [
      'value' => [
        [
          'uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'inputs' => [
            'heading' => $generate_static_prop_source('world'),
          ],
        ],
        [
          'uuid' => '3b305d86-86a7-4684-8664-7ef1fc2be070',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'parent_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
          'slot' => 'the_body',
          'inputs' => [
            'heading' => $generate_static_prop_source('from a slot'),
          ],
        ],
      ],
      'expected_value' => [
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            'component' => 'sdc.canvas_test_sdc.props-slots',
            'props' => ['heading' => 'Hello, world!'],
            'slots' => [
              'the_footer' => 'Example value for <strong>the_footer</strong>.',
              'the_colophon' => '',
              'the_body' => [
                '3b305d86-86a7-4684-8664-7ef1fc2be070' => [
                  'component' => 'sdc.canvas_test_sdc.props-no-slots',
                  'props' => ['heading' => 'Hello, from a slot!'],
                ],
              ],
            ],
          ],
        ],
      ],
      'expected_renderable' => [
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            '#type' => RenderSafeComponentContainer::PLUGIN_ID,
            '#component_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
            '#component_context' => 'Page A page (-)',
            '#is_preview' => FALSE,
            '#component' => [
              '#type' => 'component',
              '#cache' => [
                'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-slots'],
                'contexts' => [],
                'max-age' => Cache::PERMANENT,
              ],
              '#component' => 'canvas_test_sdc:props-slots',
              '#props' => [
                'heading' => 'Hello, world!',
                'canvas_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
                'canvas_slot_ids' => ['the_body', 'the_footer', 'the_colophon'],
                'canvas_is_preview' => FALSE,
              ],
              '#slots' => [
                'the_footer' => [
                  '#plain_text' => 'Example value for <strong>the_footer</strong>.',
                ],
                'the_colophon' => ['#plain_text' => ''],
                'the_body' => [
                  '3b305d86-86a7-4684-8664-7ef1fc2be070' => [
                    '#type' => RenderSafeComponentContainer::PLUGIN_ID,
                    '#component_uuid' => '3b305d86-86a7-4684-8664-7ef1fc2be070',
                    '#component_context' => 'Page A page (-)',
                    '#is_preview' => FALSE,
                    '#component' => [
                      '#type' => 'component',
                      '#cache' => [
                        'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-no-slots'],
                        'contexts' => [],
                        'max-age' => Cache::PERMANENT,
                      ],
                      '#component' => 'canvas_test_sdc:props-no-slots',
                      '#props' => [
                        'heading' => 'Hello, from a slot!',
                        'canvas_uuid' => '3b305d86-86a7-4684-8664-7ef1fc2be070',
                        'canvas_slot_ids' => [],
                        'canvas_is_preview' => FALSE,
                      ],
                      '#prefix' => Markup::create('<!-- canvas-start-3b305d86-86a7-4684-8664-7ef1fc2be070 -->'),
                      '#suffix' => Markup::create('<!-- canvas-end-3b305d86-86a7-4684-8664-7ef1fc2be070 -->'),
                      '#attached' => [
                        'library' => [
                          'core/components.canvas_test_sdc--props-no-slots',
                        ],
                      ],
                    ],
                  ],
                ],
              ],
              '#prefix' => Markup::create('<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 -->'),
              '#suffix' => Markup::create('<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->'),
              '#attached' => [
                'library' => [
                  'core/components.canvas_test_sdc--props-slots',
                ],
              ],
            ],
          ],
        ],
      ],
      'expected_html' => <<<HTML
<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 --><div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-41595148-e5c1-4873-b373-be3ae6e21340/heading -->Hello, world!<!-- canvas-prop-end-41595148-e5c1-4873-b373-be3ae6e21340/heading --></h1>
  <div class="component--props-slots--body">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_body --><!-- canvas-start-3b305d86-86a7-4684-8664-7ef1fc2be070 --><div  data-component-id="canvas_test_sdc:props-no-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-3b305d86-86a7-4684-8664-7ef1fc2be070/heading -->Hello, from a slot!<!-- canvas-prop-end-3b305d86-86a7-4684-8664-7ef1fc2be070/heading --></h1>
</div>
<!-- canvas-end-3b305d86-86a7-4684-8664-7ef1fc2be070 --><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_body -->
    </div>
  <div class="component--props-slots--footer">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_footer --><div class="canvas--slot-empty-placeholder"></div>Example value for &lt;strong&gt;the_footer&lt;/strong&gt;.<!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_footer -->
    </div>
  <div class="component--props-slots--colophon">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_colophon --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_colophon -->
    </div>
</div>
<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->
HTML,
      'expected_cache_tags' => [
        'config:canvas.component.sdc.canvas_test_sdc.props-slots',
        'config:canvas.component.sdc.canvas_test_sdc.props-no-slots',
      ],
    ];
    yield 'simplest component tree with nesting' => [...self::removePrefixSuffix($simplest_component_tree_with_nesting), 'isPreview' => FALSE];
    yield 'simplest component tree with nesting in preview' => [...self::modifyExpectationFromLiveToPreview($simplest_component_tree_with_nesting, TRUE), 'isPreview' => TRUE];

    $path = self::getCiModulePath();
    $component_tree_with_complex_nesting = [
      'value' => [
        // Note how these are NOT sequentially ordered.
        [
          'uuid' => 'dfd2e899-6d88-46f8-b6aa-98929d1586dd',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'parent_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
          'slot' => 'the_body',
          'inputs' => ['heading' => $generate_static_prop_source('from slot level 1')],
        ],
        [
          'uuid' => '81c63cac-187d-4f05-8acc-1c38fb2489d3',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'parent_uuid' => 'e0b92f23-c177-4196-8fa4-3e837f99a357',
          'slot' => 'the_body',
          'inputs' => ['heading' => $generate_static_prop_source('from slot level 3')],
        ],
        [
          'uuid' => '68167e4a-9245-41be-b564-f1e1dcad1dec',
          'component_id' => 'block.system_branding_block',
          'parent_uuid' => 'e0b92f23-c177-4196-8fa4-3e837f99a357',
          'slot' => 'the_body',
          'inputs' => [
            'label' => '',
            'label_display' => '0',
            'use_site_logo' => TRUE,
            'use_site_name' => TRUE,
            'use_site_slogan' => TRUE,
          ],
        ],
        [
          'uuid' => '2f57ba57-f32a-4a7b-9896-9d1104b446f1',
          'component_id' => 'js.my-cta',
          'parent_uuid' => 'e0b92f23-c177-4196-8fa4-3e837f99a357',
          'slot' => 'the_body',
          'inputs' => [
            'text' => $generate_static_prop_source('from a "code component"'),
            'href' => 'https://example.com',
          ],
        ],
        [
          'uuid' => 'b4bc6c8f-66f7-458a-99a9-41c29b2801e7',
          'component_id' => 'js.my-cta-with-auto-save',
          'parent_uuid' => 'e0b92f23-c177-4196-8fa4-3e837f99a357',
          'slot' => 'the_body',
          'inputs' => [
            'text' => $generate_static_prop_source('from a "auto-save code component"'),
            'href' => 'https://example.com',
          ],
        ],
        [
          'uuid' => '9f09ecd8-ec65-408c-b5c8-ef036e6aeb97',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'parent_uuid' => 'e0b92f23-c177-4196-8fa4-3e837f99a357',
          'slot' => 'the_body',
          'inputs' => ['heading' => $generate_static_prop_source('from slot <LAST ONE>')],
        ],
        [
          'uuid' => 'e0b92f23-c177-4196-8fa4-3e837f99a357',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'parent_uuid' => 'dfd2e899-6d88-46f8-b6aa-98929d1586dd',
          'slot' => 'the_body',
          'inputs' => ['heading' => $generate_static_prop_source('from slot level 2')],
        ],
        [
          'uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'inputs' => [
            'heading' => $generate_static_prop_source('world'),
          ],
        ],
      ],
      'expected_value' => [
        // Note how these are sequentially ordered.
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            'component' => 'sdc.canvas_test_sdc.props-slots',
            'props' => ['heading' => 'Hello, world!'],
            'slots' => [
              'the_footer' => 'Example value for <strong>the_footer</strong>.',
              'the_colophon' => '',
              'the_body' => [
                'dfd2e899-6d88-46f8-b6aa-98929d1586dd' => [
                  'component' => 'sdc.canvas_test_sdc.props-slots',
                  'props' => ['heading' => 'Hello, from slot level 1!'],
                  'slots' => [
                    'the_footer' => 'Example value for <strong>the_footer</strong>.',
                    'the_colophon' => '',
                    'the_body' => [
                      'e0b92f23-c177-4196-8fa4-3e837f99a357' => [
                        'component' => 'sdc.canvas_test_sdc.props-slots',
                        'props' => ['heading' => 'Hello, from slot level 2!'],
                        'slots' => [
                          'the_footer' => 'Example value for <strong>the_footer</strong>.',
                          'the_colophon' => '',
                          'the_body' => [
                            '81c63cac-187d-4f05-8acc-1c38fb2489d3' => [
                              'component' => 'sdc.canvas_test_sdc.props-no-slots',
                              'props' => ['heading' => 'Hello, from slot level 3!'],
                            ],
                            '68167e4a-9245-41be-b564-f1e1dcad1dec' => [
                              'component' => 'block.system_branding_block',
                              'settings' => [
                                'label' => '',
                                'label_display' => '0',
                                'use_site_logo' => TRUE,
                                'use_site_name' => TRUE,
                                'use_site_slogan' => TRUE,
                              ],
                            ],
                            '2f57ba57-f32a-4a7b-9896-9d1104b446f1' => [
                              'component' => 'js.my-cta',
                              'props' => [
                                'text' => 'Hello, from a "code component"!',
                                'href' => 'https://example.com',
                              ],
                            ],
                            'b4bc6c8f-66f7-458a-99a9-41c29b2801e7' => [
                              'component' => 'js.my-cta-with-auto-save',
                              'props' => [
                                'text' => 'Hello, from a "auto-save code component"!',
                                'href' => 'https://example.com',
                              ],
                            ],
                            '9f09ecd8-ec65-408c-b5c8-ef036e6aeb97' => [
                              'component' => 'sdc.canvas_test_sdc.props-no-slots',
                              'props' => ['heading' => 'Hello, from slot <LAST ONE>!'],
                            ],
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'expected_renderable' => [
        // Note how these are sequentially ordered.
        ComponentTreeItemList::ROOT_UUID => [
          '41595148-e5c1-4873-b373-be3ae6e21340' => [
            '#type' => RenderSafeComponentContainer::PLUGIN_ID,
            '#component_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
            '#component_context' => 'Page A page (-)',
            '#is_preview' => FALSE,
            '#component' => [
              '#type' => 'component',
              '#cache' => [
                'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-slots'],
                'contexts' => [],
                'max-age' => Cache::PERMANENT,
              ],
              '#component' => 'canvas_test_sdc:props-slots',
              '#props' => [
                'heading' => 'Hello, world!',
                'canvas_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
                'canvas_slot_ids' => ['the_body', 'the_footer', 'the_colophon'],
                'canvas_is_preview' => FALSE,
              ],
              '#slots' => [
                'the_footer' => [
                  '#plain_text' => 'Example value for <strong>the_footer</strong>.',
                ],
                'the_colophon' => ['#plain_text' => ''],
                'the_body' => [
                  'dfd2e899-6d88-46f8-b6aa-98929d1586dd' => [
                    '#type' => RenderSafeComponentContainer::PLUGIN_ID,
                    '#component_uuid' => 'dfd2e899-6d88-46f8-b6aa-98929d1586dd',
                    '#component_context' => 'Page A page (-)',
                    '#is_preview' => FALSE,
                    '#component' => [
                      '#type' => 'component',
                      '#cache' => [
                        'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-slots'],
                        'contexts' => [],
                        'max-age' => Cache::PERMANENT,
                      ],
                      '#component' => 'canvas_test_sdc:props-slots',
                      '#props' => [
                        'heading' => 'Hello, from slot level 1!',
                        'canvas_uuid' => 'dfd2e899-6d88-46f8-b6aa-98929d1586dd',
                        'canvas_slot_ids' => ['the_body', 'the_footer', 'the_colophon'],
                        'canvas_is_preview' => FALSE,
                      ],
                      '#slots' => [
                        'the_footer' => [
                          // This string is the first example value for this slot.
                          '#plain_text' => 'Example value for <strong>the_footer</strong>.',
                        ],
                        'the_colophon' => [
                          // This slot has no example value defined.
                          '#plain_text' => '',
                        ],
                        'the_body' => [
                          'e0b92f23-c177-4196-8fa4-3e837f99a357' => [
                            '#type' => RenderSafeComponentContainer::PLUGIN_ID,
                            '#component_uuid' => 'e0b92f23-c177-4196-8fa4-3e837f99a357',
                            '#component_context' => 'Page A page (-)',
                            '#is_preview' => FALSE,
                            '#component' => [
                              '#type' => 'component',
                              '#cache' => [
                                'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-slots'],
                                'contexts' => [],
                                'max-age' => Cache::PERMANENT,
                              ],
                              '#component' => 'canvas_test_sdc:props-slots',
                              '#props' => [
                                'heading' => 'Hello, from slot level 2!',
                                'canvas_uuid' => 'e0b92f23-c177-4196-8fa4-3e837f99a357',
                                'canvas_slot_ids' => ['the_body', 'the_footer', 'the_colophon'],
                                'canvas_is_preview' => FALSE,
                              ],
                              '#slots' => [
                                'the_footer' => [
                                  '#plain_text' => 'Example value for <strong>the_footer</strong>.',
                                ],
                                'the_colophon' => ['#plain_text' => ''],
                                'the_body' => [
                                  '81c63cac-187d-4f05-8acc-1c38fb2489d3' => [
                                    '#type' => RenderSafeComponentContainer::PLUGIN_ID,
                                    '#component_uuid' => '81c63cac-187d-4f05-8acc-1c38fb2489d3',
                                    '#component_context' => 'Page A page (-)',
                                    '#is_preview' => FALSE,
                                    '#component' => [
                                      '#type' => 'component',
                                      '#cache' => [
                                        'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-no-slots'],
                                        'contexts' => [],
                                        'max-age' => Cache::PERMANENT,
                                      ],
                                      '#component' => 'canvas_test_sdc:props-no-slots',
                                      '#props' => [
                                        'heading' => 'Hello, from slot level 3!',
                                        'canvas_uuid' => '81c63cac-187d-4f05-8acc-1c38fb2489d3',
                                        'canvas_slot_ids' => [],
                                        'canvas_is_preview' => FALSE,
                                      ],
                                      '#prefix' => Markup::create('<!-- canvas-start-81c63cac-187d-4f05-8acc-1c38fb2489d3 -->'),
                                      '#suffix' => Markup::create('<!-- canvas-end-81c63cac-187d-4f05-8acc-1c38fb2489d3 -->'),
                                      '#attached' => [
                                        'library' => [
                                          'core/components.canvas_test_sdc--props-no-slots',
                                        ],
                                      ],
                                    ],
                                  ],
                                  '68167e4a-9245-41be-b564-f1e1dcad1dec' => [
                                    '#type' => RenderSafeComponentContainer::PLUGIN_ID,
                                    '#component_uuid' => '68167e4a-9245-41be-b564-f1e1dcad1dec',
                                    '#component_context' => 'Page A page (-)',
                                    '#is_preview' => FALSE,
                                    '#component' => [
                                      '#access' => new AccessResultAllowed(),
                                      '#theme' => 'block',
                                      '#configuration' => [
                                        'id' => 'system_branding_block',
                                        'label' => '',
                                        'label_display' => '0',
                                        'provider' => 'system',
                                        'use_site_logo' => TRUE,
                                        'use_site_name' => TRUE,
                                        'use_site_slogan' => TRUE,
                                        'local_source_id' => 'system_branding_block',
                                        'default_settings' => [
                                          'id' => 'system_branding_block',
                                          'label' => 'Site branding',
                                          'label_display' => '0',
                                          'provider' => 'system',
                                          'use_site_logo' => TRUE,
                                          'use_site_name' => TRUE,
                                          'use_site_slogan' => TRUE,
                                        ],
                                      ],
                                      '#plugin_id' => 'system_branding_block',
                                      '#base_plugin_id' => 'system_branding_block',
                                      '#derivative_plugin_id' => NULL,
                                      '#id' => '68167e4a-9245-41be-b564-f1e1dcad1dec',
                                      'content' => [
                                        'site_logo' => [
                                          '#theme' => 'image',
                                          '#uri' => NULL,
                                          '#alt' => 'Home',
                                          '#access' => TRUE,
                                        ],
                                        'site_name' => [
                                          '#markup' => 'Canvas Test Site',
                                          '#access' => TRUE,
                                        ],
                                        'site_slogan' => [
                                          '#markup' => 'Drupal Canvas Test Site',
                                          '#access' => TRUE,
                                        ],
                                      ],
                                      '#cache' => [
                                        'tags' => [
                                          'config:system.site',
                                          'config:canvas.component.block.system_branding_block',
                                        ],
                                        'contexts' => [],
                                        'max-age' => Cache::PERMANENT,
                                      ],
                                      '#prefix' => Markup::create('<!-- canvas-start-68167e4a-9245-41be-b564-f1e1dcad1dec -->'),
                                      '#suffix' => Markup::create('<!-- canvas-end-68167e4a-9245-41be-b564-f1e1dcad1dec -->'),
                                    ],
                                  ],
                                  '2f57ba57-f32a-4a7b-9896-9d1104b446f1' => [
                                    '#type' => RenderSafeComponentContainer::PLUGIN_ID,
                                    '#component_uuid' => '2f57ba57-f32a-4a7b-9896-9d1104b446f1',
                                    '#component_context' => 'Page A page (-)',
                                    '#is_preview' => FALSE,
                                    '#component' => [
                                      '#type' => 'astro_island',
                                      '#cache' => [
                                        'tags' => [
                                          'config:canvas.js_component.my-cta',
                                          'config:canvas.component.js.my-cta',
                                        ],
                                        'contexts' => [],
                                        'max-age' => Cache::PERMANENT,
                                      ],
                                      '#import_maps' => [
                                        ImportMapResponseAttachmentsProcessor::GLOBAL_IMPORTS => [
                                          'preact' => \sprintf('%s/ui/lib/astro-hydration/dist/preact.module.js?2.1.0-alpha3', $path),
                                          'preact/hooks' => \sprintf('%s/ui/lib/astro-hydration/dist/hooks.module.js?2.1.0-alpha3', $path),
                                          'react/jsx-runtime' => \sprintf('%s/ui/lib/astro-hydration/dist/jsx-runtime-default.js?2.1.0-alpha3', $path),
                                          'react' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $path),
                                          'react-dom' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $path),
                                          'react-dom/client' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $path),
                                          'clsx' => \sprintf('%s/ui/lib/astro-hydration/dist/clsx.js?2.1.0-alpha3', $path),
                                          'class-variance-authority' => \sprintf('%s/ui/lib/astro-hydration/dist/class-variance-authority.js?2.1.0-alpha3', $path),
                                          'tailwind-merge' => \sprintf('%s/ui/lib/astro-hydration/dist/tailwind-merge.js?2.1.0-alpha3', $path),
                                          '@/lib/FormattedText' => \sprintf('%s/ui/lib/astro-hydration/dist/FormattedText.js?2.1.0-alpha3', $path),
                                          'next-image-standalone' => \sprintf('%s/ui/lib/astro-hydration/dist/next-image-standalone.js?2.1.0-alpha3', $path),
                                          '@/lib/utils' => \sprintf('%s/ui/lib/astro-hydration/dist/utils.js?2.1.0-alpha3', $path),
                                          '@drupal-api-client/json-api-client' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-client.js?2.1.0-alpha3', $path),
                                          'drupal-jsonapi-params' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-params.js?2.1.0-alpha3', $path),
                                          '@/lib/jsonapi-utils' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-utils.js?2.1.0-alpha3', $path),
                                          '@/lib/drupal-utils' => \sprintf('%s/ui/lib/astro-hydration/dist/drupal-utils.js?2.1.0-alpha3', $path),
                                          'swr' => \sprintf('%s/ui/lib/astro-hydration/dist/swr.js?2.1.0-alpha3', $path),
                                        ],
                                      ],
                                      '#attached' => [
                                        'html_head_link' => [
                                          [
                                            [
                                              'rel' => 'modulepreload',
                                              'fetchpriority' => 'high',
                                              'href' => \sprintf('%s/ui/lib/astro-hydration/dist/signals.module.js?2.1.0-alpha3', $path),
                                            ],
                                          ],
                                          [
                                            [
                                              'rel' => 'modulepreload',
                                              'fetchpriority' => 'high',
                                              'href' => \sprintf('%s/ui/lib/astro-hydration/dist/preload-helper.js?2.1.0-alpha3', $path),
                                            ],
                                          ],
                                        ],
                                        'library' => [
                                          'canvas/astro_island.my-cta',
                                          'canvas/asset_library.' . AssetLibrary::GLOBAL_ID,
                                        ],
                                      ],
                                      '#name' => 'My First Code Component',
                                      '#component_url' => '::SITE_DIR_BASE_URL::/files/astro-island/zp6hEMcVLAQUXUUP3gsBwM5-MNs4_2kJ_7z16CTg1Sk.js',
                                      '#props' => [
                                        'text' => 'Hello, from a "code component"!',
                                        'href' => 'https://example.com',
                                        'canvas_uuid' => '2f57ba57-f32a-4a7b-9896-9d1104b446f1',
                                        'canvas_slot_ids' => [],
                                        'canvas_is_preview' => FALSE,
                                      ],
                                      '#prefix' => Markup::create('<!-- canvas-start-2f57ba57-f32a-4a7b-9896-9d1104b446f1 -->'),
                                      '#suffix' => Markup::create('<!-- canvas-end-2f57ba57-f32a-4a7b-9896-9d1104b446f1 -->'),
                                      '#uuid' => '2f57ba57-f32a-4a7b-9896-9d1104b446f1',
                                    ],
                                  ],
                                  'b4bc6c8f-66f7-458a-99a9-41c29b2801e7' => [
                                    '#type' => RenderSafeComponentContainer::PLUGIN_ID,
                                    '#component_uuid' => 'b4bc6c8f-66f7-458a-99a9-41c29b2801e7',
                                    '#component_context' => 'Page A page (-)',
                                    '#is_preview' => FALSE,
                                    '#component' => [
                                      '#type' => 'astro_island',
                                      '#cache' => [
                                        'tags' => [
                                          'config:canvas.js_component.my-cta-with-auto-save',
                                          'config:canvas.component.js.my-cta-with-auto-save',
                                        ],
                                        'contexts' => [],
                                        'max-age' => Cache::PERMANENT,
                                      ],
                                      '#import_maps' => [
                                        ImportMapResponseAttachmentsProcessor::GLOBAL_IMPORTS => [
                                          'preact' => \sprintf('%s/ui/lib/astro-hydration/dist/preact.module.js?2.1.0-alpha3', $path),
                                          'preact/hooks' => \sprintf('%s/ui/lib/astro-hydration/dist/hooks.module.js?2.1.0-alpha3', $path),
                                          'react/jsx-runtime' => \sprintf('%s/ui/lib/astro-hydration/dist/jsx-runtime-default.js?2.1.0-alpha3', $path),
                                          'react' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $path),
                                          'react-dom' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $path),
                                          'react-dom/client' => \sprintf('%s/ui/lib/astro-hydration/dist/compat.module.js?2.1.0-alpha3', $path),
                                          'clsx' => \sprintf('%s/ui/lib/astro-hydration/dist/clsx.js?2.1.0-alpha3', $path),
                                          'class-variance-authority' => \sprintf('%s/ui/lib/astro-hydration/dist/class-variance-authority.js?2.1.0-alpha3', $path),
                                          'tailwind-merge' => \sprintf('%s/ui/lib/astro-hydration/dist/tailwind-merge.js?2.1.0-alpha3', $path),
                                          '@/lib/FormattedText' => \sprintf('%s/ui/lib/astro-hydration/dist/FormattedText.js?2.1.0-alpha3', $path),
                                          'next-image-standalone' => \sprintf('%s/ui/lib/astro-hydration/dist/next-image-standalone.js?2.1.0-alpha3', $path),
                                          '@/lib/utils' => \sprintf('%s/ui/lib/astro-hydration/dist/utils.js?2.1.0-alpha3', $path),
                                          '@drupal-api-client/json-api-client' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-client.js?2.1.0-alpha3', $path),
                                          'drupal-jsonapi-params' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-params.js?2.1.0-alpha3', $path),
                                          '@/lib/jsonapi-utils' => \sprintf('%s/ui/lib/astro-hydration/dist/jsonapi-utils.js?2.1.0-alpha3', $path),
                                          '@/lib/drupal-utils' => \sprintf('%s/ui/lib/astro-hydration/dist/drupal-utils.js?2.1.0-alpha3', $path),
                                          'swr' => \sprintf('%s/ui/lib/astro-hydration/dist/swr.js?2.1.0-alpha3', $path),
                                        ],
                                      ],
                                      '#attached' => [
                                        'html_head_link' => [
                                          [
                                            [
                                              'rel' => 'modulepreload',
                                              'fetchpriority' => 'high',
                                              'href' => \sprintf('%s/ui/lib/astro-hydration/dist/signals.module.js?2.1.0-alpha3', $path),
                                            ],
                                          ],
                                          [
                                            [
                                              'rel' => 'modulepreload',
                                              'fetchpriority' => 'high',
                                              'href' => \sprintf('%s/ui/lib/astro-hydration/dist/preload-helper.js?2.1.0-alpha3', $path),
                                            ],
                                          ],
                                        ],
                                        'library' => [
                                          'canvas/astro_island.my-cta-with-auto-save',
                                          'canvas/asset_library.' . AssetLibrary::GLOBAL_ID,
                                        ],
                                      ],
                                      '#name' => 'My Code Component with Auto-Save',
                                      '#component_url' => '::SITE_DIR_BASE_URL::/files/astro-island/dErbetE11Vm2Twy1AoP3OU8bws4QaYAih9Gd8PgRrm4.js',
                                      '#props' => [
                                        'text' => 'Hello, from a "auto-save code component"!',
                                        'href' => 'https://example.com',
                                        'canvas_uuid' => 'b4bc6c8f-66f7-458a-99a9-41c29b2801e7',
                                        'canvas_slot_ids' => [],
                                        'canvas_is_preview' => FALSE,
                                      ],
                                      '#prefix' => Markup::create('<!-- canvas-start-b4bc6c8f-66f7-458a-99a9-41c29b2801e7 -->'),
                                      '#suffix' => Markup::create('<!-- canvas-end-b4bc6c8f-66f7-458a-99a9-41c29b2801e7 -->'),
                                      '#uuid' => 'b4bc6c8f-66f7-458a-99a9-41c29b2801e7',
                                    ],
                                  ],
                                  '9f09ecd8-ec65-408c-b5c8-ef036e6aeb97' => [
                                    '#type' => RenderSafeComponentContainer::PLUGIN_ID,
                                    '#component_uuid' => '9f09ecd8-ec65-408c-b5c8-ef036e6aeb97',
                                    '#component_context' => 'Page A page (-)',
                                    '#is_preview' => FALSE,
                                    '#component' => [
                                      '#type' => 'component',
                                      '#cache' => [
                                        'tags' => ['config:canvas.component.sdc.canvas_test_sdc.props-no-slots'],
                                        'contexts' => [],
                                        'max-age' => Cache::PERMANENT,
                                      ],
                                      '#component' => 'canvas_test_sdc:props-no-slots',
                                      '#props' => [
                                        'heading' => 'Hello, from slot <LAST ONE>!',
                                        'canvas_uuid' => '9f09ecd8-ec65-408c-b5c8-ef036e6aeb97',
                                        'canvas_slot_ids' => [],
                                        'canvas_is_preview' => FALSE,
                                      ],
                                      '#prefix' => Markup::create('<!-- canvas-start-last-in-tree -->'),
                                      '#suffix' => Markup::create('<!-- canvas-end-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97 -->'),
                                      '#attached' => [
                                        'library' => [
                                          'core/components.canvas_test_sdc--props-no-slots',
                                        ],
                                      ],
                                    ],
                                  ],
                                ],
                              ],
                              '#prefix' => Markup::create('<!-- canvas-start-e0b92f23-c177-4196-8fa4-3e837f99a357 -->'),
                              '#suffix' => Markup::create('<!-- canvas-end-e0b92f23-c177-4196-8fa4-3e837f99a357 -->'),
                              '#attached' => [
                                'library' => [
                                  'core/components.canvas_test_sdc--props-slots',
                                ],
                              ],
                            ],
                          ],
                        ],
                      ],
                      '#prefix' => Markup::create('<!-- canvas-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd -->'),
                      '#suffix' => Markup::create('<!-- canvas-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd -->'),
                      '#attached' => [
                        'library' => [
                          'core/components.canvas_test_sdc--props-slots',
                        ],
                      ],
                    ],
                  ],
                ],
              ],
              '#prefix' => Markup::create('<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 -->'),
              '#suffix' => Markup::create('<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->'),
              '#attached' => [
                'library' => [
                  'core/components.canvas_test_sdc--props-slots',
                ],
              ],
            ],
          ],
        ],
      ],
      'expected_html' => <<<HTML
<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 --><div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-41595148-e5c1-4873-b373-be3ae6e21340/heading -->Hello, world!<!-- canvas-prop-end-41595148-e5c1-4873-b373-be3ae6e21340/heading --></h1>
  <div class="component--props-slots--body">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_body --><!-- canvas-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd --><div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd/heading -->Hello, from slot level 1!<!-- canvas-prop-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd/heading --></h1>
  <div class="component--props-slots--body">
        <!-- canvas-slot-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_body --><!-- canvas-start-e0b92f23-c177-4196-8fa4-3e837f99a357 --><div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-e0b92f23-c177-4196-8fa4-3e837f99a357/heading -->Hello, from slot level 2!<!-- canvas-prop-end-e0b92f23-c177-4196-8fa4-3e837f99a357/heading --></h1>
  <div class="component--props-slots--body">
        <!-- canvas-slot-start-e0b92f23-c177-4196-8fa4-3e837f99a357/the_body --><!-- canvas-start-81c63cac-187d-4f05-8acc-1c38fb2489d3 --><div  data-component-id="canvas_test_sdc:props-no-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-81c63cac-187d-4f05-8acc-1c38fb2489d3/heading -->Hello, from slot level 3!<!-- canvas-prop-end-81c63cac-187d-4f05-8acc-1c38fb2489d3/heading --></h1>
</div>
<!-- canvas-end-81c63cac-187d-4f05-8acc-1c38fb2489d3 --><!-- canvas-start-68167e4a-9245-41be-b564-f1e1dcad1dec --><div id="block-68167e4a-9245-41be-b564-f1e1dcad1dec">


          <a href="/" rel="home">Canvas Test Site</a>
    Drupal Canvas Test Site
</div>
<!-- canvas-end-68167e4a-9245-41be-b564-f1e1dcad1dec --><!-- canvas-start-2f57ba57-f32a-4a7b-9896-9d1104b446f1 --><canvas-island uid="2f57ba57-f32a-4a7b-9896-9d1104b446f1"
      component-url="::SITE_DIR_BASE_URL::/files/astro-island/zp6hEMcVLAQUXUUP3gsBwM5-MNs4_2kJ_7z16CTg1Sk.js"
      component-export="default"
      renderer-url="::CANVAS_DIR_BASE_URL::/ui/lib/astro-hydration/dist/client.js"
      props="{&quot;text&quot;:[&quot;raw&quot;,&quot;Hello, from a \&quot;code component\&quot;!&quot;],&quot;href&quot;:[&quot;raw&quot;,&quot;https:\/\/example.com&quot;]}"
      ssr="" client="only"
      opts="{&quot;name&quot;:&quot;My First Code Component&quot;,&quot;value&quot;:&quot;preact&quot;}"><script type="module" src="::CANVAS_DIR_BASE_URL::/ui/lib/astro-hydration/dist/client.js" blocking="render"></script><script type="module" src="::SITE_DIR_BASE_URL::/files/astro-island/zp6hEMcVLAQUXUUP3gsBwM5-MNs4_2kJ_7z16CTg1Sk.js" blocking="render"></script></canvas-island><!-- canvas-end-2f57ba57-f32a-4a7b-9896-9d1104b446f1 --><!-- canvas-start-b4bc6c8f-66f7-458a-99a9-41c29b2801e7 --><canvas-island uid="b4bc6c8f-66f7-458a-99a9-41c29b2801e7"
      component-url="::SITE_DIR_BASE_URL::/files/astro-island/dErbetE11Vm2Twy1AoP3OU8bws4QaYAih9Gd8PgRrm4.js"
      component-export="default"
      renderer-url="::CANVAS_DIR_BASE_URL::/ui/lib/astro-hydration/dist/client.js"
      props="{&quot;text&quot;:[&quot;raw&quot;,&quot;Hello, from a \&quot;auto-save code component\&quot;!&quot;],&quot;href&quot;:[&quot;raw&quot;,&quot;https:\/\/example.com&quot;]}"
      ssr="" client="only"
      opts="{&quot;name&quot;:&quot;My Code Component with Auto-Save&quot;,&quot;value&quot;:&quot;preact&quot;}"><script type="module" src="::CANVAS_DIR_BASE_URL::/ui/lib/astro-hydration/dist/client.js" blocking="render"></script><script type="module" src="::SITE_DIR_BASE_URL::/files/astro-island/dErbetE11Vm2Twy1AoP3OU8bws4QaYAih9Gd8PgRrm4.js" blocking="render"></script></canvas-island><!-- canvas-end-b4bc6c8f-66f7-458a-99a9-41c29b2801e7 --><!-- canvas-start-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97 --><div  data-component-id="canvas_test_sdc:props-no-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97/heading -->Hello, from slot &lt;LAST ONE&gt;!<!-- canvas-prop-end-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97/heading --></h1>
</div>
<!-- canvas-end-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97 --><!-- canvas-slot-end-e0b92f23-c177-4196-8fa4-3e837f99a357/the_body -->
    </div>
  <div class="component--props-slots--footer">
        <!-- canvas-slot-start-e0b92f23-c177-4196-8fa4-3e837f99a357/the_footer --><div class="canvas--slot-empty-placeholder"></div>Example value for &lt;strong&gt;the_footer&lt;/strong&gt;.<!-- canvas-slot-end-e0b92f23-c177-4196-8fa4-3e837f99a357/the_footer -->
    </div>
  <div class="component--props-slots--colophon">
        <!-- canvas-slot-start-e0b92f23-c177-4196-8fa4-3e837f99a357/the_colophon --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-e0b92f23-c177-4196-8fa4-3e837f99a357/the_colophon -->
    </div>
</div>
<!-- canvas-end-e0b92f23-c177-4196-8fa4-3e837f99a357 --><!-- canvas-slot-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_body -->
    </div>
  <div class="component--props-slots--footer">
        <!-- canvas-slot-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_footer --><div class="canvas--slot-empty-placeholder"></div>Example value for &lt;strong&gt;the_footer&lt;/strong&gt;.<!-- canvas-slot-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_footer -->
    </div>
  <div class="component--props-slots--colophon">
        <!-- canvas-slot-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_colophon --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_colophon -->
    </div>
</div>
<!-- canvas-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd --><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_body -->
    </div>
  <div class="component--props-slots--footer">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_footer --><div class="canvas--slot-empty-placeholder"></div>Example value for &lt;strong&gt;the_footer&lt;/strong&gt;.<!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_footer -->
    </div>
  <div class="component--props-slots--colophon">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_colophon --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_colophon -->
    </div>
</div>
<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->
HTML,
      'expected_cache_tags' => [
        'config:canvas.component.sdc.canvas_test_sdc.props-slots',
        'config:canvas.component.sdc.canvas_test_sdc.props-no-slots',
        'config:canvas.js_component.my-cta-with-auto-save',
        'config:canvas.component.js.my-cta-with-auto-save',
        'config:canvas.js_component.my-cta',
        'config:canvas.component.js.my-cta',
        'config:system.site',
        'config:canvas.component.block.system_branding_block',
      ],
    ];

    $path_to_auto_saved_js_component = [
      ComponentTreeItemList::ROOT_UUID,
      '41595148-e5c1-4873-b373-be3ae6e21340',
      '#component', '#slots', 'the_body',
      'dfd2e899-6d88-46f8-b6aa-98929d1586dd',
      '#component', '#slots', 'the_body',
      'e0b92f23-c177-4196-8fa4-3e837f99a357',
      '#component', '#slots', 'the_body',
      'b4bc6c8f-66f7-458a-99a9-41c29b2801e7',
      '#component',
    ];
    $path_to_js_component = [
      ComponentTreeItemList::ROOT_UUID,
      '41595148-e5c1-4873-b373-be3ae6e21340',
      '#component', '#slots', 'the_body',
      'dfd2e899-6d88-46f8-b6aa-98929d1586dd',
      '#component', '#slots', 'the_body',
      'e0b92f23-c177-4196-8fa4-3e837f99a357',
      '#component', '#slots', 'the_body',
      '2f57ba57-f32a-4a7b-9896-9d1104b446f1',
      '#component',
    ];

    yield 'component tree with complex nesting' => [...self::removePrefixSuffix($component_tree_with_complex_nesting), 'isPreview' => FALSE];
    yield 'component tree with complex nesting in preview' => [
      ...self::overwriteRenderableExpectations(
        self::modifyExpectationFromLiveToPreview($component_tree_with_complex_nesting, TRUE),
        [
          [
            'parents' => [...$path_to_auto_saved_js_component, '#name'],
            'value' => 'My Code Component with Auto-Save - Draft',
          ],
          [
            'parents' => [...$path_to_auto_saved_js_component, '#component_url'],
            'value' => '/canvas/api/v0/auto-saves/js/js_component/my-cta-with-auto-save',
          ],
          [
            'parents' => [...$path_to_auto_saved_js_component, '#cache'],
            'value' => [
              'tags' => [
                AutoSaveManager::CACHE_TAG,
                'config:canvas.js_component.my-cta-with-auto-save',
                'config:canvas.component.js.my-cta-with-auto-save',
              ],
              'contexts' => [],
              'max-age' => Cache::PERMANENT,
            ],
          ],
          // ⚠️ Now how also a code component without an auto-save is loaded
          // from the draft URL anyway (which avoids race conditions), but note
          // that its title does not get a "draft" suffix.
          [
            'parents' => [...$path_to_js_component, '#component_url'],
            'value' => '/canvas/api/v0/auto-saves/js/js_component/my-cta',
          ],
          [
            'parents' => [...$path_to_js_component, '#cache'],
            'value' => [
              'tags' => [
                AutoSaveManager::CACHE_TAG,
                'config:canvas.js_component.my-cta',
                'config:canvas.component.js.my-cta',
              ],
              'contexts' => [],
              'max-age' => Cache::PERMANENT,
            ],
          ],
        ],
      ),
      'expected_html' => <<<HTML
<!-- canvas-start-41595148-e5c1-4873-b373-be3ae6e21340 --><div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-41595148-e5c1-4873-b373-be3ae6e21340/heading -->Hello, world!<!-- canvas-prop-end-41595148-e5c1-4873-b373-be3ae6e21340/heading --></h1>
  <div class="component--props-slots--body">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_body --><!-- canvas-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd --><div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd/heading -->Hello, from slot level 1!<!-- canvas-prop-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd/heading --></h1>
  <div class="component--props-slots--body">
        <!-- canvas-slot-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_body --><!-- canvas-start-e0b92f23-c177-4196-8fa4-3e837f99a357 --><div  data-component-id="canvas_test_sdc:props-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-e0b92f23-c177-4196-8fa4-3e837f99a357/heading -->Hello, from slot level 2!<!-- canvas-prop-end-e0b92f23-c177-4196-8fa4-3e837f99a357/heading --></h1>
  <div class="component--props-slots--body">
        <!-- canvas-slot-start-e0b92f23-c177-4196-8fa4-3e837f99a357/the_body --><!-- canvas-start-81c63cac-187d-4f05-8acc-1c38fb2489d3 --><div  data-component-id="canvas_test_sdc:props-no-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-81c63cac-187d-4f05-8acc-1c38fb2489d3/heading -->Hello, from slot level 3!<!-- canvas-prop-end-81c63cac-187d-4f05-8acc-1c38fb2489d3/heading --></h1>
</div>
<!-- canvas-end-81c63cac-187d-4f05-8acc-1c38fb2489d3 --><!-- canvas-start-68167e4a-9245-41be-b564-f1e1dcad1dec --><div id="block-68167e4a-9245-41be-b564-f1e1dcad1dec">


          <a href="/" rel="home">Canvas Test Site</a>
    Drupal Canvas Test Site
</div>
<!-- canvas-end-68167e4a-9245-41be-b564-f1e1dcad1dec --><!-- canvas-start-2f57ba57-f32a-4a7b-9896-9d1104b446f1 --><canvas-island uid="2f57ba57-f32a-4a7b-9896-9d1104b446f1"
      component-url="/canvas/api/v0/auto-saves/js/js_component/my-cta"
      component-export="default"
      renderer-url="::CANVAS_DIR_BASE_URL::/ui/lib/astro-hydration/dist/client.js"
      props="{&quot;text&quot;:[&quot;raw&quot;,&quot;Hello, from a \&quot;code component\&quot;!&quot;],&quot;href&quot;:[&quot;raw&quot;,&quot;https:\/\/example.com&quot;]}"
      ssr="" client="only"
      opts="{&quot;name&quot;:&quot;My First Code Component&quot;,&quot;value&quot;:&quot;preact&quot;}"><script type="module" src="::CANVAS_DIR_BASE_URL::/ui/lib/astro-hydration/dist/client.js" blocking="render"></script><script type="module" src="/canvas/api/v0/auto-saves/js/js_component/my-cta" blocking="render"></script></canvas-island><!-- canvas-end-2f57ba57-f32a-4a7b-9896-9d1104b446f1 --><!-- canvas-start-b4bc6c8f-66f7-458a-99a9-41c29b2801e7 --><canvas-island uid="b4bc6c8f-66f7-458a-99a9-41c29b2801e7"
      component-url="/canvas/api/v0/auto-saves/js/js_component/my-cta-with-auto-save"
      component-export="default"
      renderer-url="::CANVAS_DIR_BASE_URL::/ui/lib/astro-hydration/dist/client.js"
      props="{&quot;text&quot;:[&quot;raw&quot;,&quot;Hello, from a \&quot;auto-save code component\&quot;!&quot;],&quot;href&quot;:[&quot;raw&quot;,&quot;https:\/\/example.com&quot;]}"
      ssr="" client="only"
      opts="{&quot;name&quot;:&quot;My Code Component with Auto-Save - Draft&quot;,&quot;value&quot;:&quot;preact&quot;}"><script type="module" src="::CANVAS_DIR_BASE_URL::/ui/lib/astro-hydration/dist/client.js" blocking="render"></script><script type="module" src="/canvas/api/v0/auto-saves/js/js_component/my-cta-with-auto-save" blocking="render"></script></canvas-island><!-- canvas-end-b4bc6c8f-66f7-458a-99a9-41c29b2801e7 --><!-- canvas-start-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97 --><div  data-component-id="canvas_test_sdc:props-no-slots" style="font-family: Helvetica, Arial, sans-serif; width: 100%; height: 100vh; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; padding: 20px; box-sizing: border-box;">
  <h1 style="font-size: 3em; margin: 0.5em 0; color: #333;"><!-- canvas-prop-start-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97/heading -->Hello, from slot &lt;LAST ONE&gt;!<!-- canvas-prop-end-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97/heading --></h1>
</div>
<!-- canvas-end-9f09ecd8-ec65-408c-b5c8-ef036e6aeb97 --><!-- canvas-slot-end-e0b92f23-c177-4196-8fa4-3e837f99a357/the_body -->
    </div>
  <div class="component--props-slots--footer">
        <!-- canvas-slot-start-e0b92f23-c177-4196-8fa4-3e837f99a357/the_footer --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-e0b92f23-c177-4196-8fa4-3e837f99a357/the_footer -->
    </div>
  <div class="component--props-slots--colophon">
        <!-- canvas-slot-start-e0b92f23-c177-4196-8fa4-3e837f99a357/the_colophon --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-e0b92f23-c177-4196-8fa4-3e837f99a357/the_colophon -->
    </div>
</div>
<!-- canvas-end-e0b92f23-c177-4196-8fa4-3e837f99a357 --><!-- canvas-slot-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_body -->
    </div>
  <div class="component--props-slots--footer">
        <!-- canvas-slot-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_footer --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_footer -->
    </div>
  <div class="component--props-slots--colophon">
        <!-- canvas-slot-start-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_colophon --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd/the_colophon -->
    </div>
</div>
<!-- canvas-end-dfd2e899-6d88-46f8-b6aa-98929d1586dd --><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_body -->
    </div>
  <div class="component--props-slots--footer">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_footer --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_footer -->
    </div>
  <div class="component--props-slots--colophon">
        <!-- canvas-slot-start-41595148-e5c1-4873-b373-be3ae6e21340/the_colophon --><div class="canvas--slot-empty-placeholder"></div><!-- canvas-slot-end-41595148-e5c1-4873-b373-be3ae6e21340/the_colophon -->
    </div>
</div>
<!-- canvas-end-41595148-e5c1-4873-b373-be3ae6e21340 -->
HTML,
      'isPreview' => TRUE,
      'expected_cache_tags' => [
        'config:canvas.component.sdc.canvas_test_sdc.props-slots',
        'config:canvas.component.sdc.canvas_test_sdc.props-no-slots',
        AutoSaveManager::CACHE_TAG,
        'config:canvas.js_component.my-cta-with-auto-save',
        'config:canvas.component.js.my-cta-with-auto-save',
        'config:canvas.js_component.my-cta',
        'config:canvas.component.js.my-cta',
        'config:system.site',
        'config:canvas.component.block.system_branding_block',
      ],
    ];

  }

  /**
   * Tests an entity with a hydrated tree item can be normalized.
   */
  public function testNormalize(): void {
    // We need the schema for user as the author entity reference triggers an
    // attempt to load the anonymous user from the database.
    $this->installEntitySchema('user');
    $page = Page::create();
    self::assertIsArray(\Drupal::service('serializer')->normalize($page));
  }

  public static function providerInjectSubTreeItemList(): iterable {
    $initial_tree = [
      [
        'uuid' => '72eb7863-ea7f-4e31-8cfe-01f0d0471682',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'parent_uuid' => NULL,
        'inputs' => [
          'heading' => [
            'sourceType' => 'static:field_item:string',
            'value' => 'Hello world',
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
    ];
    $valid_exposed_slots = [
      'exposed' => [
        'component_uuid' => '72eb7863-ea7f-4e31-8cfe-01f0d0471682',
        'slot_name' => 'the_body',
      ],
    ];
    $valid_subtree = [
      [
        'uuid' => 'caac2f59-6a47-41d5-8dc9-0fa99a7e6101',
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'parent_uuid' => '72eb7863-ea7f-4e31-8cfe-01f0d0471682',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => [
            'sourceType' => 'static:field_item:string',
            'value' => 'This is in an exposed slot',
            'expression' => 'ℹ︎string␟value',
          ],
        ],
      ],
    ];

    // The subtree is properly injected into the exposed slot and its inputs are
    // merged into the main tree.
    yield 'No error: everything merged properly' => [
      $initial_tree,
      $valid_exposed_slots,
      $valid_subtree,
      \array_merge($initial_tree, $valid_subtree),
    ];

    // The subtree targets a slot that isn't exposed, so it's just ignored.
    $subtree_in_non_existent_slot = $valid_subtree;
    $subtree_in_non_existent_slot[0]['slot'] = 'not_exposed';
    yield 'No error: subtrees do not match any exposed slots' => [
      $initial_tree,
      $valid_exposed_slots,
      [$subtree_in_non_existent_slot],
      $initial_tree,
    ];

    $tree_with_non_empty_slot = $initial_tree;
    $tree_with_non_empty_slot[] = [
      'uuid' => '2b86e95d-ebc3-4cdb-a7af-b203f415f08e',
      'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
      'parent_uuid' => '72eb7863-ea7f-4e31-8cfe-01f0d0471682',
      'slot' => 'the_body',
      'inputs' => [
        'heading' => [
          'sourceType' => 'static:field_item:string',
          'value' => 'This is and existing thing',
          'expression' => 'ℹ︎string␟value',
        ],
      ],
    ];
    yield 'Error: target slot is not empty' => [
      $tree_with_non_empty_slot,
      $valid_exposed_slots,
      $valid_subtree,
      "Cannot inject subtree because the targeted slot is not empty.",
    ];

    $tree_with_conflicting_components = $initial_tree;
    // Add a component to our tree which will conflict with one that is in the
    // subtree.
    $tree_with_conflicting_components[] = [
      'uuid' => 'caac2f59-6a47-41d5-8dc9-0fa99a7e6101',
      'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
      'inputs' => [
        'heading' => [
          'sourceType' => 'static:field_item:string',
          'value' => 'This is an existing thing in the root of the template',
          'expression' => 'ℹ︎string␟value',
        ],
      ],
    ];
    yield 'Error: subtree component already exists in the main tree' => [
      $tree_with_conflicting_components,
      $valid_exposed_slots,
      $valid_subtree,
      "Cannot inject subtree because some of its components are already in the final tree.",
    ];

    yield 'Error: target component UUID is not set' => [
      $initial_tree,
      [
        'exposed' => [
          'slot_name' => 'the_body',
        ],
      ],
      [$valid_subtree],
      "Cannot inject subtree because we don't know the UUID of the component instance to target.",
    ];

    yield 'Error: target component slot name is not set' => [
      $initial_tree,
      [
        'exposed' => [
          'component_uuid' => '72eb7863-ea7f-4e31-8cfe-01f0d0471682',
        ],
      ],
      [$valid_subtree],
      "Cannot inject subtree because we don't know the name of the component slot to target.",
    ];
  }

  /**
   * @covers ::injectSubTreeItemList
   *
   * @dataProvider providerInjectSubTreeItemList
   */
  public function testInjectSubTreeItemList(array $initial_value, array $exposed_slot_info, array $subtrees, array|string $expected_tree_or_exception): void {
    $target_tree = self::staticallyCreateDanglingComponentTreeItemList(\Drupal::typedDataManager());
    $target_tree->setValue($initial_value);

    $sub_tree = self::staticallyCreateDanglingComponentTreeItemList(\Drupal::typedDataManager());
    $sub_tree->setValue($subtrees);

    try {
      $target_tree->injectSubTreeItemList($exposed_slot_info, $sub_tree);
      if (\is_array($expected_tree_or_exception)) {
        $expected_tree_or_exception = \array_map(static function (array $item) {
          // Inject version IDs.
          $component = Component::load($item['component_id']);
          \assert($component instanceof ComponentInterface);
          return $item + ['component_version' => $component->getActiveVersion()];
        }, $expected_tree_or_exception);
      }
      $actual_value = $target_tree->getValue();

      $this->assertSame($expected_tree_or_exception, $actual_value);
    }
    catch (SubtreeInjectionException $e) {
      $this->assertSame($expected_tree_or_exception, $e->getMessage());
    }
  }

}
