<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\editor\EditorInterface;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\DateRangeItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\EntityReferenceItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\FileItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\FileUriItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\FloatItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\ImageItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\LinkItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\ListIntegerItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\StringItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\StringLongItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\TextItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\TextLongItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\TextWithSummaryItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\UriItemOverride;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\UuidItemOverride;
use Drupal\canvas\Plugin\Validation\Constraint\StringSemanticsConstraint;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropShape\CandidateStorablePropShape;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;
use Drupal\filter\FilterFormatInterface;
use Drupal\media\Entity\MediaType;
use Drupal\media\MediaTypeInterface;
use Drupal\media\Plugin\media\Source\Image;
use Drupal\media\Plugin\media\Source\VideoFile;
use Symfony\Component\Validator\Constraints\Hostname;
use Symfony\Component\Validator\Constraints\Ip;

/**
 * @file
 * Hook implementations that make shape matching work.
 *
 * @see https://www.drupal.org/project/issues/canvas?component=Shape+matching
 * @see docs/shape-matching-into-field-types.md, section 3.1.2.a
 */
class ShapeMatchingHooks {

  const SCHEMA_TO_MEDIA_SOURCE = [
    // @see \Drupal\media\Plugin\media\Source\Image
    'json-schema-definitions://canvas.module/image' => Image::class,
    // @see \Drupal\media\Plugin\media\Source\VideoFile
    'json-schema-definitions://canvas.module/video' => VideoFile::class,
  ];

  /**
   * Implements hook_validation_constraint_alter().
   */
  #[Hook('validation_constraint_alter')]
  public function validationConstraintAlter(array &$definitions): void {
    // Add the Symfony validation constraints that Drupal core does not add in
    // \Drupal\Core\Validation\ConstraintManager::registerDefinitions() for
    // unknown reasons. Do it defensively, to not break when this changes.
    if (!isset($definitions['Hostname'])) {
      // @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaStringFormat::Hostname
      // @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaStringFormat::IdnHostname
      $definitions['Hostname'] = [
        'label' => 'Hostname',
        'class' => Hostname::class,
        'type' => ['string'],
        'provider' => 'core',
        'id' => 'Hostname',
      ];
    }
    if (!isset($definitions['Ip'])) {
      // @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaStringFormat::Ipv4
      // @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaStringFormat::Ipv6
      $definitions['Ip'] = [
        'label' => 'IP address',
        'class' => Ip::class,
        'type' => ['string'],
        'provider' => 'core',
        'id' => 'Ip',
      ];
    }
  }

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(array &$info): void {
    $overrides = [
      'daterange' => DateRangeItemOverride::class,
      'file' => FileItemOverride::class,
      'file_uri' => FileUriItemOverride::class,
      'float' => FloatItemOverride::class,
      'image' => ImageItemOverride::class,
      'link' => LinkItemOverride::class,
      'list_integer' => ListIntegerItemOverride::class,
      'string' => StringItemOverride::class,
      'string_long' => StringLongItemOverride::class,
      'text' => TextItemOverride::class,
      'text_long' => TextLongItemOverride::class,
      'text_with_summary' => TextWithSummaryItemOverride::class,
      'uuid' => UuidItemOverride::class,
      'uri' => UriItemOverride::class,
      'entity_reference' => EntityReferenceItemOverride::class,
    ];
    foreach ($overrides as $plugin_id => $class) {
      if (\array_key_exists($plugin_id, $info)) {
        $info[$plugin_id]['class'] = $class;
      }
    }
  }

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    // The User entity type uses the "string" field type but then overrides its
    // item class with a custom one, causing the `value` property string
    // semantics to be lost. Explicitly set them again.
    // @see \Drupal\canvas\Plugin\Field\FieldType\StringItemOverride
    // @see \Drupal\user\UserNameItem
    // @see \Drupal\user\Entity\User::baseFieldDefinitions()
    // @todo Remove when \Drupal\canvas\Plugin\Field\FieldTypeOverride\StringItemOverride is merged into core.
    if ($entity_type->id() === 'user') {
      $fields['name']->addPropertyConstraints('value', ['StringSemantics' => StringSemanticsConstraint::PROSE]);
    }

    // The File entity type's `filename` and `filemime` base fields use the
    // `string` field type but are NOT prose (which is the default semantic for
    // that field type).
    // @see \Drupal\canvas\Plugin\Field\FieldType\StringItemOverride
    if ($entity_type->id() === 'file') {
      // Override the default string semantics of the "string" field type.
      // @see \Drupal\canvas\Plugin\Field\FieldTypeOverride\StringItemOverride::propertyDefinitions()
      $fields['filename']->addPropertyConstraints('value', ['StringSemantics' => StringSemanticsConstraint::STRUCTURED]);
      $fields['filemime']->addPropertyConstraints('value', ['StringSemantics' => StringSemanticsConstraint::STRUCTURED]);
      $fields['uri']->setRequired(\TRUE);
    }
  }

  /**
   * Implements hook_filter_format_access().
   *
   * Prevents any operations on Drupal Canvas's text formats.
   */
  #[Hook('filter_format_access')]
  public function filterFormatAccess(FilterFormatInterface $format, string $operation, AccountInterface $account): AccessResult {
    $protected_formats = [
      'canvas_html_inline',
      'canvas_html_block',
    ];
    if (in_array($format->id(), $protected_formats, TRUE)) {
      return match($operation) {
        // It is guaranteed that these text formats/editors are available only
        // for Canvas's component instance form.
        // @see \Drupal\filter\Element\TextFormat::processFormats()
        // @see \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::processTextFormat()
        'use' => AccessResult::allowed()->addCacheableDependency($format),
        default => AccessResult::forbidden('Drupal Canvas text formats cannot be modified.')
          ->addCacheableDependency($format)
      };
    }

    // No opinion on other formats.
    return AccessResult::neutral();
  }

  /**
   * Implements hook_editor_access().
   *
   * Prevents any operations on Drupal Canvas's editors.
   */
  #[Hook('editor_access')]
  public function editorAccess(EditorInterface $editor, string $operation, AccountInterface $account): AccessResult {
    $protected_editors = [
      'canvas_html_inline',
      'canvas_html_block',
    ];
    if (in_array($editor->id(), $protected_editors, TRUE)) {
      return AccessResult::forbidden('Drupal Canvas editors cannot be modified.')
        ->addCacheableDependency($editor);
    }

    // No opinion on other editors.
    return AccessResult::neutral();
  }

  /**
   * Implements hook_entity_operation_alter().
   *
   * Removes all operations for Drupal Canvas's text formats and editors from list builders.
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    // Handle FilterFormat entities.
    if ($entity instanceof FilterFormatInterface) {
      $protected_formats = [
        'canvas_html_inline',
        'canvas_html_block',
      ];

      if (in_array($entity->id(), $protected_formats, TRUE)) {
        // Remove all operations for these text formats.
        $operations = [];
      }
    }

    // Handle Editor entities.
    if ($entity instanceof EditorInterface) {
      $protected_editors = [
        'canvas_html_inline',
        'canvas_html_block',
      ];

      if (in_array($entity->id(), $protected_editors, TRUE)) {
        // Remove all operations for these editors.
        $operations = [];
      }
    }
  }

  /**
   * Implements hook_storage_prop_shape_alter().
   *
   * (On behalf of the Media Library module.)
   *
   * Overrides the default: the "image" field type + widget. Note that this used
   * to run only for sites that install the Media Library module, but to achieve
   * the intended authoring experience, Canvas depends on the Media Library module
   * since https://www.drupal.org/i/3474226.
   *
   * @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::computeStorablePropShape()
   * @todo Move to Media Library module, eventually.
   */
  #[Hook('storage_prop_shape_alter', module: 'media_library')]
  public function mediaLibraryStoragePropShapeAlter(CandidateStorablePropShape $storable_prop_shape): void {
    $media_type_ids = NULL;

    // @see json-schema-definitions://canvas.module/stream-wrapper-image-uri
    if ($storable_prop_shape->shape->schema['type'] === 'string' &&
      (
        isset($storable_prop_shape->shape->schema['$ref']) &&
        $storable_prop_shape->shape->schema['$ref'] === 'json-schema-definitions://canvas.module/stream-wrapper-image-uri'
      )
      || (
        isset($storable_prop_shape->shape->schema['contentMediaType'])
        && $storable_prop_shape->shape->schema['contentMediaType'] === 'image/*'
        // @see json-schema-definitions://canvas.module/stream-wrapper-image-uri
        // @todo Update in https://www.drupal.org/project/canvas/issues/3542895  to only do this if `format==uri` — right now this also is used for `format=uri-reference`. Theoretical problem though, because using `format=uri-reference` does not make sense for stream wrapper URIs.
        && ($storable_prop_shape->shape->schema['pattern'] ?? '') === '^(?!https?://)[\w\-]+://'
      )
    ) {
      $media_types = self::getMediaTypesForSource(Image::class);
      if (!empty($media_types)) {
        $media_type_ids = array_keys($media_types);
        $source_field_names = array_map(
        // @phpstan-ignore-next-line
          fn(MediaTypeInterface $type): string => $type->getSource()->getSourceFieldDefinition($type)->getName(),
          $media_types
        );
        $storable_prop_shape->fieldTypeProp = new ReferenceFieldTypePropExpression(
          new FieldTypePropExpression('entity_reference', 'entity'),
          new ReferenceFieldPropExpression(
            new FieldPropExpression(BetterEntityDataDefinition::create('media', $media_type_ids), $source_field_names, \NULL, 'entity'),
            new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'value'),
          ),
        );
      }
    }

    if ($storable_prop_shape->shape->schema['type'] === 'object' &&
      isset($storable_prop_shape->shape->schema['$ref']) &&
      array_key_exists($storable_prop_shape->shape->schema['$ref'], self::SCHEMA_TO_MEDIA_SOURCE)
    ) {
      $media_source_class = self::SCHEMA_TO_MEDIA_SOURCE[$storable_prop_shape->shape->schema['$ref']];
      $media_types = self::getMediaTypesForSource($media_source_class);
      if (!empty($media_types)) {
        $media_type_ids = array_keys($media_types);
        $storable_prop_shape->fieldTypeProp = new FieldTypeObjectPropsExpression('entity_reference',
          $this->getFieldTypeProps($media_types, $media_type_ids, $media_source_class)
        );
      }
    }

    if (!empty($media_type_ids)) {
      $storable_prop_shape->fieldStorageSettings = ['target_type' => 'media'];
      $storable_prop_shape->fieldInstanceSettings = [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => array_combine($media_type_ids, $media_type_ids),
        ],
      ];
      $storable_prop_shape->fieldWidget = 'media_library_widget';
    }
  }

  /**
   * Implements hook_storage_prop_shape_alter().
   *
   * (On behalf of the Date Range module.)
   *
   * @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::computeStorablePropShape()
   * @see \Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem
   */
  #[Hook('storage_prop_shape_alter', module: 'datetime_range')]
  public function datetimeRangeStoragePropShapeAlter(CandidateStorablePropShape $storable_prop_shape): void {
    if ($storable_prop_shape->shape->schema == [
      'type' => 'object',
      '$ref' => 'json-schema-definitions://sdc_test_all_props.module/date-range',
    ]) {
      $storable_prop_shape->fieldTypeProp = new FieldTypeObjectPropsExpression('daterange', [
        'from' => new FieldTypePropExpression('daterange', 'end_value'),
        'to' => new FieldTypePropExpression('daterange', 'value'),
      ]);
      $storable_prop_shape->fieldStorageSettings = ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE];
      // @todo Make this actually work in component instance forms in https://www.drupal.org/project/canvas/issues/3523379
      $storable_prop_shape->fieldWidget = 'daterange_default';
    }
  }

  /**
   * @param class-string $media_source_class
   *
   * @return array
   */
  private static function getMediaTypesForSource(string $media_source_class) : array {
    // Allow all MediaTypes that use the "image" MediaSource, for example.
    // @see \Drupal\media\Plugin\media\Source\Image
    $media_types = array_filter(
      MediaType::loadMultiple(),
      fn (MediaTypeInterface $type): bool => is_a($type->getSource(), $media_source_class)
    );
    ksort($media_types);
    $media_type_ids = array_map(
    // @phpstan-ignore-next-line
      fn (MediaTypeInterface $type): string => $type->id(),
      $media_types
    );
    return array_combine($media_type_ids, $media_types);
  }

  /**
   * Returns Field Type Props for specific MediaSource.
   *
   * @param \Drupal\media\MediaTypeInterface[] $media_types
   * @param array $media_type_ids
   * @param string $media_source_class
   *
   * @return array|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression[]
   */
  protected function getFieldTypeProps(array $media_types, array $media_type_ids, string $media_source_class): array {
    $source_field_names = array_map(
    // @phpstan-ignore-next-line
      fn (MediaTypeInterface $type): string => $type->getSource()->getSourceFieldDefinition($type)->getName(),
      $media_types
    );

    return match ($media_source_class) {
      Image::class => [
        'src' => new ReferenceFieldTypePropExpression(
          new FieldTypePropExpression('entity_reference', 'entity'),
          // TRICKY: Additional computed property on image fields added by Drupal Canvas.
          // @see \Drupal\canvas\Plugin\Field\FieldTypeOverride\ImageItemOverride
          new FieldPropExpression(BetterEntityDataDefinition::create('media', $media_type_ids), $source_field_names, \NULL, 'src_with_alternate_widths'),
        ),
        'alt' => new ReferenceFieldTypePropExpression(new FieldTypePropExpression('entity_reference', 'entity'), new FieldPropExpression(BetterEntityDataDefinition::create('media', $media_type_ids), $source_field_names, \NULL, 'alt')),
        'width' => new ReferenceFieldTypePropExpression(new FieldTypePropExpression('entity_reference', 'entity'), new FieldPropExpression(BetterEntityDataDefinition::create('media', $media_type_ids), $source_field_names, \NULL, 'width')),
        'height' => new ReferenceFieldTypePropExpression(new FieldTypePropExpression('entity_reference', 'entity'), new FieldPropExpression(BetterEntityDataDefinition::create('media', $media_type_ids), $source_field_names, \NULL, 'height')),
      ],
      VideoFile::class => [
        'src' => new ReferenceFieldTypePropExpression(
          new FieldTypePropExpression('entity_reference', 'entity'),
          new ReferenceFieldPropExpression(
            new FieldPropExpression(BetterEntityDataDefinition::create('media', $media_type_ids), $source_field_names, \NULL, 'entity'),
            new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', \NULL, 'url')
          )
        ),
      ],
      default => [],
    };
  }

  /**
   * Returns MediaType Source Plugin field name.
   *
   * @param \Drupal\media\MediaTypeInterface $media_type
   *
   * @return string
   */
  protected function getMediaSourceFieldName(MediaTypeInterface $media_type): string {
    $source_field_definition = $media_type->getSource()
      ->getSourceFieldDefinition($media_type);
    \assert($source_field_definition !== \NULL);

    return $source_field_definition->getName();
  }

}
