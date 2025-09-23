<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource;

use Drupal\Component\Utility\Html;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\Controller\ClientServerConversionTrait;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\Pattern;
use Drupal\canvas\Plugin\Canvas\ComponentSource\BlockComponent;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Drupal\Tests\canvas\Traits\BlockComponentTreeSchemaUpdateTestTrait;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\CrawlerTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\canvas\Traits\SingleDirectoryComponentTreeTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\canvas_test_block\Plugin\Block\CanvasTestBlockInputSchemaChangePoc;
use Drupal\canvas_test_block_simulate_input_schema_change\Plugin\Block\SimulatedInputSchemaChangeBlock;

/**
 * Test explicit inputs can evolve as input schema & shape matching change.
 *
 * @group canvas
 */
final class ComponentInputsEvolutionTest extends KernelTestBase {

  use BlockComponentTreeSchemaUpdateTestTrait;
  use ContribStrictConfigSchemaTestTrait;
  use SingleDirectoryComponentTreeTestTrait;
  use GenerateComponentConfigTrait;
  use CiModulePathTrait;
  use CrawlerTrait;
  use ComponentTreeItemListInstantiatorTrait;
  use ClientServerConversionTrait;
  use ConstraintViolationsTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'media',
    'path',
    'file',
    'image',
    'link',
    'options',
    'system',
    'block',
    'datetime',
    'user',
    'canvas_test_block',
    'filter',
    'ckeditor5',
    'editor',
    'canvas_test_sdc',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $this->installSchema('user', 'users_data');
    $this->installConfig('canvas');
    $this->generateComponentConfig();
    // Set up a test user "bob"
    $this->setUpCurrentUser(['name' => 'bob', 'uid' => 2]);
  }

  /**
   * @see hook_storage_prop_shape_alter()
   * @covers \Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent::updateConfigEntity()
   * @covers \Drupal\canvas\ComponentSource\ComponentSourceBase::generateVersionHash()
   */
  public function testStorablePropShapeChanges(): void {
    $component = Component::load('sdc.canvas_test_sdc.my-cta');
    \assert($component instanceof ComponentInterface);
    self::assertEquals([
      'text' => 'string',
      'href' => 'link',
      'target' => 'list_string',
    ], \array_map(static fn (array $field) => $field['field_type'], $component->getSettings()['prop_field_definitions']));

    $uuid = \Drupal::service(UuidInterface::class);

    // Create an item for the component in its current form.
    $items = self::staticallyCreateDanglingComponentTreeItemList(\Drupal::typedDataManager());
    $first_uuid = $uuid->generate();
    $original_version = $component->getActiveVersion();
    self::assertSame([$original_version], $component->getVersions());
    $items->setValue([
      [
        'uuid' => $first_uuid,
        'component_id' => $component->id(),
        // Collapsed inputs with static defaults pinned to the active component
        // version.
        'inputs' => [
          'text' => 'mirror my melody',
          'href' => ['uri' => 'http://arachnophobia.com/', 'options' => []],
        ],
      ],
    ]);
    self::assertCount(0, $items->validate());

    // Creating a component of this type should set the `component_version`
    // field property and column to the active version.
    self::assertSame($original_version, $items->first()?->getComponentVersion());
    self::assertSame($original_version, $items->getValue()[0]['component_version']);

    // Converting to a client-side model should expand the plain inputs into
    // structured values.
    // @todo Simplify the client-side model in https://www.drupal.org/i/3528043
    $client_model = $items->getClientSideRepresentation();

    $expected_original_client_model = [
      'layout' => [
        [
          'uuid' => $first_uuid,
          'nodeType' => 'component',
          'type' => 'sdc.canvas_test_sdc.my-cta@' . $original_version,
          'slots' => [],
          'name' => NULL,
        ],
      ],
      'model' => [
        $first_uuid => [
          'source' => [
            'text' => [
              'sourceType' => 'static:field_item:string',
              'expression' => 'ℹ︎string␟value',
            ],
            'href' => [
              'sourceType' => 'static:field_item:link',
              'sourceTypeSettings' => [
                'instance' => [
                  'title' => \DRUPAL_DISABLED,
                ],
              ],
              'value' => [
                'uri' => 'http://arachnophobia.com/',
                'options' => [],
              ],
              'expression' => 'ℹ︎link␟url',
            ],
          ],
          'resolved' => [
            'text' => 'mirror my melody',
            'href' => 'http://arachnophobia.com/',
          ],
        ],
      ],
    ];
    self::assertEquals($expected_original_client_model, $client_model);

    // Now enable the 'canvas_test_storage_prop_shape_alter' module to change the
    // field type used for populating the cta1href (`format: uri`) prop.
    // @see \Drupal\canvas_test_storage_prop_shape_alter\Hook\CanvasTestStoragePropShapeAlterHooks::storagePropShapeAlter()
    \Drupal::service(ModuleInstallerInterface::class)
      ->install(['canvas_test_storage_prop_shape_alter']);
    $this->generateComponentConfig();
    $component = Component::load('sdc.canvas_test_sdc.my-cta');
    \assert($component instanceof ComponentInterface);
    $new_version = $component->getActiveVersion();
    self::assertNotEquals($original_version, $new_version);
    self::assertSame([$new_version, $original_version], $component->getVersions());
    self::assertEquals([
      'text' => 'string',
      'href' => 'uri',
      'target' => 'list_string',
    ], \array_map(static fn(array $field) => $field['field_type'], $component->getSettings()['prop_field_definitions']));

    $new_items = self::staticallyCreateDanglingComponentTreeItemList(\Drupal::typedDataManager());
    $second_uuid = $uuid->generate();
    $new_items->setValue([
      [
        'uuid' => $second_uuid,
        'component_id' => $component->id(),
        'inputs' => [
          'text' => 'mirror my melody',
          'href' => 'http://arachnophobia.com/',
        ],
      ],
    ]);
    self::assertCount(0, $new_items->validate());

    // Creating a component of this type should set the `component_version`
    // field property and column to the active version.
    self::assertSame($new_version, $new_items->first()?->getComponentVersion());
    self::assertSame($new_version, $new_items->getValue()[0]['component_version']);

    $new_client_model = $new_items->getClientSideRepresentation();

    self::assertEquals([
      'layout' => [
        [
          'uuid' => $second_uuid,
          'nodeType' => 'component',
          'type' => 'sdc.canvas_test_sdc.my-cta@' . $new_version,
          'slots' => [],
          'name' => NULL,
        ],
      ],
      'model' => [
        $second_uuid => [
          'source' => [
            'text' => [
              'sourceType' => 'static:field_item:string',
              'expression' => 'ℹ︎string␟value',
            ],
            'href' => [
              'sourceType' => 'static:field_item:uri',
              'expression' => 'ℹ︎uri␟value',
            ],
          ],
          'resolved' => [
            'text' => 'mirror my melody',
            'href' => 'http://arachnophobia.com/',
          ],
        ],
      ],
    ], $new_client_model);

    // Converting the old client model should still retain the reference to the
    // old version.
    $component_tree_item_list_values = self::convertClientToServer($client_model['layout'], $client_model['model']);
    \assert(\array_key_exists('component_version', $component_tree_item_list_values[0]));
    self::assertSame($original_version, $component_tree_item_list_values[0]['component_version']);
    // Create a new item list from this.
    $original_items = self::staticallyCreateDanglingComponentTreeItemList(\Drupal::typedDataManager());
    $original_items->setValue($component_tree_item_list_values);
    self::assertCount(0, $original_items->validate());
    // Should still equal the original model, even though the field type is now
    // different for the cta1href prop for new component instances: existing
    // component instances remain unchanged.
    // @todo Allow the content author to switch to the new field type in https://drupal.org/i/3463996
    self::assertEquals($expected_original_client_model, $original_items->getClientSideRepresentation());

    // If we uninstall the module, the Component should again point to the
    // original field type.
    \Drupal::service(ModuleInstallerInterface::class)->uninstall(['canvas_test_storage_prop_shape_alter']);
    $this->generateComponentConfig();
    $component = Component::load('sdc.canvas_test_sdc.my-cta');
    \assert($component instanceof ComponentInterface);
    $newest_version = $component->getActiveVersion();
    self::assertEquals($original_version, $newest_version);
    self::assertSame([$original_version, $new_version, $original_version], $component->getVersions());
    self::assertEquals([
      'text' => 'string',
      'href' => 'link',
      'target' => 'list_string',
    ], \array_map(static fn (array $field) => $field['field_type'], $component->getSettings()['prop_field_definitions']));

    $newest_items = self::staticallyCreateDanglingComponentTreeItemList(\Drupal::typedDataManager());
    $newest_items->setValue([
      [
        'uuid' => $uuid->generate(),
        'component_id' => $component->id(),
        'inputs' => [
          'text' => 'mirror my melody',
          'href' => ['uri' => 'http://arachnophobia.com/', 'options' => []],
        ],
      ],
    ]);
    self::assertCount(0, $newest_items->validate());

    // Creating a component of this type should set the `component_version`
    // field property and column to the active version.
    self::assertSame($original_version, $newest_items->first()?->getComponentVersion());
    self::assertSame($original_version, $newest_items->getValue()[0]['component_version']);
  }

  /**
   * @todo Refactor after https://www.drupal.org/project/drupal/issues/3521221 is in.
   */
  private static function blockUpdatePathSampleForCoreIssue3521221(array $block_plugin_settings): array {
    if (is_int($block_plugin_settings['foo']) || array_key_exists('change', $block_plugin_settings)) {
      throw new \LogicException('Nothing to do; ideally this would then not be called at all.');
    }

    // Update the `foo` key-value pair from string to integer.
    assert(is_string($block_plugin_settings['foo']));
    $block_plugin_settings['foo'] = match ($block_plugin_settings['foo']) {
      // Remap the old default to the new default.
      // @see \Drupal\canvas_test_block\Plugin\Block\CanvasTestBlockInputSchemaChangePoc::defaultConfiguration()
      // @see \Drupal\canvas_test_block_simulate_input_schema_change\Plugin\Block\SimulatedInputSchemaChangeBlock::defaultConfiguration()
      'bar' => 2,
      // Remap all other values to integer 1.
      default => 1,
    };
    // Add the new required `change` key-value pair.
    $block_plugin_settings['change'] = 'is necessary';
    return $block_plugin_settings;
  }

  private function renderBlockWithDefaultSettings(ComponentInterface $component): string {
    $inputs = [
      BlockComponent::EXPLICIT_INPUT_NAME => $component->getSettings()['default_settings'],
    ];
    $build = $component->getComponentSource()->renderComponent($inputs, 'some-uuid', FALSE);
    $document = Html::load($this->crawlerForRenderArray($build)->html());
    return trim($document->getElementsByTagName('div')[0]->textContent);
  }

  /**
   * Tests valid Block Plugin update: both logic and config schema can change.
   *
   * As there is no option for us, with the current tools, to execute a module update
   * that changes the schema, we can simulate it with 2 modules, one with the v1
   * of the schema, and others with the v2 of the schema.
   *
   * @covers \Drupal\canvas\Plugin\Canvas\ComponentSource\BlockComponent::getExplicitInputDefinitions()
   * @covers \Drupal\canvas\ComponentSource\ComponentSourceBase::generateVersionHash()
   *
   * @see \Drupal\canvas_test_block\Plugin\Block\CanvasTestBlockInputSchemaChangePoc::defaultConfiguration()
   * @see \Drupal\canvas_test_block_simulate_input_schema_change\Plugin\Block\SimulatedInputSchemaChangeBlock::defaultConfiguration()
   * @see \Drupal\canvas_test_block_simulate_input_schema_change\Hook\SimulatedInputSchemaChangeHooks
   */
  public function testBlockPluginUpdateConsequences(): void {
    // @see `type: block_settings`
    $block_settings_schema = \Drupal::service(TypedConfigManagerInterface::class)->createFromNameAndData('block_settings', []);
    assert($block_settings_schema instanceof Mapping);
    $generic_block_settings = $block_settings_schema->getRequiredKeys();

    // Before the update.
    $before = Component::load('block.canvas_test_block_input_schema_change_poc');
    assert($before instanceof Component);
    self::assertSame(CanvasTestBlockInputSchemaChangePoc::class, $before->getComponentSource()->getReferencedPluginClass());
    self::assertSame('7cc894b85e93a7d8', $before->getActiveVersion());
    self::assertSame(['7cc894b85e93a7d8'], $before->getVersions());
    self::assertSame(['foo' => 'bar'], array_diff_key($before->getSettings()['default_settings'], array_flip($generic_block_settings)));
    self::assertSame('Current foo value: bar', $this->renderBlockWithDefaultSettings($before));

    // Simulate update of the block plugin:
    // - config schema
    // - plugin class.
    $this->container->get(ModuleInstallerInterface::class)->install(['canvas_test_block_simulate_input_schema_change']);

    // After the update:
    // 1. new version
    // 2. updated default settings
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\BlockComponent::getExplicitInputDefinitions()
    $after = Component::load($before->id());
    assert($after instanceof Component);
    self::assertSame(SimulatedInputSchemaChangeBlock::class, $after->getComponentSource()->getReferencedPluginClass());
    self::assertSame('88c370526c14d185', $after->getActiveVersion());
    self::assertSame(['88c370526c14d185', '7cc894b85e93a7d8'], $after->getVersions());
    self::assertSame(['foo' => 2, 'change' => 'is scary'], array_diff_key($after->getSettings()['default_settings'], array_flip($generic_block_settings)));
    self::assertSame('Modified block! Current foo value: 2. Change … is scary.', $this->renderBlockWithDefaultSettings($after));

    // Simulate the Component config entity erroneously not having been updated:
    // validate the "before" Component config entity in the reality of the
    // updated codebase.
    self::assertSame([
      'active_version' => 'The version 7cc894b85e93a7d8 does not match the hash of the settings for this version, expected ec03b64ff4f992b9.',
      'versioned_properties.active.settings.default_settings' => "'change' is a required key because source_local_id is canvas_test_block_input_schema_change_poc (see config schema type block.settings.canvas_test_block_input_schema_change_poc).",
      'versioned_properties.active.settings.default_settings.foo' => [
        'The value you selected is not a valid choice.',
        'This value should be of the correct primitive type.',
      ],
    ], self::violationsToArray($before->getTypedData()->validate()));
  }

  /**
   * Tests invalid Block Plugin update: config schema is updated, logic is not.
   */
  public function testBrokenBlockPluginUpdate(): void {
    // @see \Drupal\canvas_test_block_simulate_input_schema_change\Hook\SimulatedInputSchemaChangeHooks::blockAlter()
    \Drupal::state()->set('canvas_test_block.allow_hook_block_alter', FALSE);
    $this->expectException(SchemaIncompleteException::class);
    $this->expectExceptionMessage('Schema errors for canvas.component.block.canvas_test_block_input_schema_change_poc with the following errors: 0 [active_version] The version ec03b64ff4f992b9 does not match the hash of the settings for this version, expected af78995aa8d4160e., 1 [versioned_properties.active.settings.default_settings] &#039;change&#039; is a required key because source_local_id is canvas_test_block_input_schema_change_poc (see config schema type block.settings.canvas_test_block_input_schema_change_poc)., 2 [versioned_properties.active.settings.default_settings.foo] The value you selected is not a valid choice.');
    $this->container->get(ModuleInstallerInterface::class)->install(['canvas_test_block_simulate_input_schema_change']);
  }

  /**
   * @return array<string, string>
   *   The text content of all rendered block component instances, keyed by UUID.
   */
  private function getTextOfAllRenderedBlockComponentInstances(ComponentTreeEntityInterface $component_tree_entity): array {
    Html::resetSeenIds();
    $build = $component_tree_entity->getComponentTree()->toRenderable($component_tree_entity);
    $rendered_block_components = $this->crawlerForRenderArray($build)->filter('[data-component-uuid], [id^=block-]');
    return array_combine(
      $rendered_block_components->each(fn ($node, $i) => $node->attr('data-component-uuid') ?? substr($node->attr('id'), strlen('block-'))),
      $rendered_block_components->each(fn ($node, $i) => $node->text()),
    );
  }

  /**
   * Tests existing component instances of a Block Plugin with an update path.
   *
   * Tests, for both content- and config-defined component trees:
   * 1. pre-update
   * 2. mid-update (code change + config schema change deployed, but existing
   *   component instances not yet updated)
   *   a. WITH backwards compatibility layer (or: no hard BC break)
   *   b. WITHOUT backwards compatibility layer (or: hard BC break)
   * 3. post-update (after update path has been applied)
   *
   * At all times should the component tree MUST continue to render, in the
   * worst case it should fall back to a fallback message informing the user of
   * render failure.
   *
   * @dataProvider getValidTreesForASchemaUpdate
   *
   * @see \Drupal\canvas_test_block_simulate_input_schema_change\Hook\SimulatedInputSchemaChangeHooks
   * @see \Drupal\canvas\Element\RenderSafeComponentContainer::handleComponentException()
   */
  public function testBlockPluginUpdatePath(
    array $component_tree,
    array $expected_pre_update_markup,
    array $expected_mid_update_markup_bc_layer,
    array $expected_mid_update_markup_bc_break,
    array $expected_post_update_markup,
    array $expected_post_update_violations,
    array $expected_post_update_component_tree,
  ): void {
    // A content-defined component tree.
    $page = Page::create([
      'title' => $this->randomString(),
      'components' => $component_tree,
    ]);
    $page->save();

    // A config-defined component tree.
    $pattern = Pattern::create([
      'id' => 'update_path_test_block',
      'label' => $this->randomString(),
    ])->setComponentTree($component_tree);
    $pattern->save();

    // Component instances work well BEFORE the module update.
    self::assertSame([], self::violationsToArray($page->getComponentTree()->validate()));
    self::assertSame($expected_pre_update_markup, self::getTextOfAllRenderedBlockComponentInstances($page));

    // Simulate a module update that brings an updated Block plugin class and
    // updated settings config schema.
    // @see ::testBlockPluginUpdateConsequences()
    self::assertCount(1, Component::load('block.canvas_test_block_input_schema_change_poc')?->getVersions() ?? []);
    $old_version = Component::load('block.canvas_test_block_input_schema_change_poc')?->getActiveVersion();
    \Drupal::state()->set('canvas_test_block.allow_hook_block_alter', TRUE);
    $this->container->get(ModuleInstallerInterface::class)->install(['canvas_test_block_simulate_input_schema_change']);
    self::assertCount(2, Component::load('block.canvas_test_block_input_schema_change_poc')?->getVersions());
    $new_version = Component::load('block.canvas_test_block_input_schema_change_poc')->getActiveVersion();

    // MID-update: AFTER the module update, BEFORE applying an update path: the
    // component tree contains instances with explicit inputs that are now
    // invalid.
    // Case a: Block Plugin logic update with BC layer.
    // TRICKY: load the Page entity anew to avoid the ComponentSource object for
    // the OLD version to continue to be used. This is an acceptable work-around
    // because in real workloads, it is impossible to experience Component
    // updates within a single request.
    $reloaded_page = Page::load($page->id());
    assert(!is_null($reloaded_page));
    self::assertSame($expected_post_update_violations, self::violationsToArray($reloaded_page->getComponentTree()->validate()));
    self::assertSame($expected_mid_update_markup_bc_layer, self::getTextOfAllRenderedBlockComponentInstances($reloaded_page));
    self::assertSame($expected_mid_update_markup_bc_layer, self::getTextOfAllRenderedBlockComponentInstances($page));
    // Case b: Block Plugin logic update without BC layer: HARD BC break.
    // @see \Drupal\canvas\Element\RenderSafeComponentContainer::handleComponentException()
    \Drupal::state()->set('canvas_test_block.schema_update_break', TRUE);
    self::assertSame($expected_mid_update_markup_bc_break, self::getTextOfAllRenderedBlockComponentInstances($reloaded_page));
    self::assertSame($expected_mid_update_markup_bc_break, self::getTextOfAllRenderedBlockComponentInstances($page));

    // Determine which component trees to update:
    // - which content entity revisions' component trees
    // - which config entities' component trees
    // @todo Move more of this logic into the ComponentAudit service in https://www.drupal.org/project/canvas/issues/3524751
    // @todo Add explicit support for component revisions to the ComponentAudit service in https://www.drupal.org/project/canvas/issues/3524751
    $expected_content_entity_revisions_to_update = !empty($expected_post_update_violations)
      ? ['canvas_page:1:1']
      : [];
    $expected_config_entities_to_update = !empty($expected_post_update_violations)
      ? ['canvas.pattern.update_path_test_block']
      : [];
    $audit = $this->container->get(ComponentAudit::class);
    assert($audit instanceof ComponentAudit);
    $updated_component = Component::load('block.canvas_test_block_input_schema_change_poc');
    // The new version of the component does not have any uses.
    self::assertSame([], $audit->getContentRevisionsUsingComponent($updated_component, [$new_version]));
    // Only the old version has uses that need to be updated.
    $content_entity_revisions_to_update = $audit->getContentRevisionsUsingComponent($updated_component, [$old_version]);
    self::assertSame($expected_config_entities_to_update, array_keys($audit->getConfigEntityDependenciesUsingComponent($updated_component, Pattern::ENTITY_TYPE_ID)));
    self::assertSame($expected_content_entity_revisions_to_update, array_map(
      self::contentEntityRevisionObjectToString(...),
      $content_entity_revisions_to_update,
    ));

    // Prove future viability of automatic update paths for components in
    // content-defined component trees.
    // @todo Add missing Drupal core infrastructure to allow updating plugin configuration in https://www.drupal.org/project/drupal/issues/3521221.
    $page_component_instances_to_update = $page->getComponentTree()->componentTreeItemsIterator(
      static fn (ComponentTreeItem $item) => $item->getComponentId() === 'block.canvas_test_block_input_schema_change_poc'
    );
    foreach ($page_component_instances_to_update as $component_tree_item) {
      $component_tree_item->setInput(self::blockUpdatePathSampleForCoreIssue3521221($component_tree_item->getInputs()));
      // Not valid until the component version is updated, too.
      self::assertNotEmpty(self::violationsToArray($component_tree_item->validate()));
      $component_tree_item->set('component_version', $new_version);
      self::assertEquals($new_version, $component_tree_item->getComponent()->getLoadedVersion());
      self::assertSame([], self::violationsToArray($component_tree_item->validate()));
    }

    // AFTER the update, the content-defined component tree:
    // 1. is valid
    // 2. contains exactly the expected values
    // 3. renders the expected markup
    self::assertSame([], self::violationsToArray($page->validate()));
    self::assertSame($expected_post_update_component_tree, array_map(
      function (ComponentTreeItem $item): array {
        $array = array_filter($item->toArray());
        $array['inputs'] = json_decode($array['inputs'], TRUE);
        return $array;
      },
      iterator_to_array($page->getComponentTree()->componentTreeItemsIterator()),
    ));
    $page->save();
    // Zero uses remain of the old version, every component instance is on the
    // new version.
    self::assertSame([], array_map(
      self::contentEntityRevisionObjectToString(...),
      $audit->getContentRevisionsUsingComponent($updated_component, [$old_version]),
    ));
    self::assertSame($expected_content_entity_revisions_to_update, array_map(
      self::contentEntityRevisionObjectToString(...),
      $audit->getContentRevisionsUsingComponent($updated_component, [$new_version]),
    ));
    // @phpstan-ignore-next-line
    self::assertSame($expected_post_update_markup, self::getTextOfAllRenderedBlockComponentInstances(Page::load(1)));

    // Prove future viability of automatic update paths for components in
    // config-defined component trees.
    // @todo Add missing Drupal core infrastructure to allow updating plugin configuration in https://www.drupal.org/project/drupal/issues/3521221.
    // @todo Abstract away the content- vs config-defined component tree differences in https://www.drupal.org/project/canvas/issues/3524751
    $pattern_component_instances_to_update = array_map(
      fn (ComponentTreeItem $item): string => $item->getUuid(),
      iterator_to_array($pattern->getComponentTree()->componentTreeItemsIterator(
        static fn (ComponentTreeItem $item) => $item->getComponentId() === 'block.canvas_test_block_input_schema_change_poc'
      ))
    );
    $raw_component_tree = $pattern->get('component_tree');
    foreach ($raw_component_tree as $key => $component_instance) {
      if (in_array($component_instance['uuid'], $pattern_component_instances_to_update, TRUE)) {
        $raw_component_tree[$key]['inputs'] = self::blockUpdatePathSampleForCoreIssue3521221($component_instance['inputs']);
        $raw_component_tree[$key]['component_version'] = '88c370526c14d185';
      }
    }
    $pattern->setComponentTree($raw_component_tree);

    // AFTER the update, the config-defined component tree:
    // 1. is valid
    // 2. contains exactly the expected values
    // 3. renders the expected markup
    self::assertSame([], self::violationsToArray($pattern->getTypedData()->validate()));
    self::assertSame($expected_post_update_component_tree, array_map(
      function (ComponentTreeItem $item): array {
        $array = array_filter($item->toArray());
        $array['inputs'] = json_decode($array['inputs'], TRUE);
        return $array;
      },
      iterator_to_array($pattern->getComponentTree()->componentTreeItemsIterator()),
    ));
    $pattern->save();
    self::assertSame($expected_post_update_markup, self::getTextOfAllRenderedBlockComponentInstances($pattern));
  }

  private static function contentEntityRevisionObjectToString(ContentEntityInterface $e): string {
    return sprintf("%s:%s:%s", $e->getEntityTypeId(), $e->id(), $e->getRevisionId());
  }

}
