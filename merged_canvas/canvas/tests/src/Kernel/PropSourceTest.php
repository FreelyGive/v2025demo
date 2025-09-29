<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\BooleanCheckboxWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\UriWidget;
use Drupal\Core\File\FileExists;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDatelistWidget;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDefaultWidget;
use Drupal\canvas\Plugin\ComponentPluginManager;
use Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;
use Drupal\canvas\PropSource\AdaptedPropSource;
use Drupal\canvas\PropSource\DefaultRelativeUrlPropSource;
use Drupal\canvas\PropSource\DynamicPropSource;
use Drupal\canvas\PropSource\PropSource;
use Drupal\canvas\PropSource\StaticPropSource;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\canvas\Kernel\Traits\VfsPublicStreamUrlTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * @coversDefaultClass \Drupal\canvas\PropSource\PropSource
 * @group canvas
 */
class PropSourceTest extends KernelTestBase {

  private const FILE_UUID1 = 'a461c159-039a-4de2-96e5-07d1112105df';
  private const FILE_UUID2 = '792ea357-71d6-45fa-a12b-78d029edbe4c';
  private const IMAGE_MEDIA_UUID1 = '83b145bb-d8c3-4410-bbd6-fdcd06e27c29';
  private const IMAGE_MEDIA_UUID2 = '93b145bb-d8c3-4410-bbd6-fdcd06e27c29';
  private const TEST_MEDIA = '43b145bb-d8c3-4410-bbd6-fdcd06e27c29';

  use ContribStrictConfigSchemaTestTrait;
  use ImageFieldCreationTrait;
  use MediaTypeCreationTrait;
  use NodeCreationTrait;
  use TestFileCreationTrait;
  use VfsPublicStreamUrlTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'field',
    'file',
    'image',
    'node',
    'user',
    'datetime',
    'datetime_range',
    'media',
    'media_library',
    'media_test_source',
    'system',
    'media',
    'views',
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
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installEntitySchema('media');

    $this->createMediaType('image', ['id' => 'image']);
    $this->createMediaType('image', ['id' => 'anything_is_possible']);
    // @see \Drupal\media_test_source\Plugin\media\Source\Test
    $this->createMediaType('test', ['id' => 'image_but_not_image_media_source']);

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $file_uri = 'public://image-2.jpg';
    if (!\file_exists($file_uri)) {
      $file_system->copy(\Drupal::root() . '/core/tests/fixtures/files/image-2.jpg', PublicStream::basePath(), FileExists::Replace);
    }
    $file1 = File::create([
      'uuid' => self::FILE_UUID1,
      'uri' => $file_uri,
      'status' => 1,
    ]);
    $file1->save();
    $file_uri = 'public://image-3.jpg';
    if (!\file_exists($file_uri)) {
      $file_system->copy(\Drupal::root() . '/core/tests/fixtures/files/image-3.jpg', PublicStream::basePath(), FileExists::Replace);
    }
    $file2 = File::create([
      'uuid' => self::FILE_UUID2,
      'uri' => $file_uri,
      'status' => 1,
    ]);
    $file2->save();
    $this->installEntitySchema('media');
    $image1 = Media::create([
      'uuid' => self::IMAGE_MEDIA_UUID1,
      'bundle' => 'image',
      'name' => 'Amazing image',
      'field_media_image' => [
        [
          'target_id' => $file1->id(),
          'alt' => 'An image so amazing that to gaze upon it would melt your face',
          'title' => 'This is an amazing image, just look at it and you will be amazed',
        ],
      ],
    ]);
    $image1->save();
    $image2 = Media::create([
      'uuid' => self::IMAGE_MEDIA_UUID2,
      'bundle' => 'anything_is_possible',
      'name' => 'amazing',
      'field_media_image_1' => [
        [
          'target_id' => $file2->id(),
          'alt' => 'amazing',
          'title' => 'amazing',
        ],
      ],
    ]);
    $image2->save();
    $test_media = Media::create([
      'uuid' => self::TEST_MEDIA,
      'bundle' => 'image_but_not_image_media_source',
      'name' => 'contrived example',
      'field_media_test' => [
        'value' => 'Jack is awesome!',
      ],
    ]);
    $test_media->save();

    // Fixate the private key & hash salt to get predictable `itok`.
    $this->container->get('state')->set('system.private_key', 'dynamic_image_style_private_key');
    $settings_class = new \ReflectionClass(Settings::class);
    $instance_property = $settings_class->getProperty('instance');
    $settings = new Settings([
      'hash_salt' => 'dynamic_image_style_hash_salt',
    ]);
    $instance_property->setValue(NULL, $settings);
  }

  /**
   * @coversClass \Drupal\canvas\PropSource\StaticPropSource
   * @dataProvider providerStaticPropSource
   */
  public function testStaticPropSource(
    string $sourceType,
    array|null $sourceTypeSettings,
    mixed $value,
    string $expression,
    string $expected_json_representation,
    array|null $field_widgets,
    mixed $expected_user_value,
    string $expected_prop_expression,
    array $expected_dependencies,
  ): void {
    // @phpstan-ignore-next-line
    $prop_source_example = StaticPropSource::parse([
      'sourceType' => $sourceType,
      'value' => $value,
      'expression' => $expression,
      'sourceTypeSettings' => $sourceTypeSettings,
    ]);
    // First, get the string representation and parse it back, to prove
    // serialization and deserialization works.
    $json_representation = (string) $prop_source_example;
    $this->assertSame($expected_json_representation, $json_representation);
    $decoded_representation = json_decode($json_representation, TRUE);
    $prop_source_example = PropSource::parse($decoded_representation);
    $this->assertInstanceOf(StaticPropSource::class, $prop_source_example);
    // The contained information read back out.
    $this->assertSame($sourceType, $prop_source_example->getSourceType());
    /** @var class-string $expected_prop_expression */
    $this->assertInstanceOf($expected_prop_expression, StructuredDataPropExpression::fromString($prop_source_example->asChoice()));
    self::assertSame($expected_dependencies, $prop_source_example->calculateDependencies());
    // - generate a widget to edit the stored value — using the default widget
    //   or a specified widget.
    // @see \Drupal\canvas\Entity\Component::$defaults
    \assert(is_array($field_widgets));
    // Ensure we always test the default widget.
    \assert(isset($field_widgets[NULL]));
    // Ensure an unknown widget type is handled gracefully.
    $field_widgets['not_real'] = $field_widgets[NULL];
    foreach ($field_widgets as $widget_type => $expected_widget_class) {
      $this->assertInstanceOf($expected_widget_class, $prop_source_example->getWidget('irrelevant-for-test', 'irrelevant-for-test', 'irrelevant-for-test', $this->randomString(), $widget_type));
    }
    if (NULL === $value) {
      $this->assertNull($expected_user_value);
      // Do not continue testing if there is no values.
      return;
    }

    try {
      StaticPropSource::isMinimalRepresentation($decoded_representation);
    }
    catch (\LogicException) {
      $this->fail("Not a minimal representation: $json_representation.");
    }
    $this->assertSame($value, $prop_source_example->getValue());
    // Test the functionality of a StaticPropSource:
    // - evaluate it to populate an SDC prop
    if (isset($expected_user_value['src'])) {
      // Make it easier to write expectations containing root-relative URLs
      // pointing somewhere into the site-specific directory.
      $expected_user_value['src'] = str_replace('::SITE_DIR_BASE_URL::', \base_path() . $this->siteDirectory, $expected_user_value['src']);
      $expected_user_value['src'] = str_replace(UrlHelper::encodePath('::SITE_DIR_BASE_URL::'), UrlHelper::encodePath(\base_path() . $this->siteDirectory), $expected_user_value['src']);
    }
    if (is_array($expected_user_value) && array_is_list($expected_user_value)) {
      foreach (array_keys($expected_user_value) as $i) {
        if (isset($expected_user_value[$i]['src'])) {
          // Make it easier to write expectations containing root-relative URLs
          // pointing somewhere into the site-specific directory.
          $expected_user_value[$i]['src'] = str_replace('::SITE_DIR_BASE_URL::', \base_path() . $this->siteDirectory, $expected_user_value[$i]['src']);
          $expected_user_value[$i]['src'] = str_replace(UrlHelper::encodePath('::SITE_DIR_BASE_URL::'), UrlHelper::encodePath(\base_path() . $this->siteDirectory), $expected_user_value[$i]['src']);
        }
      }
    }
    $this->assertSame($expected_user_value, $prop_source_example->evaluate(User::create([]), is_required: TRUE));
    // - the field type's item's raw value is minimized if it is single-property
    $this->assertSame($value, $prop_source_example->getValue());
  }

  public static function providerStaticPropSource(): \Generator {
    yield "scalar shape, field type=string, cardinality=1" => [
      'sourceType' => 'static:field_item:string',
      'sourceTypeSettings' => NULL,
      'value' => 'Hello, world!',
      'expression' => 'ℹ︎string␟value',
      'expected_json_representation' => '{"sourceType":"static:field_item:string","value":"Hello, world!","expression":"ℹ︎string␟value"}',
      'field_widgets' => [
        NULL => StringTextfieldWidget::class,
        'string_textfield' => StringTextfieldWidget::class,
        'string_textarea' => StringTextfieldWidget::class,
      ],
      'expected_user_value' => 'Hello, world!',
      'expected_prop_expression' => FieldTypePropExpression::class,
      'expected_dependencies' => [],
    ];
    yield "scalar shape, field type=uri, cardinality=1" => [
      'sourceType' => 'static:field_item:uri',
      'sourceTypeSettings' => NULL,
      'value' => 'https://drupal.org',
      'expression' => 'ℹ︎uri␟value',
      'expected_json_representation' => '{"sourceType":"static:field_item:uri","value":"https:\/\/drupal.org","expression":"ℹ︎uri␟value"}',
      'field_widgets' => [
        NULL => UriWidget::class,
        'uri' => UriWidget::class,
      ],
      'expected_user_value' => 'https://drupal.org',
      'expected_prop_expression' => FieldTypePropExpression::class,
      'expected_dependencies' => [],
    ];
    yield "scalar shape, field type=boolean, cardinality=1" => [
      'sourceType' => 'static:field_item:boolean',
      'sourceTypeSettings' => NULL,
      'value' => TRUE,
      'expression' => 'ℹ︎boolean␟value',
      'expected_json_representation' => '{"sourceType":"static:field_item:boolean","value":true,"expression":"ℹ︎boolean␟value"}',
      'field_widgets' => [
        NULL => BooleanCheckboxWidget::class,
        'boolean_checkbox' => BooleanCheckboxWidget::class,
      ],
      'expected_user_value' => TRUE,
      'expected_prop_expression' => FieldTypePropExpression::class,
      'expected_dependencies' => [],
    ];
    // A simple (expression targeting a simple prop) array example (with
    // cardinality specified, rather than the default of `cardinality=1`).
    yield "scalar shape, field type=integer, cardinality=5" => [
      'sourceType' => 'static:field_item:integer',
      'sourceTypeSettings' => [
        'cardinality' => 5,
      ],
      'value' => [
        20,
        06,
        1,
        88,
        92,
      ],
      'expression' => 'ℹ︎integer␟value',
      'expected_json_representation' => '{"sourceType":"static:field_item:integer","value":[20,6,1,88,92],"expression":"ℹ︎integer␟value","sourceTypeSettings":{"cardinality":5}}',
      'field_widgets' => [
        NULL => NumberWidget::class,
        'number' => NumberWidget::class,
      ],
      'expected_user_value' => [
        20,
        06,
        1,
        88,
        92,
      ],
      'expected_prop_expression' => FieldTypePropExpression::class,
      'expected_dependencies' => [],
    ];
    yield "object shape, daterange field, cardinality=1" => [
      'sourceType' => 'static:field_item:daterange',
      'sourceTypeSettings' => NULL,
      'value' => [
        'value' => '2020-04-16T00:00',
        'end_value' => '2024-07-10T10:24',
      ],
      'expression' => 'ℹ︎daterange␟{start↠value,stop↠end_value}',
      'expected_json_representation' => '{"sourceType":"static:field_item:daterange","value":{"value":"2020-04-16T00:00","end_value":"2024-07-10T10:24"},"expression":"ℹ︎daterange␟{start↠value,stop↠end_value}"}',
      'field_widgets' => [
        NULL => DateRangeDefaultWidget::class,
        'daterange_default' => DateRangeDefaultWidget::class,
        'daterange_datelist' => DateRangeDatelistWidget::class,
      ],
      'expected_user_value' => [
        'start' => '2020-04-16T00:00',
        'stop' => '2024-07-10T10:24',
      ],
      'expected_prop_expression' => FieldTypeObjectPropsExpression::class,
      'expected_dependencies' => [
        'module' => [
          'datetime_range',
        ],
      ],
    ];
    // A complex (expression targeting multiple props) array example (with
    // cardinality specified, rather than the default of `cardinality=1`).
    yield "object shape, daterange field, cardinality=UNLIMITED" => [
      'sourceType' => 'static:field_item:daterange',
      'sourceTypeSettings' => [
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      ],
      'value' => [
        [
          'value' => '2020-04-16T00:00',
          'end_value' => '2024-07-10T10:24',
        ],
        [
          'value' => '2020-04-16T00:00',
          'end_value' => '2024-09-26T11:31',
        ],
      ],
      'expression' => 'ℹ︎daterange␟{start↠value,stop↠end_value}',
      'expected_json_representation' => '{"sourceType":"static:field_item:daterange","value":[{"value":"2020-04-16T00:00","end_value":"2024-07-10T10:24"},{"value":"2020-04-16T00:00","end_value":"2024-09-26T11:31"}],"expression":"ℹ︎daterange␟{start↠value,stop↠end_value}","sourceTypeSettings":{"cardinality":-1}}',
      'field_widgets' => [
        NULL => DateRangeDefaultWidget::class,
        'daterange_default' => DateRangeDefaultWidget::class,
        'daterange_datelist' => DateRangeDatelistWidget::class,
      ],
      'expected_user_value' => [
        [
          'start' => '2020-04-16T00:00',
          'stop' => '2024-07-10T10:24',
        ],
        [
          'start' => '2020-04-16T00:00',
          'stop' => '2024-09-26T11:31',
        ],
      ],
      'expected_prop_expression' => FieldTypeObjectPropsExpression::class,
      'expected_dependencies' => [
        'module' => [
          'datetime_range',
        ],
      ],
    ];
    yield "complex empty example with entity_reference" => [
      'sourceType' => 'static:field_item:entity_reference',
      'sourceTypeSettings' => [
        'storage' => ['target_type' => 'media'],
        'instance' => [
          'handler' => 'default:media',
          'handler_settings' => [
            'target_bundles' => ['image' => 'image'],
          ],
        ],
      ],
      'value' => NULL,
      'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
      'expected_json_representation' => '{"sourceType":"static:field_item:entity_reference","value":null,"expression":"ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}","sourceTypeSettings":{"storage":{"target_type":"media"},"instance":{"handler":"default:media","handler_settings":{"target_bundles":{"image":"image"}}}}}',
      'field_widgets' => [
        NULL => EntityReferenceAutocompleteWidget::class,
        'media_library_widget' => MediaLibraryWidget::class,
      ],
      'expected_user_value' => NULL,
      'expected_prop_expression' => FieldTypeObjectPropsExpression::class,
      'expected_dependencies' => [
        'config' => [
          'field.field.media.image.field_media_image',
          'image.style.canvas_parametrized_width',
          'media.type.image',
        ],
        'content' => [],
        'module' => [
          'file',
          'media',
        ],
      ],
    ];
    yield "complex non-empty example with entity_reference and multiple target bundles but same field name" => [
      'sourceType' => 'static:field_item:entity_reference',
      'sourceTypeSettings' => [
        'cardinality' => 5,
        'storage' => ['target_type' => 'media'],
        'instance' => [
          'handler' => 'default:media',
          'handler_settings' => [
            'target_bundles' => [
              'image' => 'image',
              'anything_is_possible' => 'anything_is_possible',
              'image_but_not_image_media_source' => 'image_but_not_image_media_source',
            ],
          ],
        ],
      ],
      'value' => [['target_id' => 2], ['target_id' => 1], ['target_id' => 3]],
      'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:anything_is_possible|image|image_but_not_image_media_source␝field_media_image_1|field_media_image|field_media_test␞␟src_with_alternate_widths|src_with_alternate_widths|value,alt↝entity␜␜entity:media:anything_is_possible|image|image_but_not_image_media_source␝field_media_image_1|field_media_image|field_media_test␞␟alt|alt|␀,width↝entity␜␜entity:media:anything_is_possible|image|image_but_not_image_media_source␝field_media_image_1|field_media_image|field_media_test␞␟width|width|␀,height↝entity␜␜entity:media:anything_is_possible|image|image_but_not_image_media_source␝field_media_image_1|field_media_image|field_media_test␞␟height|height|␀}',
      'expected_json_representation' => '{"sourceType":"static:field_item:entity_reference","value":[{"target_id":2},{"target_id":1},{"target_id":3}],"expression":"ℹ︎entity_reference␟{src↝entity␜␜entity:media:anything_is_possible|image|image_but_not_image_media_source␝field_media_image_1|field_media_image|field_media_test␞␟src_with_alternate_widths|src_with_alternate_widths|value,alt↝entity␜␜entity:media:anything_is_possible|image|image_but_not_image_media_source␝field_media_image_1|field_media_image|field_media_test␞␟alt|alt|␀,width↝entity␜␜entity:media:anything_is_possible|image|image_but_not_image_media_source␝field_media_image_1|field_media_image|field_media_test␞␟width|width|␀,height↝entity␜␜entity:media:anything_is_possible|image|image_but_not_image_media_source␝field_media_image_1|field_media_image|field_media_test␞␟height|height|␀}","sourceTypeSettings":{"storage":{"target_type":"media"},"instance":{"handler":"default:media","handler_settings":{"target_bundles":{"image":"image","anything_is_possible":"anything_is_possible","image_but_not_image_media_source":"image_but_not_image_media_source"}}},"cardinality":5}}',
      'field_widgets' => [
        NULL => EntityReferenceAutocompleteWidget::class,
        'media_library_widget' => MediaLibraryWidget::class,
      ],
      'expected_user_value' => [
        [
          'src' => '::SITE_DIR_BASE_URL::/files/image-3.jpg?alternateWidths=' . UrlHelper::encodePath('::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--{width}/public/image-3.jpg.webp?itok=spSF5vvd'),
          'alt' => 'amazing',
          'width' => 80,
          'height' => 60,
        ],
        [
          'src' => '::SITE_DIR_BASE_URL::/files/image-2.jpg?alternateWidths=' . UrlHelper::encodePath('::SITE_DIR_BASE_URL::/files/styles/canvas_parametrized_width--{width}/public/image-2.jpg.webp?itok=SnSVAYVj'),
          'alt' => 'An image so amazing that to gaze upon it would melt your face',
          'width' => 80,
          'height' => 60,
        ],
        [
          'src' => 'Jack is awesome!',
        ],
      ],
      'expected_prop_expression' => FieldTypeObjectPropsExpression::class,
      'expected_dependencies' => [
        'config' => [
          'field.field.media.anything_is_possible.field_media_image_1',
          'field.field.media.image.field_media_image',
          'field.field.media.image_but_not_image_media_source.field_media_test',
          'image.style.canvas_parametrized_width',
          'media.type.anything_is_possible',
          'media.type.image',
          'media.type.image_but_not_image_media_source',
        ],
        'content' => [
          'file:file:' . self::FILE_UUID2,
          'file:file:' . self::FILE_UUID1,
          'media:anything_is_possible:' . self::IMAGE_MEDIA_UUID2,
          'media:image:' . self::IMAGE_MEDIA_UUID1,
          'media:image_but_not_image_media_source:' . self::TEST_MEDIA,
        ],
        'module' => [
          'file',
          'media',
        ],
      ],
    ];
  }

  /**
   * @coversClass \Drupal\canvas\PropSource\DynamicPropSource
   * @dataProvider providerDynamicPropSource
   */
  public function testDynamicPropSource(
    string $expression,
    string $expected_json_representation,
    string $expected_expression_class,
    mixed $expected_evaluation_with_user_host_entity,
    mixed $expected_evaluation_with_node_host_entity,
    array $expected_dependencies_expression_only,
    array $expected_dependencies_with_host_entity,
  ): void {
    // For testing expressions relying on users.
    $this->installEntitySchema('user');
    $user = User::create([
      'uuid' => '881261cd-c9e2-4dcd-b0a8-1efa2e319a13',
      'name' => 'John Doe',
    ]);
    $user->save();

    // For testing expressions relying on nodes.
    $this->installEntitySchema('node');
    NodeType::create(['type' => 'page', 'name' => 'page'])->save();
    $this->createImageField('field_image', 'node', 'page');
    $node = $this->createNode(['uid' => $user->id(), 'field_image' => ['target_id' => 1]]);

    // For testing expressions relying on multiple bundles of the `node` entity
    // type.
    NodeType::create(['type' => 'bio', 'name' => 'biography'])->save();
    $this->createImageField('field_photo', 'node', 'bio');
    $node2 = $this->createNode(['uid' => $user->id(), 'type' => 'bio', 'field_photo' => ['target_id' => 2]]);

    $original = DynamicPropSource::parse([
      'sourceType' => 'dynamic',
      'expression' => $expression,
    ]);
    // First, get the string representation and parse it back, to prove
    // serialization and deserialization works.
    $json_representation = (string) $original;
    $this->assertSame($expected_json_representation, $json_representation);
    $parsed = PropSource::parse(json_decode($json_representation, TRUE));
    $this->assertInstanceOf(DynamicPropSource::class, $parsed);
    // The contained information read back out.
    $this->assertSame('dynamic', $parsed->getSourceType());
    // @phpstan-ignore-next-line argument.type
    $this->assertInstanceOf($expected_expression_class, StructuredDataPropExpression::fromString($parsed->asChoice()));

    // Test the functionality of a DynamicPropSource:
    $parsed_expression = StructuredDataPropExpression::fromString($expression);
    $correct_host_entity_type = match (get_class($parsed_expression)) {
      FieldPropExpression::class, FieldObjectPropsExpression::class => $parsed_expression->entityType->getEntityTypeId(),
      ReferenceFieldPropExpression::class => $parsed_expression->referencer->entityType->getEntityTypeId(),
      default => throw new \LogicException(),
    };
    // - evaluate it to populate an SDC prop using a `user` host entity
    try {
      $result = $parsed->evaluate($user, is_required: TRUE);
      if ($expected_evaluation_with_user_host_entity === \DomainException::class) {
        self::fail('Should throw an exception.');
      }
      else {
        self::assertSame($expected_evaluation_with_user_host_entity, $result);
      }
    }
    catch (\DomainException $e) {
      self::assertSame($expected_evaluation_with_user_host_entity, \DomainException::class);
      self::assertSame(sprintf("`%s` is an expression for entity type `%s`, but the provided entity is of type `user`.", (string) $parsed_expression, $correct_host_entity_type), $e->getMessage());
    }
    // - evaluate it to populate an SDC prop using a `node` host entity
    try {
      $result = $parsed->evaluate($node, is_required: TRUE);
      if ($expected_evaluation_with_node_host_entity === \DomainException::class) {
        self::fail('Should throw an exception.');
      }
      else {
        // TRICKY: this one test case is hard to parametrize using a data
        // provider, see the more precise/expansive assertions at the end of the
        // test method.
        if ($expression !== 'ℹ︎␜entity:node:page|bio␝field_photo|field_image␞␟srcset_candidate_uri_template|src_with_alternate_widths') {
          self::assertSame($expected_evaluation_with_node_host_entity, $result);
        }
      }
    }
    catch (\DomainException $e) {
      self::assertSame($expected_evaluation_with_node_host_entity, \DomainException::class);
      self::assertSame(sprintf("`%s` is an expression for entity type `%s`, but the provided entity is of type `node`.", (string) $parsed_expression, $correct_host_entity_type), $e->getMessage());
    }

    // - calculate its dependencies
    $this->assertSame($expected_dependencies_expression_only, $parsed->calculateDependencies());
    $correct_host_entity = match ($correct_host_entity_type) {
      'user' => $user,
      'node' => $node,
      default => throw new \LogicException(),
    };
    $this->assertSame($expected_dependencies_with_host_entity, $parsed->calculateDependencies($correct_host_entity));

    if ($expression === 'ℹ︎␜entity:node:page|bio␝field_photo|field_image␞␟srcset_candidate_uri_template|src_with_alternate_widths') {
      // For the "bio" node, expect `image-2` and an `alternateWidths` query
      // string (NOT: a URI template).
      $this->assertStringContainsString('image-2', $parsed->evaluate($node, is_required: TRUE));
      $this->assertStringContainsString('?alternateWidths=', $parsed->evaluate($node, is_required: TRUE));
      $this->assertStringNotContainsString('{width}', $parsed->evaluate($node, is_required: TRUE));
      // For the "bio" node, expect `image-3` and a URI template (NOT: an
      // `alternateWidths` query string).
      $this->assertStringContainsString('image-3', $parsed->evaluate($node2, is_required: TRUE));
      $this->assertStringContainsString('{width}', $parsed->evaluate($node2, is_required: TRUE));
      $this->assertStringNotContainsString('?alternateWidths=', $parsed->evaluate($node2, is_required: TRUE));

      // The expression in the context of node 2 (a `bio` node), which surfaces
      // no `content` dependencies because the `srcset_candidate_uri_template`
      // property does not provide such a dependency
      // @see \Drupal\canvas\TypedData\ImageDerivativeWithParametrizedWidth
      $this->assertSame($expected_dependencies_expression_only, $parsed->calculateDependencies($node2));
    }
  }

  public static function providerDynamicPropSource(): \Generator {
    yield "simple: FieldPropExpression" => [
      'expression' => 'ℹ︎␜entity:user␝name␞␟value',
      'expected_json_representation' => '{"sourceType":"dynamic","expression":"ℹ︎␜entity:user␝name␞␟value"}',
      'expected_expression_class' => FieldPropExpression::class,
      'expected_evaluation_with_user_host_entity' => 'John Doe',
      'expected_evaluation_with_node_host_entity' => \DomainException::class,
      'expected_dependencies_expression_only' => ['module' => ['user']],
      'expected_dependencies_with_host_entity' => ['module' => ['user']],
    ];

    yield "entity reference: ReferenceFieldPropExpression" => [
      'expression' => 'ℹ︎␜entity:node:page␝uid␞␟entity␜␜entity:user␝name␞␟value',
      'expected_json_representation' => '{"sourceType":"dynamic","expression":"ℹ︎␜entity:node:page␝uid␞␟entity␜␜entity:user␝name␞␟value"}',
      'expected_expression_class' => ReferenceFieldPropExpression::class,
      'expected_evaluation_with_user_host_entity' => \DomainException::class,
      'expected_evaluation_with_node_host_entity' => 'John Doe',
      'expected_dependencies_expression_only' => [
        'module' => ['node', 'user'],
        'config' => ['node.type.page'],
      ],
      'expected_dependencies_with_host_entity' => [
        'module' => ['node', 'user'],
        'config' => ['node.type.page'],
        'content' => [
          'user:user:881261cd-c9e2-4dcd-b0a8-1efa2e319a13',
        ],
      ],
    ];

    yield "complex object: FieldObjectPropsExpression containing a ReferenceFieldPropExpression" => [
      'expression' => 'ℹ︎␜entity:node:page␝uid␞␟{human_id↝entity␜␜entity:user␝name␞␟value,machine_id↠target_id}',
      'expected_json_representation' => '{"sourceType":"dynamic","expression":"ℹ︎␜entity:node:page␝uid␞␟{human_id↝entity␜␜entity:user␝name␞␟value,machine_id↠target_id}"}',
      'expected_expression_class' => FieldObjectPropsExpression::class,
      'expected_evaluation_with_user_host_entity' => \DomainException::class,
      'expected_evaluation_with_node_host_entity' => [
        'human_id' => 'John Doe',
        'machine_id' => 1,
      ],
      'expected_dependencies_expression_only' => [
        'module' => ['node', 'user', 'node'],
        'config' => ['node.type.page', 'node.type.page'],
      ],
      'expected_dependencies_with_host_entity' => [
        'module' => ['node', 'user', 'node'],
        'config' => ['node.type.page', 'node.type.page'],
        'content' => [
          'user:user:881261cd-c9e2-4dcd-b0a8-1efa2e319a13',
        ],
      ],
    ];

    $expected_dependencies_expression = [
      'module' => [
        'node',
        'file',
        'file',
      ],
      'config' => [
        'node.type.bio',
        'node.type.page',
        'field.field.node.bio.field_photo',
        'image.style.canvas_parametrized_width',
        'field.field.node.page.field_image',
        'image.style.canvas_parametrized_width',
      ],
    ];
    // The expression in the context of the `page` node, which surfaces content
    // dependencies because the `src_with_alternate_widths` property DOES
    // provide such dependencies
    // @see \Drupal\canvas\Plugin\DataType\ComputedUrlWithQueryString
    $expected_node_1_expression_dependencies = $expected_dependencies_expression;
    $expected_node_1_expression_dependencies['module'][] = 'file';
    $expected_node_1_expression_dependencies['module'][] = 'file';
    $expected_node_1_expression_dependencies['content'][] = 'file:file:' . self::FILE_UUID1;

    yield "Contrived multi-bundle example, with per-bundle field names *and* per-field property names" => [
      'expression' => 'ℹ︎␜entity:node:page|bio␝field_photo|field_image␞␟srcset_candidate_uri_template|src_with_alternate_widths',
      'expected_json_representation' => '{"sourceType":"dynamic","expression":"ℹ︎␜entity:node:bio|page␝field_photo|field_image␞␟srcset_candidate_uri_template|src_with_alternate_widths"}',
      'expected_expression_class' => FieldPropExpression::class,
      'expected_evaluation_with_user_host_entity' => \DomainException::class,
      'expected_evaluation_with_node_host_entity' => '<impossible to express in a data provider, see test>',
      'expected_dependencies_expression_only' => $expected_dependencies_expression,
      'expected_dependencies_with_host_entity' => $expected_node_1_expression_dependencies,
    ];
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\Evaluator
   * @testWith ["ℹ︎␜entity:user␝name␞␟value", null, "John Doe"]
   *           ["ℹ︎␜entity:user␝name␞0␟value", null, "John Doe"]
   *           ["ℹ︎␜entity:user␝name␞-1␟value", "Requested delta -1, but deltas must be positive integers.", "💩"]
   *           ["ℹ︎␜entity:user␝name␞5␟value", "Requested delta 5 for single-cardinality field, must be either zero or omitted.", "💩"]
   *           ["ℹ︎␜entity:user␝roles␞␟target_id", null, ["test_role_a", "test_role_b"]]
   *           ["ℹ︎␜entity:user␝roles␞0␟target_id", null, "test_role_a"]
   *           ["ℹ︎␜entity:user␝roles␞1␟target_id", null, "test_role_b"]
   *           ["ℹ︎␜entity:user␝roles␞5␟target_id", null, null]
   *           ["ℹ︎␜entity:user␝roles␞-1␟target_id", "Requested delta -1, but deltas must be positive integers.", "💩"]
   */
  public function testInvalidDynamicPropSourceFieldPropExpressionDueToDelta(string $expression, ?string $expected_message, mixed $expected_value): void {
    Role::create(['id' => 'test_role_a', 'label' => 'Test role A'])->save();
    Role::create(['id' => 'test_role_b', 'label' => 'Test role B'])->save();
    $user = User::create([
      'name' => 'John Doe',
      'roles' => [
        'test_role_a',
        'test_role_b',
      ],
    ]);

    // @phpstan-ignore-next-line argument.type
    $dynamic_prop_source_delta_test = new DynamicPropSource(StructuredDataPropExpression::fromString($expression));

    if ($expected_message !== NULL) {
      $this->expectException(\LogicException::class);
      $this->expectExceptionMessage($expected_message);
    }

    self::assertSame($expected_value, $dynamic_prop_source_delta_test->evaluate($user, is_required: TRUE));
  }

  /**
   * @coversClass \Drupal\canvas\PropSource\AdaptedPropSource
   */
  public function testAdaptedPropSource(): void {
    // 2. user created access

    // 1. daterange
    // A simple static example.
    $simple_static_example = AdaptedPropSource::parse([
      'sourceType' => 'adapter:day_count',
      'adapterInputs' => [
        'oldest' => [
          'sourceType' => 'static:field_item:daterange',
          'value' => [
            'value' => '2020-04-16',
            'end_value' => '2024-11-04',
          ],
          'expression' => 'ℹ︎daterange␟value',
        ],
        'newest' => [
          'sourceType' => 'static:field_item:daterange',
          'value' => [
            'value' => '2020-04-16',
            'end_value' => '2024-11-04',
          ],
          'expression' => 'ℹ︎daterange␟end_value',
        ],
      ],
    ]);
    // First, get the string representation and parse it back, to prove
    // serialization and deserialization works.
    $json_representation = (string) $simple_static_example;
    $this->assertSame('{"sourceType":"adapter:day_count","adapterInputs":{"oldest":{"sourceType":"static:field_item:daterange","value":{"value":"2020-04-16","end_value":"2024-11-04"},"expression":"ℹ︎daterange␟value"},"newest":{"sourceType":"static:field_item:daterange","value":{"value":"2020-04-16","end_value":"2024-11-04"},"expression":"ℹ︎daterange␟end_value"}}}', $json_representation);
    $simple_static_example = PropSource::parse(json_decode($json_representation, TRUE));
    $this->assertInstanceOf(AdaptedPropSource::class, $simple_static_example);
    // The contained information read back out.
    $this->assertSame('adapter:day_count', $simple_static_example->getSourceType());
    // Test the functionality of a DynamicPropSource:
    // - evaluate it to populate an SDC prop
    $this->assertSame(1663, $simple_static_example->evaluate(User::create(['name' => 'John Doe', 'created' => 694695600, 'access' => 1720602713]), is_required: TRUE));
    self::assertSame([
      'module' => [
        'canvas',
        'datetime_range',
        'datetime_range',
      ],
    ], $simple_static_example->calculateDependencies());

    // A simple dynamic example.
    $simple_dynamic_example = AdaptedPropSource::parse([
      'sourceType' => 'adapter:day_count',
      'adapterInputs' => [
        'oldest' => [
          'sourceType' => 'adapter:unix_to_date',
          'adapterInputs' => [
            'unix' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:user␝created␞␟value',
            ],
          ],
        ],
        'newest' => [
          'sourceType' => 'adapter:unix_to_date',
          'adapterInputs' => [
            'unix' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:user␝access␞␟value',
            ],
          ],
        ],
      ],
    ]);
    // First, get the string representation and parse it back, to prove
    // serialization and deserialization works.
    $json_representation = (string) $simple_dynamic_example;
    $this->assertSame('{"sourceType":"adapter:day_count","adapterInputs":{"oldest":{"sourceType":"adapter:unix_to_date","adapterInputs":{"unix":{"sourceType":"dynamic","expression":"ℹ︎␜entity:user␝created␞␟value"}}},"newest":{"sourceType":"adapter:unix_to_date","adapterInputs":{"unix":{"sourceType":"dynamic","expression":"ℹ︎␜entity:user␝access␞␟value"}}}}}', $json_representation);
    $simple_dynamic_example = PropSource::parse(json_decode($json_representation, TRUE));
    $this->assertInstanceOf(AdaptedPropSource::class, $simple_dynamic_example);
    // The contained information read back out.
    $this->assertSame('adapter:day_count', $simple_dynamic_example->getSourceType());
    // Test the functionality of a DynamicPropSource:
    // - evaluate it to populate an SDC prop
    $user = User::create(['name' => 'John Doe', 'created' => 694695600, 'access' => 1720602713]);
    $this->assertSame(11874, $simple_dynamic_example->evaluate($user, is_required: TRUE));
    self::assertSame([
      'module' => [
        'canvas',
        'canvas',
        'user',
        'canvas',
        'user',
      ],
    ], $simple_dynamic_example->calculateDependencies($user));

    // A complex example.
    $complex_example = AdaptedPropSource::parse([
      'sourceType' => 'adapter:day_count',
      'adapterInputs' => [
        'oldest' => [
          'sourceType' => 'static:field_item:datetime',
          'sourceTypeSettings' => [
            'storage' => [
              'datetime_type' => DateTimeItem::DATETIME_TYPE_DATE,
            ],
          ],
          'value' => '2020-04-16',
          'expression' => 'ℹ︎datetime␟value',
        ],
        'newest' => [
          'sourceType' => 'adapter:unix_to_date',
          'adapterInputs' => [
            'unix' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:user␝access␞␟value',
            ],
          ],
        ],
      ],
    ]);
    // First, get the string representation and parse it back, to prove
    // serialization and deserialization works.
    $json_representation = (string) $complex_example;
    $this->assertSame('{"sourceType":"adapter:day_count","adapterInputs":{"oldest":{"sourceType":"static:field_item:datetime","value":{"value":"2020-04-16"},"expression":"ℹ︎datetime␟value","sourceTypeSettings":{"storage":{"datetime_type":"date"}}},"newest":{"sourceType":"adapter:unix_to_date","adapterInputs":{"unix":{"sourceType":"dynamic","expression":"ℹ︎␜entity:user␝access␞␟value"}}}}}', $json_representation);
    $complex_example = PropSource::parse(json_decode($json_representation, TRUE));
    $this->assertInstanceOf(AdaptedPropSource::class, $complex_example);
    // The contained information read back out.
    $this->assertSame('adapter:day_count', $complex_example->getSourceType());
    // Test the functionality of a DynamicPropSource:
    // - evaluate it to populate an SDC prop
    $this->assertSame(1546, $complex_example->evaluate(User::create(['name' => 'John Doe', 'created' => 694695600, 'access' => 1720602713]), is_required: TRUE));
    self::assertSame([
      'module' => [
        'canvas',
        'datetime',
        'canvas',
        'user',
      ],
    ], $complex_example->calculateDependencies($user));
  }

  /**
   * @coversClass \Drupal\canvas\PropSource\DefaultRelativeUrlPropSource
   */
  public function testDefaultRelativeUrlPropSource(): void {
    $this->enableModules(['canvas_test_sdc', 'link', 'image', 'options']);
    // Force rebuilding of the definitions which will create the required
    // component.
    $plugin_manager = $this->container->get(ComponentPluginManager::class);
    $plugin_manager->clearCachedDefinitions();
    $plugin_manager->getDefinitions();
    $source = new DefaultRelativeUrlPropSource(
      value: [
        'src' => 'gracie.jpg',
        'alt' => 'A good dog',
        'width' => 601,
        'height' => 402,
      ],
      jsonSchema: [
        'title' => 'image',
        'type' => 'object',
        'required' => ['src'],
        'properties' => [
          'src' => [
            'type' => 'string',
            'contentMediaType' => 'image/*',
            'format' => 'uri-reference',
            'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$',
            'title' => 'Image URL',
          ],
          'alt' => [
            'type' => 'string',
            'title' => 'Alternate text',
          ],
          'width' => [
            'type' => 'integer',
            'title' => 'Image width',
          ],
          'height' => [
            'type' => 'integer',
            'title' => 'Image height',
          ],
        ],
      ],
      componentId: 'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop',
    );
    // First, get the string representation and parse it back, to prove
    // serialization and deserialization works.
    // Note: title of properties have been omitted; only essential data is kept.
    $json_representation = (string) $source;
    self::assertSame('{"sourceType":"default-relative-url","value":{"src":"gracie.jpg","alt":"A good dog","width":601,"height":402},"jsonSchema":{"type":"object","properties":{"src":{"type":"string","contentMediaType":"image\/*","format":"uri-reference","pattern":"^(\/|https?:\/\/)?(?!.*\\\\:\/\/)[^\\\\s]+$"},"alt":{"type":"string"},"width":{"type":"integer"},"height":{"type":"integer"}},"required":["src"]},"componentId":"sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop"}', $json_representation);
    $decoded = json_decode($json_representation, TRUE);
    // Ensure that DefaultRelativeUrlPropSource::parse() does not care about key
    // order for the JSON Schema definition it contains.
    $decoded['jsonSchema'] = array_reverse($decoded['jsonSchema']);
    $source = PropSource::parse($decoded);
    self::assertInstanceOf(DefaultRelativeUrlPropSource::class, $source);
    self::assertSame('default-relative-url', $source->getSourceType());
    $path = $this->container->get(ExtensionPathResolver::class)->getPath('module', 'canvas_test_sdc') . '/components/image-optional-with-example-and-additional-prop';
    // Prove that using a `$ref` results in the same JSON representation.
    $equivalent_source = new DefaultRelativeUrlPropSource(
      value: [
        'src' => 'gracie.jpg',
        'alt' => 'A good dog',
        'width' => 601,
        'height' => 402,
      ],
      jsonSchema: [
        '$ref' => 'json-schema-definitions://canvas.module/image',
      ],
      componentId: 'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop',
    );
    self::assertSame((string) $equivalent_source, $json_representation);
    // Test that the URL resolves on evaluation.
    self::assertSame([
      'src' => Url::fromUri(\sprintf('base:%s/gracie.jpg', $path))->toString(),
      'alt' => 'A good dog',
      'width' => 601,
      'height' => 402,
    ], $source->evaluate(NULL, is_required: TRUE));
    self::assertSame([
      'config' => ['canvas.component.sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop'],
    ], $source->calculateDependencies());
    // This is never a choice presented to the end user; this is a purely internal prop source.
    $this->expectException(\LogicException::class);
    $source->asChoice();
  }

}
