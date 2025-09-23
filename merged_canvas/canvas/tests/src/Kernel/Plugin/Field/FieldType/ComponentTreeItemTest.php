<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Plugin\Field\FieldType;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\VersionedConfigEntityBase;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\canvas\Traits\SingleDirectoryComponentTreeTestTrait;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;

/**
 * @coversDefaultClass \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem
 * @group canvas
 */
class ComponentTreeItemTest extends KernelTestBase {

  use SingleDirectoryComponentTreeTestTrait;
  use ComponentTreeItemListInstantiatorTrait;
  use ConstraintViolationsTestTrait;
  use ContribStrictConfigSchemaTestTrait;
  use GenerateComponentConfigTrait;
  use CiModulePathTrait;
  use ImageFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'sdc',
    'sdc_test',
    'canvas_test_sdc',
    // Dependencies must actually exist.
    'field',
    'user',
    'node',
    // Modules providing field types + widgets for the SDC Components'
    // `prop_field_definitions`.
    'file',
    'image',
    'options',
    'link',
    'system',
    'media',
    'canvas_test_code_components',
    'filter',
    'ckeditor5',
    'editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('canvas');
    $this->generateComponentConfig();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('node');
  }

  /**
   * @covers ::setValue
   * @covers ::onChange
   */
  public function testSetValue(): void {
    $this->generateComponentConfig();
    $component = Component::load('sdc.canvas_test_sdc.props-slots');
    assert($component !== NULL);

    // The test Component has a single version; create a second version.
    self::assertCount(1, $component->getVersions());
    $settings = $component->getSettings();
    $settings['prop_field_definitions']['heading']['default_value'][0]['value'] = 'Updated example value 👋';
    $component->createVersion('a9ff855f3d425e0d')
      ->setSettings($settings)
      ->save();
    $violations = $component->getTypedData()->validate();
    self::assertSame([], self::violationsToArray($violations));
    self::assertCount(2, $component->getVersions());

    // A helper method to set 2 instances of the exact same component to two
    // different versions, and assert that this is A) valid, B) successful.
    $set_values = function (ComponentTreeItemList $item_list) use ($component) {
      $inputs = [
        'heading' => 'This is really tricky for a first-timer …',
      ];
      $item_list->setValue([
        [
          'uuid' => '947c196f-f108-43fd-a446-03a08100d571',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          // ⚠️ Note the absence of a component version!
          'inputs' => $inputs,
        ],
        [
          'uuid' => '947c196f-f108-43fd-a446-03a08100d572',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => $component->getVersions()[1],
          'inputs' => $inputs,
        ],
      ]);
      $violations = $item_list->validate();
      self::assertSame([], self::violationsToArray($violations));
      self::assertInstanceOf(ComponentTreeItem::class, $item_list->get(0));
      self::assertInstanceOf(ComponentTreeItem::class, $item_list->get(1));
      self::assertSame($component->getActiveVersion(), $item_list->get(0)->getComponentVersion());
      self::assertSame($component->getVersions()[0], $item_list->get(0)->getComponentVersion());
      self::assertSame($component->getVersions()[1], $item_list->get(1)->getComponentVersion());
    };

    // Create a component tree item list using the oldest version; then try
    // editing it. The component version should not change.
    $component_tree = $this->createDanglingComponentTreeItemList();

    // Iteration 1: populate the empty component tree item list.
    self::assertTrue($component_tree->isEmpty());
    $set_values($component_tree);

    // Iteration 2: check idempotency — setting the same values should yield the
    // same result. Anything else would be data loss.
    self::assertFalse($component_tree->isEmpty());
    $set_values($component_tree);
  }

  /**
   * @testWith ["not-a-uuid", {"0.parent_uuid": "This is not a valid UUID."}]
   *           ["", {"0.parent_uuid": "This value should not be blank."}]
   * @covers \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator
   */
  public function testInvalidParentUuid(string $parent_uuid, array $expected_violations): void {
    $this->generateComponentConfig();
    $item_list = $this->createDanglingComponentTreeItemList();
    $item_list->setValue([
      [
        'parent_uuid' => $parent_uuid,
        'uuid' => '947c196f-f108-43fd-a446-03a08100d579',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'inputs' => [
          'heading' => 'This is really tricky for a first-timer …',
        ],
      ],
    ]);
    $this->assertCount(1, $item_list);
    $violations = $item_list->validate();
    $this->assertSame($expected_violations, self::violationsToArray($violations));
  }

  /**
   * @testWith ["not-a-slot", {"1.slot": "Invalid component subtree. This component subtree contains an invalid slot name for component <em class=\"placeholder\">sdc.canvas_test_sdc.props-slots</em>: <em class=\"placeholder\">not-a-slot</em>. Valid slot names are: <em class=\"placeholder\">the_body, the_footer, the_colophon</em>."}]
   *           ["", {"1.slot": "This value should not be blank."}]
   *           ["_", {"1.slot": "<em class=\"placeholder\">&quot;_&quot;</em> is not a valid slot name."}]
   *           ["-", {"1.slot": "<em class=\"placeholder\">&quot;-&quot;</em> is not a valid slot name."}]
   *           ["_invalid", {"1.slot": "<em class=\"placeholder\">&quot;_invalid&quot;</em> is not a valid slot name."}]
   *           ["-invalid", {"1.slot": "<em class=\"placeholder\">&quot;-invalid&quot;</em> is not a valid slot name."}]
   *           ["invalid-", {"1.slot": "<em class=\"placeholder\">&quot;invalid-&quot;</em> is not a valid slot name."}]
   *           ["invalid_", {"1.slot": "<em class=\"placeholder\">&quot;invalid_&quot;</em> is not a valid slot name."}]
   *           [null, {"1.slot": "Invalid component tree item with UUID <em class=\"placeholder\">8b6b47ec-1167-433b-975d-e2d97739f5a6</em>. A slot name must be present if a parent uuid is provided."}]
   *           ["the_body", {}]
   * @covers \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator
   */
  public function testInvalidSlot(?string $slot, array $expected_violations): void {
    $root_uuid = '947c196f-f108-43fd-a446-03a08100d579';
    $child_uuid = '8b6b47ec-1167-433b-975d-e2d97739f5a6';

    $this->generateComponentConfig();
    $item_list = $this->createDanglingComponentTreeItemList();
    $item_list->setValue([
      [
        'uuid' => $root_uuid,
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'inputs' => [
          'heading' => 'This is really tricky for a first-timer …',
        ],
      ],
      [
        'parent_uuid' => $root_uuid,
        'slot' => $slot,
        'uuid' => $child_uuid,
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'inputs' => [
          'heading' => '… but eventually it all makes sense. Wished I RTFMd.',
        ],
      ],
    ]);
    $this->assertCount(2, $item_list);
    $violations = $item_list->validate();
    $this->assertSame($expected_violations, self::violationsToArray($violations));
  }

  /**
   * @covers \Drupal\canvas\Plugin\Validation\Constraint\ValidConfigEntityVersionConstraintValidator
   */
  public function testInvalidVersion(): void {
    $root_uuid = '947c196f-f108-43fd-a446-03a08100d579';
    $child_uuid = '8b6b47ec-1167-433b-975d-e2d97739f5a6';

    $this->generateComponentConfig();
    $item_list = $this->createDanglingComponentTreeItemList();
    $item_list->setValue([
      [
        'uuid' => $root_uuid,
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'lol',
        'inputs' => [
          'heading' => 'This is really tricky for a first-timer …',
        ],
      ],
      [
        'parent_uuid' => $root_uuid,
        'slot' => 'the_body',
        'uuid' => $child_uuid,
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'component_version' => VersionedConfigEntityBase::ACTIVE_VERSION,
        'inputs' => [
          'heading' => '… but eventually it all makes sense. Wished I RTFMd.',
        ],
      ],
    ]);
    $this->assertCount(2, $item_list);
    $violations = $item_list->validate();
    $this->assertSame([
      '0.component_version' => "'lol' is not a version that exists on component config entity 'sdc.canvas_test_sdc.props-slots'. Available versions: 'ab4d3ddce315cf64'.",
      '1.component_version' => "'active' is not a version that exists on component config entity 'sdc.canvas_test_sdc.props-no-slots'. Available versions: '95f4f1d5ee47663b'.",
    ], self::violationsToArray($violations));
  }

  /**
   * @covers ::getParentUuid()
   * @covers ::getParentComponentTreeItem()
   * @covers ::getSlot()
   * @covers ::getComponentId()
   * @covers ::getComponent()
   * @covers ::getUuid()
   */
  public function testConvenienceMethods(): void {
    $root_uuid = '947c196f-f108-43fd-a446-03a08100d579';
    $child_uuid = '8b6b47ec-1167-433b-975d-e2d97739f5a6';
    $js_uuid = '0aaa0f58-287c-453d-be65-81ba0f4e6f1c';

    $this->generateComponentConfig();
    $this->installConfig('canvas_test_code_components');

    $item_list = $this->createDanglingComponentTreeItemList();
    $js_component_id = 'js.canvas_test_code_components_with_props';

    $js_component = Component::load($js_component_id);
    \assert($js_component instanceof ComponentInterface);
    $original_js_component_version = $js_component->getActiveVersion();

    $item_list->setValue([
      [
        'uuid' => $root_uuid,
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'inputs' => [
          'heading' => 'This is really tricky for a first-timer …',
        ],
      ],
      [
        'parent_uuid' => $root_uuid,
        'slot' => 'the_body',
        'uuid' => $child_uuid,
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'inputs' => [
          'heading' => '… but eventually it all makes sense. Wished I RTFMd.',
        ],
      ],
      [
        'uuid' => $js_uuid,
        'component_id' => $js_component_id,
        'inputs' => [
          'name' => 'Mad Dog Morgan',
          'age' => '35',
        ],
      ],
    ]);
    $this->assertCount(0, $item_list->validate());
    $this->assertCount(3, $item_list);

    // Call all convenience methods on the root component instance.
    $root = $item_list->get(0);
    assert($root instanceof ComponentTreeItem);
    $this->assertNull($root->getParentUuid());
    $this->assertNull($root->getParentComponentTreeItem());
    $this->assertNull($root->getSlot());
    $this->assertSame('sdc.canvas_test_sdc.props-slots', $root->getComponentId());
    $this->assertInstanceOf(Component::class, $root->getComponent());
    $component = Component::load('sdc.canvas_test_sdc.props-slots');
    $this->assertSame($component?->toArray(), $root->getComponent()->toArray());
    $this->assertSame($root_uuid, $root->getUuid());
    self::assertEquals($component->getLoadedVersion(), $root->getComponentVersion());

    // Call all convenience methods on the child component instance.
    $child = $item_list->get(1);
    assert($child instanceof ComponentTreeItem);
    $this->assertSame($root_uuid, $child->getParentUuid());
    $this->assertSame($root, $child->getParentComponentTreeItem());
    $this->assertSame('the_body', $child->getSlot());
    $this->assertSame('sdc.canvas_test_sdc.props-no-slots', $child->getComponentId());
    $this->assertInstanceOf(Component::class, $child->getComponent());
    $this->assertSame(Component::load('sdc.canvas_test_sdc.props-no-slots')?->toArray(), $child->getComponent()->toArray());
    $this->assertSame($child_uuid, $child->getUuid());

    // Add a new prop to the JS component and assert that the loaded version for
    // the saved item still uses the original version.
    $js_component_entity = JavaScriptComponent::load('canvas_test_code_components_with_props');
    \assert($js_component_entity instanceof JavaScriptComponent);
    $props = $js_component_entity->getProps();
    $props['real_name'] = [
      'type' => 'string',
      'title' => 'Real Name',
    ];
    $js_component_entity->setProps($props);
    $js_component_entity->save();
    $old_version_item = $item_list->get(2);
    \assert($old_version_item instanceof ComponentTreeItem);
    $reference = $old_version_item->getComponent();
    self::assertEquals($original_js_component_version, $reference?->getLoadedVersion());
    self::assertEquals($original_js_component_version, $old_version_item->getComponentVersion());

    $js_component = \Drupal::entityTypeManager()->getStorage(Component::ENTITY_TYPE_ID)->loadUnchanged($js_component_id);
    \assert($js_component instanceof ComponentInterface);
    self::assertNotEquals($js_component->getLoadedVersion(), $reference?->getLoadedVersion());

    $item_list->appendItem([
      'uuid' => '85fe2843-acac-4f17-b17b-0eeaa648ea2f',
      'component_id' => $js_component_id,
      'inputs' => [
        'name' => 'Mad Dog Morgan',
        'real_name' => 'John Owen',
        'age' => '35',
      ],
    ]);

    $new_version_item = $item_list->get(3);
    \assert($new_version_item instanceof ComponentTreeItem);
    $reference = $new_version_item->getComponent();
    self::assertNotEquals($original_js_component_version, $reference?->getLoadedVersion());
    self::assertNotEquals($original_js_component_version, $new_version_item->getComponentVersion());
    $active_version = $js_component->getActiveVersion();
    self::assertEquals($active_version, $reference?->getLoadedVersion());
    self::assertEquals($active_version, $new_version_item->getComponentVersion());

    // Finally, contrast the two for test clarity.
    self::assertSame($old_version_item->getComponentId(), $new_version_item->getComponentId());
    self::assertNotEquals($old_version_item->getComponentVersion(), $new_version_item->getComponentVersion());
    self::assertNotEquals($old_version_item->getComponent(), $new_version_item->getComponent());

    // Test that setting the version updates the component loaded.
    $old_version_item->set('component_version', $active_version);
    self::assertEquals($active_version, $old_version_item->getComponentVersion());
    self::assertEquals($active_version, $old_version_item->getComponent()?->getLoadedVersion());
  }

  public function testCalculateDependencies(): void {
    $uuid = $this->container->get('uuid');
    $type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $type->save();
    $this->createImageField('field_hero', 'node', 'article', storage_settings: [
      // @todo Remove once https://drupal.org/i/3513317 is fixed.
      // We cannot rely on the override because canvas module is not
      // yet installed so need to manually specify it here for testing sake.
      // @see \Drupal\canvas\Plugin\Field\FieldTypeOverride\ImageItemOverride::defaultStorageSettings
      'display_default' => TRUE,
    ]);

    $this->assertSame([], ComponentTreeItem::calculateDependencies(BaseFieldDefinition::create('component_tree')));
    $this->assertSame(
      [
        'config' => [
          'canvas.component.sdc.canvas_test_sdc.image',
          'canvas.component.sdc.canvas_test_sdc.my-cta',
          'field.field.node.article.field_hero',
          'image.style.canvas_parametrized_width',
          'node.type.article',
        ],
        'content' => [],
        'module' => [
          'file',
          'node',
        ],
        'theme' => [],
      ],
      ComponentTreeItem::calculateDependencies(BaseFieldDefinition::create('component_tree')
        ->setDefaultValue(
          [
            [
              'uuid' => $uuid->generate(),
              'component_id' => 'sdc.canvas_test_sdc.image',
              'inputs' => [
                'image' => [
                  'sourceType' => 'dynamic',
                  'expression' => 'ℹ︎␜entity:node:article␝field_hero␞␟{src↝entity␜␜entity:file␝uri␞␟value,alt↠alt,width↠width,height↠height}',
                ],
              ],
            ],
            [
              'uuid' => $uuid->generate(),
              'component_id' => 'sdc.canvas_test_sdc.my-cta',
              'inputs' => [
                'text' => 'hello, world!',
                'href' => [
                  'sourceType' => 'static:field_item:uri',
                  'value' => 'https://drupal.org',
                  'expression' => 'ℹ︎uri␟value',
                ],
              ],
            ],
            [
              'uuid' => $uuid->generate(),
              'component_id' => 'sdc.canvas_test_sdc.my-cta',
              'inputs' => [
                'text' => [
                  'sourceType' => 'dynamic',
                  'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
                ],
                'href' => [
                  'sourceType' => 'static:field_item:uri',
                  'value' => 'https://drupal.org',
                  'expression' => 'ℹ︎uri␟value',
                ],
              ],
            ],
            [
              'uuid' => $uuid->generate(),
              'component_id' => 'sdc.canvas_test_sdc.my-cta',
              'inputs' => [
                'text' => [
                  'sourceType' => 'dynamic',
                  'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
                ],
                'href' => [
                  'sourceType' => 'dynamic',
                  'expression' => 'ℹ︎␜entity:node:article␝field_hero␞␟entity␜␜entity:file␝uri␞␟value',
                ],
              ],
            ],
            [
              'uuid' => $uuid->generate(),
              'component_id' => 'sdc.canvas_test_sdc.image',
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
        ))
    );
  }

  public static function providerInvalidField(): array {
    $test_cases = static::getValidTreeTestCases();
    array_walk($test_cases, fn(array &$test_case) => $test_case[] = []);
    $test_cases = array_merge($test_cases, static::getInvalidTreeTestCases());
    $test_cases['invalid values using dynamic inputs'][] = [
      'field_canvas_test.0' => "The 'dynamic' prop source type must be absent.",
    ];
    $test_cases['invalid UUID, missing component_id key'][] = [
      'field_canvas_test.0.uuid' => 'This is not a valid UUID.',
      'field_canvas_test.0.component_id' => 'This value should not be blank.',
      'field_canvas_test.0.component_version' => 'This value should not be blank.',
    ];
    $test_cases['missing components, using dynamic inputs'][] = [
      'field_canvas_test.0.component_id' => "The 'canvas.component.sdc.sdc_test.missing' config does not exist.",
      'field_canvas_test.1.component_id' => "The 'canvas.component.sdc.sdc_test.missing-also' config does not exist.",
      'field_canvas_test.0' => "The 'dynamic' prop source type must be absent.",
      'field_canvas_test.1' => "The 'dynamic' prop source type must be absent.",
      'field_canvas_test.2' => "The 'dynamic' prop source type must be absent.",
    ];
    $test_cases['missing components, using only static inputs'][] = [
      'field_canvas_test.0.component_id' => "The 'canvas.component.sdc.sdc_test.missing' config does not exist.",
    ];
    $test_cases['inputs invalid, using dynamic inputs'][] = [
      \sprintf('field_canvas_test.0.inputs.%s.heading', self::UUID_DYNAMIC_STATIC_CARD_2) => 'The property heading is required.',
      'field_canvas_test.0' => "The 'dynamic' prop source type must be absent.",
      \sprintf('field_canvas_test.1.inputs.%s.heading', self::UUID_DYNAMIC_STATIC_CARD_3) => 'The property heading is required.',
      'field_canvas_test.1' => "The 'dynamic' prop source type must be absent.",
      'field_canvas_test.2' => "The 'dynamic' prop source type must be absent.",
    ];
    $test_cases['inputs invalid, using only static inputs'][] = [
      \sprintf('field_canvas_test.0.inputs.%s.heading', self::UUID_DYNAMIC_STATIC_CARD_2) => 'The property heading is required.',
    ];
    $test_cases['missing inputs key'][] = [
      \sprintf('field_canvas_test.0.inputs.%s', self::UUID_DYNAMIC_STATIC_CARD_2) => 'The required properties are missing.',
      \sprintf('field_canvas_test.1.inputs.%s', self::UUID_DYNAMIC_STATIC_CARD_3) => 'The required properties are missing.',
      \sprintf('field_canvas_test.2.inputs.%s', self::UUID_DYNAMIC_STATIC_CARD_4) => 'The required properties are missing.',
    ];
    $test_cases['non unique uuids'][] = [
      'field_canvas_test' => 'Not all component instance UUIDs in this component tree are unique.',
    ];
    $test_cases['invalid parent'][] = [
      'field_canvas_test.1.parent_uuid' => 'Invalid component tree item with UUID <em class="placeholder">e303dd88-9409-4dc7-8a8b-a31602884a94</em> references an invalid parent <em class="placeholder">6381352f-5b0a-4ca1-960d-a5505b37b27c</em>.',
    ];
    $test_cases['invalid slot'][] = [
      'field_canvas_test.1.slot' => 'Invalid component subtree. This component subtree contains an invalid slot name for component <em class="placeholder">sdc.canvas_test_sdc.props-slots</em>: <em class="placeholder">banana</em>. Valid slot names are: <em class="placeholder">the_body, the_footer, the_colophon</em>.',
    ];
    return $test_cases;
  }

  /**
   * @coversClass \Drupal\canvas\Plugin\Validation\Constraint\ValidComponentTreeItemConstraintValidator
   * @param array $field_values
   * @param array $expected_violations
   *
   * @dataProvider providerInvalidField
   */
  public function testInvalidField(array $field_values, array $expected_violations): void {
    $this->container->get('module_installer')->install(['canvas_test_config_node_article']);
    $node = Node::create([
      'title' => 'Test node',
      'type' => 'article',
      'field_canvas_test' => $field_values,
    ]);
    $violations = $node->validate();
    $this->assertSame($expected_violations, self::violationsToArray($violations));
  }

}
