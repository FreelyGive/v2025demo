<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;
use Drupal\canvas\PropShape\PropShape;
use Drupal\canvas\PropShape\StorablePropShape;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * @covers \Drupal\canvas\Hook\ShapeMatchingHooks::mediaLibraryStoragePropShapeAlter()
 * @covers \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::mediaLibraryFieldWidgetInfoAlter()
 * @group canvas
 */
class MediaLibraryHookStoragePropAlterTest extends PropShapeRepositoryTest {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // @see \Drupal\media\Entity\Media
    'media',
    // @see \Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget
    'media_library',
    // Without this module installed, the media source fields can't be created,
    // because the FieldConfig entity type would not exist.
    'field',
    // The Media Library widget uses Views.
    'views',
    // @see \Drupal\media_library\MediaLibraryEditorOpener::__construct()
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // @see \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::generateSampleValue()
    $this->installEntitySchema('media');

    // @see \Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget
    $this->installEntitySchema('user');

    // Intentionally do NOT rely on the Standard install profile: the MediaTypes
    // using the Image MediaSource should work.
    // @see core/profiles/standard/config/optional/media.type.image.yml
    // @see \Drupal\media\Plugin\media\Source\Image
    $this->createMediaType('image', ['id' => 'baby_photos']);
    $this->createMediaType('image', ['id' => 'vacation_photos']);
    // Same for the VideoFile, oEmbed and File MediaSources.
    // @see \Drupal\media\Plugin\media\Source\VideoFile
    $this->createMediaType('video_file', ['id' => 'baby_videos']);
    $this->createMediaType('video_file', ['id' => 'vacation_videos']);

    // A sample value is generated during the test, which needs this table.
    $this->installSchema('file', ['file_usage']);

    // @see \Drupal\media_library\MediaLibraryEditorOpener::__construct()
    $this->installEntitySchema('filter_format');
  }

  public static function getExpectedUnstorablePropShapes(): array {
    $unstorable_prop_shapes = parent::getExpectedUnstorablePropShapes();
    unset(
      $unstorable_prop_shapes['type=object&$ref=json-schema-definitions://canvas.module/video'],
    );
    return $unstorable_prop_shapes;
  }

  /**
   * @return \Drupal\canvas\PropShape\StorablePropShape[]
   */
  public static function getExpectedStorablePropShapes(): array {
    $storable_prop_shapes = parent::getExpectedStorablePropShapes();
    $image_shapes = array_intersect_key(
      $storable_prop_shapes,
      array_flip([
        'type=object&$ref=json-schema-definitions://canvas.module/image',
        'type=array&items[$ref]=json-schema-definitions://canvas.module/image&items[type]=object',
        'type=array&items[$ref]=json-schema-definitions://canvas.module/image&items[type]=object&maxItems=2',
      ]),
    );
    foreach ($image_shapes as $k => $image_shape) {
      $storable_prop_shapes[$k] = new StorablePropShape(
        shape: $image_shape->shape,
        cardinality: $image_shape->cardinality,
        fieldWidget: 'media_library_widget',
        // @phpstan-ignore-next-line
        fieldTypeProp: StructuredDataPropExpression::fromString("ℹ︎entity_reference␟{src↝entity␜␜entity:media:baby_photos|vacation_photos␝field_media_image|field_media_image_1␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:baby_photos|vacation_photos␝field_media_image|field_media_image_1␞␟alt,width↝entity␜␜entity:media:baby_photos|vacation_photos␝field_media_image|field_media_image_1␞␟width,height↝entity␜␜entity:media:baby_photos|vacation_photos␝field_media_image|field_media_image_1␞␟height}"),
        fieldStorageSettings: [
          'target_type' => 'media',
        ],
        fieldInstanceSettings: [
          'handler' => 'default:media',
          'handler_settings' => [
            'target_bundles' => [
              'baby_photos' => 'baby_photos',
              'vacation_photos' => 'vacation_photos',
            ],
          ],
        ],
      );
    }

    $storable_prop_shapes['type=object&$ref=json-schema-definitions://canvas.module/video'] = new StorablePropShape(
      shape: new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/video']),
      // @phpstan-ignore-next-line
      fieldTypeProp: StructuredDataPropExpression::fromString('ℹ︎entity_reference␟{src↝entity␜␜entity:media:baby_videos|vacation_videos␝field_media_video_file|field_media_video_file_1␞␟entity␜␜entity:file␝uri␞␟url}'),
      fieldWidget: 'media_library_widget',
      fieldStorageSettings: [
        'target_type' => 'media',
      ],
      fieldInstanceSettings: [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            'baby_videos' => 'baby_videos',
            'vacation_videos' => 'vacation_videos',
          ],
        ],
      ],
    );

    $storable_prop_shapes['type=string&$ref=json-schema-definitions://canvas.module/stream-wrapper-image-uri'] = new StorablePropShape(
      shape: new PropShape(['type' => 'string', 'contentMediaType' => 'image/*', 'format' => 'uri', 'pattern' => '^(?!https?://)[\w\-]+://']),
      // @phpstan-ignore-next-line
      fieldTypeProp: StructuredDataPropExpression::fromString('ℹ︎entity_reference␟entity␜␜entity:media:baby_photos|vacation_photos␝field_media_image|field_media_image_1␞␟entity␜␜entity:file␝uri␞␟value'),
      fieldWidget: 'media_library_widget',
      fieldStorageSettings: [
        'target_type' => 'media',
      ],
      fieldInstanceSettings: [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            'baby_photos' => 'baby_photos',
            'vacation_photos' => 'vacation_photos',
          ],
        ],
      ],
    );

    return $storable_prop_shapes;
  }

}
