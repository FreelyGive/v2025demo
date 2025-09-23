<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\canvas\Traits\BetterConfigDependencyManagerTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\CreateTestJsComponentTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\TestTools\Random;
use Drupal\canvas_test_validation\Plugin\Canvas\ComponentSource\InvalidSlots;

/**
 * @group canvas
 */
final class ContentTemplateValidationTest extends BetterConfigEntityValidationTestBase {

  use BetterConfigDependencyManagerTrait;
  use ContentTypeCreationTrait;
  use ContribStrictConfigSchemaTestTrait;
  use CreateTestJsComponentTrait;
  use GenerateComponentConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected bool $hasLabel = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The two only modules Drupal truly requires.
    'system',
    'user',
    // The module being tested.
    'canvas',
    // Modules providing used Components (and their ComponentSource plugins).
    'block',
    'canvas_test_sdc',
    // Canvas's dependencies (modules providing field types + widgets).
    'field',
    'file',
    'image',
    'link',
    'media',
    'node',
    'options',
    'text',
    'filter',
    'ckeditor5',
    'editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // We need to create a component with invalid source-defined slot names in
    // order to test that those slot names are validated in other contexts.
    // @see ::testExposeInvalidSlotDefinedBySource()
    'canvas.component.' . InvalidSlots::PLUGIN_ID . '.' . InvalidSlots::PLUGIN_ID,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig('node');
    $this->installConfig('canvas');
    $this->createContentType(['type' => 'alpha']);
    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'type' => 'text',
      'entity_type' => 'node',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'bundle' => 'alpha',
      'label' => 'Test field',
    ])->save();
    $this->generateComponentConfig();
    $this->createMyCtaComponentFromSdc();

    $this->entity = ContentTemplate::create([
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'alpha',
      'content_entity_type_view_mode' => 'full',
      'component_tree' => [
        [
          'uuid' => '435d1d20-a697-4d36-9892-9d61c825c99c',
          'component_id' => 'sdc.canvas_test_sdc.my-cta',
          'component_version' => '53ed322c96bee384',
          'inputs' => [
            'text' => [
              'sourceType' => 'static:field_item:string',
              'value' => 'This is really tricky for a first-timer',
              'expression' => 'ℹ︎string␟value',
            ],
            'href' => [
              'sourceType' => 'static:field_item:uri',
              'value' => 'https://drupal.org',
              'expression' => 'ℹ︎uri␟value',
            ],
          ],
          'label' => Random::string(255),
        ],
        // A code component populated by an entity base field.
        [
          'uuid' => '57afe4ed-c593-4457-a741-2ac5053be928',
          'component_id' => 'js.my-cta',
          'component_version' => '53ed322c96bee384',
          'inputs' => [
            'text' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:alpha␝title␞␟value',
            ],
          ],
        ],
        // An SDC populated by a normal entity field.
        [
          'uuid' => '2d06782a-0f24-43ae-963c-b5aff807dd95',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:alpha␝field_test␞␟value',
            ],
          ],
        ],
        // A block component.
        [
          'uuid' => 'b7f36452-ecd9-4c7c-a73c-492b81538512',
          'component_id' => 'block.system_branding_block',
          'component_version' => '247a23298360adb2',
          'inputs' => [
            'label' => '',
            'label_display' => FALSE,
            'use_site_logo' => FALSE,
            'use_site_name' => TRUE,
            'use_site_slogan' => TRUE,
          ],
        ],
        // An SDC with a slot that can be exposed.
        [
          'uuid' => 'b4937e35-ddc2-4f36-8d4c-b1cc14aaefef',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'static:field_item:string',
              'value' => 'There be a slot here',
              'expression' => 'ℹ︎string␟value',
            ],
          ],
        ],
      ],
    ]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function testEntityIsValid(): void {
    parent::testEntityIsValid();

    $this->assertSame('node.alpha.full', $this->entity->id());

    // Also validate config dependencies are computed correctly.
    $this->assertSame(
      [
        'config' => [
          'canvas.component.block.system_branding_block',
          'canvas.component.js.my-cta',
          'canvas.component.sdc.canvas_test_sdc.my-cta',
          'canvas.component.sdc.canvas_test_sdc.props-no-slots',
          'canvas.component.sdc.canvas_test_sdc.props-slots',
          'core.entity_view_mode.node.full',
          'field.field.node.alpha.field_test',
          'node.type.alpha',
        ],
        'module' => [
          'node',
        ],
      ],
      $this->entity->getDependencies()
    );
    $this->assertSame([
      'config' => [
        'canvas.component.block.system_branding_block',
        'canvas.component.js.my-cta',
        'canvas.component.sdc.canvas_test_sdc.my-cta',
        'canvas.component.sdc.canvas_test_sdc.props-no-slots',
        'canvas.component.sdc.canvas_test_sdc.props-slots',
        'core.entity_view_mode.node.full',
        'field.field.node.alpha.field_test',
        'node.type.alpha',
        'canvas.js_component.my-cta',
        'field.storage.node.field_test',
      ],
      'module' => [
        'node',
        'canvas',
        'system',
        'link',
        'options',
        'canvas_test_sdc',
        'core',
        'text',
        'field',
      ],
    ], $this->getAllDependencies($this->entity));
  }

  /**
   * @dataProvider providerInvalidComponentTree
   */
  public function testInvalidComponentTree(array $component_tree, array $expected_messages): void {
    \assert($this->entity instanceof ContentTemplate);
    $this->entity->setComponentTree($component_tree);
    $this->assertValidationErrors($expected_messages);
  }

  public static function providerInvalidComponentTree(): \Generator {
    yield "missing `component_tree` property" => [
      'component_tree' => [],
      'expected_messages' => [
        // @todo Add back message when we require dynamic prop sources in
        //   https://drupal.org/i/3541054.
        // 'component_tree' => 'The \'dynamic\' prop source type must be present.',
      ],
    ];

    yield "no DynamicPropSource, so no structured data from the content entity" => [
      'component_tree' => [
        [
          'uuid' => '19ff9a18-54a2-422a-bf68-49d65a5d53ac',
          'component_id' => 'sdc.canvas_test_sdc.druplicon',
          'component_version' => '8fe3be948e0194e1',
          'inputs' => [],
        ],
      ],
      'expected_messages' => [
        // @todo Add back message when we require dynamic prop sources in
        //   https://drupal.org/i/3541054.
        // 'component_tree' => "The 'dynamic' prop source type must be present.",
      ],
    ];

    yield "using disallowed Block-sourced Components" => [
      'component_tree' => [
        [
          'uuid' => '19ff9a18-54a2-422a-bf68-49d65a5d53ac',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
        [
          'uuid' => '08a60f2c-4737-47d3-9c34-956f33d5627e',
          'component_id' => 'block.system_branding_block',
          'component_version' => '247a23298360adb2',
          'inputs' => [
            'use_site_logo' => TRUE,
            'use_site_name' => TRUE,
            'use_site_slogan' => TRUE,
            'label' => '',
            'label_display' => FALSE,
          ],
        ],
        [
          'uuid' => 'ea2459e3-248d-4a0a-bdbc-1d982f729959',
          'component_id' => 'block.page_title_block',
          'component_version' => '62af221149ae4887',
          'inputs' => [
            'label' => '',
            'label_display' => FALSE,
          ],
        ],
        [
          'uuid' => '90804335-d16d-4799-9e80-ddb11692530a',
          'component_id' => 'block.system_messages_block',
          'component_version' => 'b92f802cf68eb83e',
          'inputs' => [
            'label' => '',
            'label_display' => FALSE,
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree' => [
          'The \'Drupal\Core\Block\TitleBlockPluginInterface\' component interface must be absent.',
          'The \'Drupal\Core\Block\MessagesBlockPluginInterface\' component interface must be absent.',
        ],
      ],
    ];

    yield "using AdaptedPropSource" => [
      'component_tree' => [
        [
          'uuid' => '90804335-d16d-4799-9e80-ddb11692530a',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
        [
          'uuid' => '7240f848-ea70-4ad2-a9d6-3ab60cba4d78',
          'component_id' => 'sdc.canvas_test_sdc.image',
          'component_version' => '5eabbfdb96b39a59',
          'inputs' => [
            'image' => [
              'sourceType' => 'adapter:image_apply_style',
              'adapterInputs' => [
                'image' => [
                  'sourceType' => 'dynamic',
                  'expression' => 'ℹ︎␜entity:node:article␝field_hero␞␟{src↝entity␜␜entity:file␝uri␞0␟value,alt↠alt,width↠width,height↠height}',
                ],
                'imageStyle' => [
                  'sourceType' => 'static:field_item:string',
                  'value' => 'thumbnail',
                  'expression' => 'ℹ︎string␟value',
                ],
              ],
            ],
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree' => "The 'adapter' prop source type must be absent.",
      ],
    ];

    yield "not a uuid" => [
      'component_tree' => [
        [
          'uuid' => 'garry-sensible-jeans',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
        [
          'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.0.uuid' => 'This is not a valid UUID.',
      ],
    ];

    yield "invalid parent" => [
      'component_tree' => [
        [
          'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
        [
          'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
          'slot' => 'the_body',
          'parent_uuid' => '6381352f-5b0a-4ca1-960d-a5505b37b27c',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.1.parent_uuid' => 'Invalid component tree item with UUID <em class="placeholder">e303dd88-9409-4dc7-8a8b-a31602884a94</em> references an invalid parent <em class="placeholder">6381352f-5b0a-4ca1-960d-a5505b37b27c</em>.',
      ],
    ];

    yield "invalid slot" => [
      'component_tree' => [
        [
          'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
        [
          'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
          'slot' => 'banana',
          'parent_uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.1.slot' => 'Invalid component subtree. This component subtree contains an invalid slot name for component <em class="placeholder">sdc.canvas_test_sdc.props-slots</em>: <em class="placeholder">banana</em>. Valid slot names are: <em class="placeholder">the_body, the_footer, the_colophon</em>.',
      ],
    ];

    yield "invalid label" => [
      'component_tree' => [
        [
          'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
          'label' => Random::string(256),
        ],
      ],
      'expected_messages' => [
        'component_tree.0.label' => 'This value is too long. It should have <em class="placeholder">255</em> characters or less.',
      ],
    ];

    yield "invalid version" => [
      'component_tree' => [
        [
          'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'abc',
          'inputs' => [
            'heading' => 'And we laugh like soft, mad children',
          ],
        ],
        [
          'uuid' => '90804335-d16d-4799-9e80-ddb11692530a',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.0.component_version' => "'abc' is not a version that exists on component config entity 'sdc.canvas_test_sdc.props-slots'. Available versions: 'ab4d3ddce315cf64'.",
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testImmutableProperties(array $valid_values = []): void {
    $this->createContentType(['type' => 'beta']);
    EntityViewMode::create([
      'id' => 'node.social_media_card',
      'label' => 'Social Media Card',
      'targetEntityType' => 'node',
    ])->save();

    $valid_values = [
      'content_entity_type_id' => 'user',
      'content_entity_type_bundle' => 'beta',
      'content_entity_type_view_mode' => 'social_media_card',
    ];
    $additional_validation_errors = [
      'id' => [],
      'content_entity_type_id' => [
        'content_entity_type_bundle' => "The 'alpha' bundle does not exist on the 'user' entity type.",
        'content_entity_type_id' => 'The value you selected is not a valid choice.',
        'content_entity_type_view_mode' => "The 'core.entity_view_mode.user.full' config does not exist.",
      ],
      'content_entity_type_bundle' => [],
      'content_entity_type_view_mode' => [],
    ];

    // @todo Update parent method to accept a `$additional_validation_errors` parameter in addition to `$valid_values`, and uncomment the next line, remove all lines after it.
    // parent::testImmutableProperties($valid_values);
    $constraints = $this->entity->getEntityType()->getConstraints();
    $this->assertNotEmpty($constraints['ImmutableProperties'], 'All config entities should have at least one immutable ID property.');

    foreach ($constraints['ImmutableProperties'] as $property_name) {
      $original_value = $this->entity->get($property_name);
      $this->entity->set($property_name, $valid_values[$property_name] ?? $this->randomMachineName());
      $this->assertValidationErrors([
        '' => "The '$property_name' property cannot be changed.",
      ] + $additional_validation_errors[$property_name]);
      $this->entity->set($property_name, $original_value);
    }
  }

  public function testInvalidContentEntityTypeId(): void {
    $this->entity->set('content_entity_type_id', 'nope');
    $this->assertValidationErrors([
      '' => "The 'content_entity_type_id' property cannot be changed.",
      'content_entity_type_id' => [
        "The 'nope' plugin does not exist.",
        'The value you selected is not a valid choice.',
      ],
      'content_entity_type_bundle' => "The 'alpha' bundle does not exist on the 'nope' entity type.",
      'content_entity_type_view_mode' => "The 'core.entity_view_mode.nope.full' config does not exist.",
    ]);
  }

  public function testInvalidContentEntityTypeBundle(): void {
    $this->entity->set('content_entity_type_bundle', 'nope');
    $this->assertValidationErrors([
      '' => "The 'content_entity_type_bundle' property cannot be changed.",
      'content_entity_type_bundle' => "The 'nope' bundle does not exist on the 'node' entity type.",
    ]);
  }

  public function testInvalidContentEntityTypeViewMode(): void {
    $this->entity->set('content_entity_type_view_mode', 'nope');
    $this->assertValidationErrors([
      '' => "The 'content_entity_type_view_mode' property cannot be changed.",
      'content_entity_type_view_mode' => "The 'core.entity_view_mode.node.nope' config does not exist.",
    ]);
  }

  public function testExposedSlotMustBeEmpty(): void {
    assert($this->entity instanceof ContentTemplate);

    // Add a component in one of the open slots.
    $items = $this->entity->getComponentTree();
    $items->appendItem([
      'uuid' => '91f6e215-49f4-47c1-a1ac-dcc151876842',
      'parent_uuid' => 'b4937e35-ddc2-4f36-8d4c-b1cc14aaefef',
      'slot' => 'the_footer',
      'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
      'inputs' => [
        'heading' => [
          'sourceType' => 'dynamic',
          'expression' => 'ℹ︎␜entity:node:alpha␝title␞␟value',
        ],
      ],
    ]);
    $this->entity->setComponentTree($items->getValue());

    $this->entity->set('exposed_slots', [
      'filled_footer' => [
        'component_uuid' => 'b4937e35-ddc2-4f36-8d4c-b1cc14aaefef',
        'slot_name' => 'the_footer',
        'label' => "Something's already here!",
      ],
    ]);
    $this->assertValidationErrors([
      'exposed_slots.filled_footer' => 'The <em class="placeholder">the_footer</em> slot must be empty.',
    ]);
  }

  public static function providerInvalidExposedSlot(): iterable {
    yield 'component exposing the slot does not exist in the tree' => [
      [
        'not_a_thing' => [
          'component_uuid' => '6348ee20-cf62-49e3-bc86-cf62abc09c74',
          'slot_name' => 'not-a-thing',
          'label' => "Can't expose a slot in a component we don't have!",
        ],
      ],
      [
        'exposed_slots.not_a_thing' => 'The component <em class="placeholder">6348ee20-cf62-49e3-bc86-cf62abc09c74</em> does not exist in the tree.',
      ],
    ];

    yield 'exposed slot is not defined by the component' => [
      [
        'filled_footer' => [
          'component_uuid' => 'b4937e35-ddc2-4f36-8d4c-b1cc14aaefef',
          'slot_name' => 'not_a_real_slot',
          'label' => "Whither this slot you speak of?",
        ],
      ],
      [
        'exposed_slots.filled_footer' => 'The component <em class="placeholder">b4937e35-ddc2-4f36-8d4c-b1cc14aaefef</em> does not have a <em class="placeholder">not_a_real_slot</em> slot.',
      ],
    ];

    yield 'exposed slot machine name is not valid: spaces' => [
      [
        'not a valid exposed slot name' => [
          'component_uuid' => 'b4937e35-ddc2-4f36-8d4c-b1cc14aaefef',
          'slot_name' => 'the_footer',
          'label' => "I got your footer right here",
        ],
      ],
      [
        'exposed_slots' => '<em class="placeholder">&quot;not a valid exposed slot name&quot;</em> is not a valid exposed slot name.',
      ],
    ];

    yield 'exposed slot machine name is not valid: leading underscore' => [
      [
        '_neither' => [
          'component_uuid' => 'b4937e35-ddc2-4f36-8d4c-b1cc14aaefef',
          'slot_name' => 'the_footer',
          'label' => "I got your footer right here",
        ],
      ],
      [
        'exposed_slots' => '<em class="placeholder">&quot;_neither&quot;</em> is not a valid exposed slot name.',
      ],
    ];
  }

  /**
   * @dataProvider providerInvalidExposedSlot
   */
  public function testInvalidExposedSlot(array $exposed_slots, array $expected_errors): void {
    $this->entity->set('exposed_slots', $exposed_slots);
    $this->assertValidationErrors($expected_errors);
  }

  public function testExposeInvalidSlotDefinedBySource(): void {
    self::assertTrue($this->container->get('module_installer')->install(['canvas_test_validation']));
    Component::create([
      'id' => InvalidSlots::PLUGIN_ID . '.' . InvalidSlots::PLUGIN_ID,
      'label' => 'Component with an invalid source-defined slot',
      'source' => InvalidSlots::PLUGIN_ID,
      'source_local_id' => InvalidSlots::PLUGIN_ID,
      'category' => 'Test',
      'active_version' => 'ccab0b28617f1f56',
    ])->save();

    $tree = $this->entity->get('component_tree');
    assert(is_array($tree));
    $tree[] = [
      'uuid' => '1870f74a-2611-4864-8fc0-639f0d125d7f',
      'component_id' => InvalidSlots::PLUGIN_ID . '.' . InvalidSlots::PLUGIN_ID,
      'component_version' => 'ccab0b28617f1f56',
      'inputs' => [],
    ];
    \assert($this->entity instanceof ContentTemplate);
    $this->entity->setComponentTree($tree)
      ->set('exposed_slots', [
        'valid_alias' => [
          'component_uuid' => '1870f74a-2611-4864-8fc0-639f0d125d7f',
          // This slot name is defined by the component source, but isn't valid.
          'slot_name' => 'invalid sl😈t',
          'label' => "Not a legitimate slot name",
        ],
      ]);

    $this->assertValidationErrors([
      'exposed_slots.valid_alias.slot_name' => '<em class="placeholder">&quot;invalid sl😈t&quot;</em> is not a valid slot name.',
    ]);
  }

  public function testExposedSlotsOnlyAllowedInFullViewMode(): void {
    $this->entity = $this->entity->createDuplicate();
    $this->entity->set('content_entity_type_view_mode', 'teaser');
    $this->entity->set('id', 'node.alpha.teaser');
    $this->entity->set('exposed_slots', [
      'footer_for_you' => [
        'component_uuid' => 'b4937e35-ddc2-4f36-8d4c-b1cc14aaefef',
        'slot_name' => 'the_footer',
        'label' => "I got your footer right here",
      ],
    ]);
    $this->assertValidationErrors([
      'exposed_slots.footer_for_you' => 'Exposed slots are only allowed in the <em class="placeholder">full</em> view mode.',
    ]);
  }

}
