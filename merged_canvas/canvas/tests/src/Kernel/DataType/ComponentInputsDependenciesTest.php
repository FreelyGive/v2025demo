<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\DataType;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * @covers \Drupal\canvas\Plugin\DataType\ComponentInputs::calculateDependencies()
 * @see \Drupal\Tests\canvas\Unit\DataType\ComponentInputsTest
 * @group canvas
 */
class ComponentInputsDependenciesTest extends KernelTestBase {

  use ComponentTreeItemListInstantiatorTrait;
  use ConstraintViolationsTestTrait;
  use ContribStrictConfigSchemaTestTrait;
  use GenerateComponentConfigTrait;
  use ImageFieldCreationTrait;
  use MediaTypeCreationTrait;

  private const TEST_IMAGE_UUID = 'd650b614-3219-4842-9a1f-f9976bdc20be';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'ckeditor5',
    'editor',
    'field',
    'filter',
    'text',
    'file',
    'image',
    'media',
    'user',
    'system',
    'path',
    'canvas',
    'link',
    'options',
    'canvas_test_sdc',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'canvas',
      'filter',
    ]);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $this->installSchema('file', ['file_usage']);
  }

  public function testCalculateDependencies(): void {
    $type = NodeType::create([
      'type' => 'alpha',
      'name' => 'Alpha',
    ]);
    $type->save();
    FieldStorageConfig::create([
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'alpha',
      'label' => 'Body',
    ])->save();
    $this->createImageField('field_hero', 'node', 'alpha', storage_settings: [
      // @todo Remove once https://drupal.org/i/3513317 is fixed.
      // We cannot rely on the override because canvas module is not
      // yet installed so need to manually specify it here for testing sake.
      // @see \Drupal\canvas\Plugin\Field\FieldTypeOverride\ImageItemOverride::defaultStorageSettings
      'display_default' => TRUE,
    ]);
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);

    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'alpha');
    $image_field_sample_value = ImageItem::generateSampleValue($field_definitions['field_hero']);
    \assert(\is_array($image_field_sample_value) && \array_key_exists('target_id', $image_field_sample_value));
    $hero_reference = Media::create([
      'bundle' => 'image',
      'name' => 'Hero image',
      'field_media_image' => $image_field_sample_value,
    ]);
    $hero_reference->save();

    $node = Node::create([
      'type' => 'alpha',
      'title' => 'Test title',
      'body' => [['value' => 'My test node body', 'summary' => 'Body Summary', 'format' => 'plain_text']],
      'field_hero' => $image_field_sample_value,
    ]);
    $node->save();

    $this->generateComponentConfig();

    $uuid = \Drupal::service(UuidInterface::class);
    $item_list = $this->createDanglingComponentTreeItemList();

    // Create test data.
    $item_list->appendItem([
      'uuid' => $uuid->generate(),
      'component_id' => 'sdc.canvas_test_sdc.heading',
      'inputs' => [
        'text' => 'Test Title',
        'element' => [
          // ⚠️ Note that this is NOT the field type that's in the Component config entity.
          'sourceType' => 'static:field_item:string',
          'value' => 'h1',
          'expression' => 'ℹ︎string␟value',
        ],
      ],
    ]);
    // Same as above, but now with collapsed values.
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::rawInputValueToPropSourceArray()
    // @see \Drupal\canvas\Plugin\DataType\ComponentInputs::getPropSources()
    $item_list->appendItem([
      'uuid' => $uuid->generate(),
      'component_id' => 'sdc.canvas_test_sdc.heading',
      'inputs' => [
        'text' => 'Test Title',
        'element' => 'h1',
      ],
    ]);
    $item_list->appendItem([
      'uuid' => $uuid->generate(),
      'component_id' => 'sdc.canvas_test_sdc.heading',
      'inputs' => [
        'heading' => [
          'sourceType' => 'dynamic',
          'expression' => 'ℹ︎␜entity:node:alpha␝body␞␟value',
        ],
      ],
    ]);
    $item_list->appendItem([
      'uuid' => self::TEST_IMAGE_UUID,
      'component_id' => 'sdc.canvas_test_sdc.image',
      'inputs' => [
        'image' => [
          'sourceType' => 'dynamic',
          'expression' => 'ℹ︎␜entity:node:alpha␝field_hero␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
        ],
      ],
    ]);
    // The component tree is valid, except that this test is using
    // DynamicPropSources. Those are not considered valid, but eventually might.
    // So: ignore these validation errors; they don't get in the way of testing
    // dependency calculation 👍
    self::assertSame([
      2 => "The 'dynamic' prop source type must be absent.",
      3 => "The 'dynamic' prop source type must be absent.",
    ], self::violationsToArray($item_list->validate()));
    self::assertTrue(in_array('dynamic', $item_list->getItemDefinition()->getConstraints()['ComponentTreeMeetRequirements']['inputs']['absence']));

    assert($item_list->get(0) instanceof ComponentTreeItem);
    assert($item_list->get(1) instanceof ComponentTreeItem);
    assert($item_list->get(2) instanceof ComponentTreeItem);
    assert($item_list->get(3) instanceof ComponentTreeItem);

    self::assertSame([], $item_list->get(0)->get('inputs')->calculateDependencies($node));

    self::assertSame([
      'module' => ['options'],
    ], $item_list->get(1)->get('inputs')->calculateDependencies($node));

    self::assertSame([
      'module' => ['node'],
      'config' => [
        'node.type.alpha',
        'field.field.node.alpha.body',
      ],
    ], $item_list->get(2)->get('inputs')->calculateDependencies($node));

    // Verify content dependencies if we have a valid entity.
    $file_entity = $hero_reference->get('field_media_image')->entity;
    assert($file_entity instanceof File);
    $file_uuid = $file_entity->get('uuid')->value;
    self::assertSame([
      'module' => [
        'node',
        'file',
        'file',
        'file',
        'node',
        'file',
        'node',
        'file',
        'node',
        'file',
      ],
      'config' => [
        'node.type.alpha',
        'field.field.node.alpha.field_hero',
        'image.style.canvas_parametrized_width',
        'node.type.alpha',
        'field.field.node.alpha.field_hero',
        'image.style.canvas_parametrized_width',
        'node.type.alpha',
        'field.field.node.alpha.field_hero',
        'image.style.canvas_parametrized_width',
        'node.type.alpha',
        'field.field.node.alpha.field_hero',
        'image.style.canvas_parametrized_width',
      ],
      'content' => [
        'file:file:' . $file_uuid,
      ],
    ], $item_list->get(3)->get('inputs')->calculateDependencies($node));

    // Prove it's possible to get the full list of content dependencies for a
    // given component tree; this is necessary for e.g. default content.
    $component_instances = iterator_to_array($item_list->componentTreeItemsIterator());
    $component_instance_deps_by_uuid = array_filter(array_combine(
      array_map(
        fn (ComponentTreeItem $item) => $item->getUuid(),
        $component_instances
      ),
      array_map(
        fn (ComponentTreeItem $item) => $item->calculateFieldItemValueDependencies($node)['content'] ?? [],
        $component_instances
      ),
    ));
    self::assertSame([
      self::TEST_IMAGE_UUID => [
        'file:file:' . $file_uuid,
      ],
    ], $component_instance_deps_by_uuid);
  }

}
