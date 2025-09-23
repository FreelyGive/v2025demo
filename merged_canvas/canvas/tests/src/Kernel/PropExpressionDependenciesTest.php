<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;
use Drupal\canvas\PropSource\StaticPropSource;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\canvas\Unit\PropExpressionTest;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\user\Entity\User;

/**
 * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::calculateDependencies()
 * @covers \Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression::calculateDependencies()
 * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression::calculateDependencies()
 * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression::calculateDependencies()
 * @covers \Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression::calculateDependencies()
 * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression::calculateDependencies()
 * @see \Drupal\Tests\canvas\Unit\PropExpressionTest
 * @group canvas
 */
class PropExpressionDependenciesTest extends KernelTestBase {

  use EntityReferenceFieldCreationTrait;
  use ImageFieldCreationTrait;
  use MediaTypeCreationTrait;

  public const NODE_1_UUID = '406ff859-f31b-4247-8b76-56cda80c06b9';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'system',
    'taxonomy',
    'text',
    'filter',
    'user',
    'file',
    'image',
    'media',
    'media_library',
    'views',
    // Ensure field type overrides are installed and hence testable.
    'canvas',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // @todo Core bug: this is missing config schema: `type: field.storage_settings.file_uri` does not exist! This is being fixed in https://www.drupal.org/project/drupal/issues/3324140.
    'field.storage.node.bar',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('user');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');

    $this->createMediaType('image', ['id' => 'image']);
    $this->createMediaType('image', ['id' => 'baby_photos']);
    $this->createMediaType('image', ['id' => 'vacation_photos']);

    // `article` node type.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();
    $this->createEntityReferenceField(
      'node',
      'article',
      'field_tags',
      'Tags',
      'taxonomy_term',
      'default',
      ['target_bundles' => ['tags']],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
    FieldStorageConfig::create([
      'field_name' => 'body',
      'type' => 'text',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();
    $this->createImageField('field_image', 'node', 'article');
    $this->createEntityReferenceField('node', 'article', 'yo_ho', 'Yo Ho', 'media', selection_handler_settings: [
      'target_bundles' => ['image'],
    ]);

    // `foo` node type.
    NodeType::create([
      'type' => 'foo',
      'name' => 'Foo',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'bar',
      'type' => 'file_uri',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'bar',
      'entity_type' => 'node',
      'bundle' => 'foo',
      'label' => 'The bar file URI field',
    ])->save();

    // `news` node type.
    NodeType::create([
      'type' => 'news',
      'name' => 'News',
    ])->save();
    $this->createImageField('field_photo', 'node', 'news');

    // `product` node type.
    NodeType::create([
      'type' => 'product',
      'name' => 'Product',
    ])->save();
    $this->createImageField('field_product_packaging_photo', 'node', 'product');

    User::create([
      'uuid' => 'some-user-uuid',
      'name' => 'user1',
      'mail' => 'user@localhost',
    ])->save();
    Vocabulary::create(['name' => 'Tags', 'vid' => 'tags'])->save();
    Term::create([
      'name' => 'term1',
      'vid' => 'tags',
    ])->save();
    Term::create([
      'name' => 'term2',
      'vid' => 'tags',
    ])->save();
    $image_file = File::create([
      'uuid' => 'some-image-uuid',
      'uri' => 'public://example.png',
      'filename' => 'example.png',
    ]);
    $image_file->save();
    $image_media = Media::create([
      'name' => 'Example image',
      'bundle' => 'image',
      'field_media_image' => $image_file,
      'uuid' => 'some-media-uuid',
    ]);
    $image_media->save();
    Node::create([
      'uuid' => self::NODE_1_UUID,
      'title' => 'dummy_title',
      'type' => 'article',
      'uid' => 1,
      'body' => [
        'format' => 'plain_text',
        'value' => $this->randomString(),
      ],
      'field_tags' => [
        ['target_id' => 1],
        ['target_id' => 2],
      ],
      'field_image' => [
        [
          'target_id' => 1,
          'alt' => 'test alt',
          'title' => 'test title',
          'width' => 10,
          'height' => 11,
        ],
      ],
      'yo_ho' => [
        'target_id' => $image_media->id(),
      ],
    ])->save();

    // `xyz` node type.
    NodeType::create([
      'type' => 'xyz',
      'name' => 'XYZ',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'abc',
      'type' => 'map',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'abc',
      'entity_type' => 'node',
      'bundle' => 'xyz',
      'label' => 'The XYZ map field',
    ])->save();
  }

  public function testCalculateDependencies(): void {
    $host_entity = Node::load(1);

    foreach (PropExpressionTest::provider() as $test_case_label => $case) {
      $expression = $case[1];
      assert($expression instanceof StructuredDataPropExpressionInterface);
      $expected_dependencies = $case[2];
      // Almost always, the content-aware dependencies are the same as the
      // content-unaware ones, just with the `content` key-value pair omitted,
      // if any.
      $expected_content_unaware_dependencies = $case[3] ?? $expected_dependencies;
      if (is_array($expected_content_unaware_dependencies)) {
        $expected_content_unaware_dependencies = array_diff_key($expected_content_unaware_dependencies, array_flip(['content']));
      }

      $test_case_precise_label = sprintf("%s (%s)", $test_case_label, (string) $expression);

      $entity_or_field = match(get_class($expression)) {
        FieldPropExpression::class, ReferenceFieldPropExpression::class, FieldObjectPropsExpression::class => $host_entity,
        FieldTypePropExpression::class, ReferenceFieldTypePropExpression::class, FieldTypeObjectPropsExpression::class => (function () use ($expression) {
          $field_item_list = StaticPropSource::generate($expression, 1)
            ->randomizeValue()->fieldItemList;
          if ($field_item_list instanceof FileFieldItemList) {
            // Ensure that expected content dependencies always use the hardcoded
            // file entity UUID.
            // @see ::setUp()
            assert($field_item_list[0] instanceof FieldItemInterface);
            $field_item_list[0]->get('target_id')->setValue(1);
          }
          return $field_item_list;
        })(),
      };

      if ($expected_dependencies instanceof \Exception) {
        try {
          $expression->calculateDependencies($entity_or_field);
          self::fail('Exception expected.');
        }
        catch (\Exception $e) {
          self::assertSame(get_class($expected_dependencies), get_class($e));
          self::assertSame($expected_dependencies->getMessage(), $e->getMessage(), $test_case_precise_label);
        }
        continue;
      }

      // When calculating dependencies for a prop expression *with* a valid
      // entity or field item list, all expected dependencies should be present.
      self::assertSame($expected_dependencies, $expression->calculateDependencies($entity_or_field), $test_case_precise_label);

      // When calculating dependencies for a prop expression *without* that, no
      // `content` dependencies (if any) should be present, because it is
      // impossible for just an expression to reference content entities.
      // (This is the case when evaluating for example a prop expression used in
      // a DynamicPropSource in a ContentTemplate: the content template applies
      // to many possible host entities, not any single one, so its
      // DynamicPropSources cannot possibly depend on any content entities.)
      self::assertSame($expected_content_unaware_dependencies, $expression->calculateDependencies(NULL), $test_case_precise_label);
    }
  }

}
