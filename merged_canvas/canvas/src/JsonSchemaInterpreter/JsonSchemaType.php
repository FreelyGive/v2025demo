<?php

declare(strict_types=1);

namespace Drupal\canvas\JsonSchemaInterpreter;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\canvas\Plugin\Validation\Constraint\StringSemanticsConstraint;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropShape\PropShape;
use Drupal\canvas\PropShape\StorablePropShape;
use Drupal\canvas\ShapeMatcher\DataTypeShapeRequirement;
use Drupal\canvas\ShapeMatcher\DataTypeShapeRequirements;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;

/**
 * Interprets JSON schema types (with type-specific constraints) to Typed Data.
 *
 * Is able to bridge the gap from JSON schema to:
 * - Drupal field types thanks to hardcoded knowledge (with facilities for
 *   altering default choices): `::computeStorablePropShape()` and
 *   `hook_storage_prop_shape_alter()`
 * - Drupal field instances' props thanks to hardcoded knowledge about Drupal
 *   validation constraint equivalents: `::toDataTypeShapeRequirements()`, used
 *   by \Drupal\canvas\ShapeMatcher\JsonSchemaFieldInstanceMatcher
 *
 * KNOWN UNKNOWNS.
 *
 * ⚠️ CONFIDENCE UNDERMINING, HIGHEST IMPACT FIRST ⚠️
 *
 * @todo Question: Does React also use JSON schema for restricting/defining its props? I.e.: identical set of primitives or not?
 * @todo expand test coverage for testing each known type as being REQUIRED too
 * @todo adapters for transforming @FieldType=timestamp -> `type:string,format=time`, @FieldType=datetime -> `type:string,format=time`, a StringSemanticsConstraint::MARKUP string could be adapted to StringSemanticsConstraint::PROSE, UnixTimestampToDateAdapter was a test-only start
 * @todo the `array` type — in particular arrays of tuples/objects, for example an array of "(image uri, alt)" pairs for an image gallery component, see https://stackoverflow.com/questions/40750340/how-to-define-json-schema-for-mapstring-integer
 * @todo `exclusiveMinimum` and `exclusiveMaximum` work differently in JSON schema draft 4 (which SDC uses) than other versions. This is a future BC nightmare.
 * @todo for `string` + `format=duration`, Drupal core has \Drupal\Core\TypedData\Plugin\DataType\DurationIso8601, but nothing uses it!
 *
 * KNOWN KNOWNS
 *
 * Upstream changes needed, but high confidence that it is possible:
 * @see \Drupal\canvas\Plugin\Field\FieldType\PathItemOverride
 * @see \Drupal\canvas\Plugin\Field\FieldType\TextItemOverride
 * @see \Drupal\canvas\Plugin\Field\FieldType\UuidItemOverride
 * @todo Disallow JSON schema string formats that do not make sense/are obscure enough — these should be disallowed in \Drupal\sdc\Component\ComponentValidator::validateProps()
 *
 * Will have to fix eventually, but high confidence that it will work:
 * @todo `minLength` and `maxLength` for `string`
 * @todo `multipleOf`, `minimum`, `exclusiveMinimum`, `maximum` and `exclusiveMaximum` support for `integer` and `number`.
 * @todo Question: can we reuse \JsonSchema\Constraints\FormatConstraint to validate just prior to passing information from fields to components, only when developing?
 * @todo Use `justinrainbow/json-schema`'s \JsonSchema\Constraints\FormatConstraint to ensure data flowing from Drupal entity is guaranteed to match with JSON schema constraint; log errors in production, throw errors in dev?
 *
 * @phpstan-type JsonSchema array<string, mixed>
 * @internal
 */
enum JsonSchemaType: string {
  case String = 'string';
  case Number = 'number';
  case Integer = 'integer';
  case Object = 'object';
  case Array = 'array';
  case Boolean = 'boolean';

  public function isScalar(): bool {
    return match ($this) {
      // A subset of the "primitive types" in JSON schema are:
      // - "scalar values" in PHP terminology
      // - "primitives" in Drupal Typed data terminology.
      // @see https://www.php.net/manual/en/function.is-scalar.php
      // @see \Drupal\Core\TypedData\PrimitiveInterface
      self::String, self::Number, self::Integer, self::Boolean => TRUE,
      // Another subset of the "primitive types" in JSON schema are:
      // - "non-scalar values" in PHP terminology, specifically "iterable"
      // - "traversable" in Drupal Typed Data terminology, specifically "lists"
      //   ("sequences" in config schema) or "complex data" ("mappings" in
      //   config schema)
      // @see https://www.php.net/manual/en/function.is-iterable.php
      // @see \Drupal\Core\TypedData\ListInterface
      // @see \Drupal\Core\TypedData\ComplexDataInterface
      // @see \Drupal\Core\TypedData\TraversableTypedDataInterface
      self::Array, self::Object => FALSE,
    };
  }

  public function isIterable(): bool {
    return !$this->isScalar();
  }

  public function isTraversable(): bool {
    return !$this->isScalar();
  }

  /**
   * Maps the given schema to data type shape requirements.
   *
   * Used for matching against existing field instances, to find candidate
   * dynamic prop source expressions that return a value that fits in this prop
   * shape.
   *
   * @param JsonSchema $schema
   *
   * @see \Drupal\canvas\PropSource\DynamicPropSource
   * @see \Drupal\canvas\JsonSchemaFieldInstanceMatcher
   */
  public function toDataTypeShapeRequirements(array $schema): DataTypeShapeRequirement|DataTypeShapeRequirements|false {
    return match ($this) {
      // There cannot possibly be any additional validation for booleans.
      JsonSchemaType::Boolean => FALSE,

      // The `string` JSON schema type
      // - `enum`: https://json-schema.org/understanding-json-schema/reference/enum
      // - `minLength` and `maxLength`: https://json-schema.org/understanding-json-schema/reference/string#length
      // - `pattern`: https://json-schema.org/understanding-json-schema/reference/string#regexp
      // - `format`: https://json-schema.org/understanding-json-schema/reference/string#format and https://json-schema.org/understanding-json-schema/reference/string#built-in-formats
      JsonSchemaType::String => match (TRUE) {
        // Custom: `contentMediaType: text/html` + `x-formatting-context`.
        // @see docs/shape-matching-into-field-types.md#3.2.1
        // @see \Drupal\canvas\Plugin\Validation\Constraint\StringSemanticsConstraint::MARKUP
        array_key_exists('contentMediaType', $schema) && $schema['contentMediaType'] === 'text/html' => match(TRUE) {
          !isset($schema['x-formatting-context']) || $schema['x-formatting-context'] === 'block' => new DataTypeShapeRequirement('StringSemantics', ['semantic' => StringSemanticsConstraint::MARKUP]),
          // @todo Add support for `x-formatting-context: inline`. This is blocked on CKEditor 5 support: https://www.drupal.org/i/3467959#comment-16052121. Once CKEditor 5 support is viable, this will need to generate a datatype shape requirement that checks the allowed text formats allowed by a field instance to ensure it only allows the `canvas_html_inline` text format, or a subset of what it allows.
          $schema['x-formatting-context'] === 'inline' => new DataTypeShapeRequirement('NOT YET SUPPORTED', []),
          // Other `x-formatting-context` values do not make sense.
          default => throw new \LogicException('Invalid `x-formatting-context` value; this component should never have been eligible.'),
        },
        array_key_exists('enum', $schema) => new DataTypeShapeRequirement('Choice', [
          'choices' => $schema['enum'],
        ], NULL),
        array_key_exists('pattern', $schema) && array_key_exists('format', $schema) => new DataTypeShapeRequirements([
          ...iterator_to_array(JsonSchemaStringFormat::from($schema['format'])->toDataTypeShapeRequirements($schema)),
          // TRICKY: `pattern` in JSON schema requires no start/end delimiters,
          // but `preg_match()` in PHP does.
          // @see https://json-schema.org/understanding-json-schema/reference/regular_expressions
          // @see \Symfony\Component\Validator\Constraints\Regex
          new DataTypeShapeRequirement('Regex', ['pattern' => self::patternToPcre($schema['pattern'])]),
        ]),
        // TRICKY: `pattern` in JSON schema requires no start/end delimiters,
        // but `preg_match()` in PHP does.
        // @see https://json-schema.org/understanding-json-schema/reference/regular_expressions
        // @see \Symfony\Component\Validator\Constraints\Regex
        array_key_exists('pattern', $schema) => new DataTypeShapeRequirement('Regex', ['pattern' => self::patternToPcre($schema['pattern'])]),
        array_key_exists('format', $schema) => JsonSchemaStringFormat::from($schema['format'])->toDataTypeShapeRequirements($schema),
        // Otherwise, it's an unrestricted string. Simply surfacing all
        // structured data containing strings would be meaningless though. To
        // ensure a good UX, Drupal interprets this as meaning "prose".
        // @see \Drupal\canvas\Plugin\Validation\Constraint\StringSemanticsConstraint::PROSE
        TRUE => new DataTypeShapeRequirement('StringSemantics', ['semantic' => StringSemanticsConstraint::PROSE]),
      },

      // phpcs:disable Drupal.Files.LineLength.TooLong
      // The `integer` and `number` JSON schema types.
      // - `enum`: https://json-schema.org/understanding-json-schema/reference/enum
      // - `multipleOf`: https://json-schema.org/understanding-json-schema/reference/numeric#multiples
      // - `minimum`, `exclusiveMinimum`, `maximum` and `exclusiveMaximum`: https://json-schema.org/understanding-json-schema/reference/numeric#range
      // phpcs:enable
      JsonSchemaType::Integer, JsonSchemaType::Number => match (TRUE) {
        array_key_exists('enum', $schema) => new DataTypeShapeRequirement('Choice', [
          'choices' => $schema['enum'],
        ], NULL),
        // Both min & max.
        array_key_exists('minimum', $schema) && array_key_exists('maximum', $schema) => new DataTypeShapeRequirement('Range', [
          'min' => $schema['minimum'],
          'max' => $schema['maximum'],
        ], NULL),
        // Either min or max.
        array_key_exists('minimum', $schema) => new DataTypeShapeRequirement('Range', ['min' => $schema['minimum']], NULL),
        array_key_exists('maximum', $schema) => new DataTypeShapeRequirement('Range', ['max' => $schema['maximum']], NULL),
        !empty(array_intersect(['multipleOf', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum'], array_keys($schema))) => new DataTypeShapeRequirement('NOT YET SUPPORTED', []),
        // Otherwise, it's an unrestricted integer or number.
        TRUE => FALSE,
      },

      JsonSchemaType::Object, JsonSchemaType::Array => (function () {
        throw new \LogicException('@see ::computeStorablePropShape() and ::recurseJsonSchema()');
      })(),
    };
  }

  /**
   * Finds the recommended UX (storage + widget) for a prop shape.
   *
   * Used for generating a StaticPropSource, for storing a value that fits in
   * this prop shape.
   *
   * @param \Drupal\canvas\PropShape\PropShape $shape
   *   The prop shape to find the recommended UX (storage + widget) for.
   *
   * @return \Drupal\canvas\PropShape\StorablePropShape|null
   *   NULL is returned to indicate that Drupal Canvas + Drupal core do not
   *   support a field type that provides a good UX for entering a value of this
   *   shape. Otherwise, a StorablePropShape is returned that specifies that UX.
   *
   * @see \Drupal\canvas\PropSource\StaticPropSource
   */
  public function computeStorablePropShape(PropShape $shape): ?StorablePropShape {
    $schema = $shape->schema;

    // Arrays containing items of a particular shape map beautifully onto multi-
    // value fields:
    // - `type: array` -> FieldItemListInterface object, with cardinality >1
    // - `items: { type: … }` -> FieldItemInterface object of some field type
    if ($this === JsonSchemaType::Array) {
      // Drupal core's Field API only supports specifying "required or not",
      // and required means ">=1 value". There's no (native) ability to
      // configure a minimum number of values for a field. Plus, JSON schema
      // allows declaring that an array must be non-empty (`minItems: 1`) even
      // for an optional array (not listed in `required`). So, it is impossible
      // to support `minItems`. And in fact, marking an SDC prop as required has
      // the same effect as `minItems: 1`.
      // @see https://www.drupal.org/project/unlimited_field_settings
      // @see https://json-schema.org/draft/2020-12/draft-bhutton-json-schema-validation-00#rfc.section.6.4.2
      // @see https://stackoverflow.com/a/49548055
      if (!empty(array_diff(array_keys($schema), ['type', 'items', 'maxItems']))) {
        return NULL;
      }
      assert($schema['type'] === 'array');
      // @todo Remove this after https://www.drupal.org/project/drupal/issues/3493086, when SDC's JSON schema validation is better; a InvalidComponentException should have been triggered for `type: array, examples: [test]` long before reaching this point!
      if (!array_key_exists('items', $schema)) {
        return NULL;
      }
      $array_item_prop_shape = PropShape::normalize($schema['items']);

      $item_storable_prop_shape = $array_item_prop_shape->getStorage();
      if ($item_storable_prop_shape === NULL) {
        return NULL;
      }

      if (array_key_exists('maxItems', $schema) && $schema['maxItems'] < 2) {
        throw new \InvalidArgumentException('Nonsensical array size limit specified.');
      }
      return new StorablePropShape(
        // The original shape, not the item shape.
        $shape,
        $item_storable_prop_shape->fieldTypeProp,
        $item_storable_prop_shape->fieldWidget,
        // Reflect the requested cardinality.
        $schema['maxItems'] ?? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        $item_storable_prop_shape->fieldStorageSettings,
        $item_storable_prop_shape->fieldInstanceSettings
      );
    }

    return match ($this) {
      // @see \Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem
      JsonSchemaType::Boolean => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('boolean', 'value'), fieldWidget: 'boolean_checkbox'),

      // The `string` JSON schema type
      // - `enum`: https://json-schema.org/understanding-json-schema/reference/enum
      // - `minLength` and `maxLength`: https://json-schema.org/understanding-json-schema/reference/string#length
      // - `pattern`: https://json-schema.org/understanding-json-schema/reference/string#regexp
      // - `format`: https://json-schema.org/understanding-json-schema/reference/string#format and https://json-schema.org/understanding-json-schema/reference/string#built-in-formats
      JsonSchemaType::String => match (TRUE) {
        // Custom: `contentMediaType: text/html` + `x-formatting-context`.
        // @see docs/shape-matching-into-field-types.md#3.2.1
        array_key_exists('contentMediaType', $schema) && $schema['contentMediaType'] === 'text/html' => match(TRUE) {
          !isset($schema['x-formatting-context']) || $schema['x-formatting-context'] === 'block' => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('text_long', 'value'), fieldWidget: 'text_textarea', fieldInstanceSettings: ['allowed_formats' => ['canvas_html_block']]),
          $schema['x-formatting-context'] === 'inline' => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('text', 'value'), fieldWidget: 'text_textfield', fieldInstanceSettings: ['allowed_formats' => ['canvas_html_inline']]),
          // Other `x-formatting-context` values do not make sense.
          default => NULL,
        },
        array_key_exists('$ref', $schema) => match ($schema['$ref']) {
          'json-schema-definitions://canvas.module/textarea' => new StorablePropShape(shape: $shape, fieldWidget: 'string_textarea', fieldTypeProp: new FieldTypePropExpression('string_long', 'value')),
          default => NULL,
        },
        array_key_exists('enum', $schema) => match(in_array('', $schema['enum'], TRUE)) {
          // The empty string is not a sensible enum value. To indicate optionality, the prop should be made optional.
          TRUE => NULL,
          FALSE => new StorablePropShape(
            shape: $shape,
            fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
            fieldWidget: 'options_select',
            fieldStorageSettings: [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
          ),
        },
        // @todo Add support for both `format` and `pattern` being present: the latter often just restricts the former.
        array_key_exists('format', $schema) => JsonSchemaStringFormat::from($schema['format'])->computeStorablePropShape($shape),
        // @todo subclass \Drupal\Core\Field\Plugin\Field\FieldType\StringItem to allow for a "pattern" setting + create subclass of \Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget to pass on that pattern setting  ⚠️
        array_key_exists('pattern', $schema) => NULL,
        // @see \Drupal\Core\Field\Plugin\Field\FieldType\StringItem
        // @todo Support `minLength`.  ⚠️
        array_key_exists('maxLength', $schema) => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('string', 'value'), fieldWidget: 'string_textfield', fieldStorageSettings: [
          'max_length' => $schema['maxLength'],
        ]),
        TRUE => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('string', 'value'), fieldWidget: 'string_textfield'),
      },

      // The `integer` JSON schema types.
      // - `enum`: https://json-schema.org/understanding-json-schema/reference/enum
      // - `multipleOf`: https://json-schema.org/understanding-json-schema/reference/numeric#multiples
      // - `minimum`, `exclusiveMinimum`, `maximum` and `exclusiveMaximum`: https://json-schema.org/understanding-json-schema/reference/numeric#range
      JsonSchemaType::Integer => match (TRUE) {
        array_key_exists('$ref', $schema) => NULL,
        array_key_exists('enum', $schema)=> new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('list_integer', 'value'), fieldWidget: 'options_select', fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ]),
        // `min` and/or `max`
        array_key_exists('minimum', $schema) || array_key_exists('maximum', $schema) => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('integer', 'value'), fieldWidget: 'number', fieldInstanceSettings: [
          'min' => $schema['minimum'] ?? (array_key_exists('exclusiveMinimum', $schema) ? $schema['exclusiveMinimum'] + 1 : NULL),
          'max' => $schema['maximum'] ?? (array_key_exists('exclusiveMaximum', $schema) ? $schema['exclusiveMaximum'] - 1 : NULL),
        ]),
        // Otherwise, it's an unrestricted integer.
        // @todo `multipleOf` ⚠️
        TRUE => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('integer', 'value'), fieldWidget: 'number'),
      },

      // The `number` JSON schema types.
      // - `enum`: https://json-schema.org/understanding-json-schema/reference/enum
      // - `multipleOf`: https://json-schema.org/understanding-json-schema/reference/numeric#multiples
      // - `minimum`, `exclusiveMinimum`, `maximum` and `exclusiveMaximum`: https://json-schema.org/understanding-json-schema/reference/numeric#range
      JsonSchemaType::Number => match (TRUE) {
        array_key_exists('$ref', $schema) => NULL,
        array_key_exists('enum', $schema) => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('list_float', 'value'), fieldWidget: 'options_select', fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ]),
        // `min` and/or `max`
        array_key_exists('minimum', $schema) || array_key_exists('maximum', $schema) => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('float', 'value'), fieldWidget: 'number', fieldStorageSettings: [
          'min' => $schema['minimum'] ?? (array_key_exists('exclusiveMinimum', $schema) ? $schema['exclusiveMinimum'] + 0.000001 : NULL),
          'max' => $schema['maximum'] ?? (array_key_exists('exclusiveMaximum', $schema) ? $schema['exclusiveMaximum'] - 0.000001 : NULL),
        ]),
        // Otherwise, it's an unrestricted integer.
        // @todo `multipleOf` ⚠️
        TRUE => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('float', 'value'), fieldWidget: 'number'),
      },

      JsonSchemaType::Object => match (TRUE) {
        array_key_exists('$ref', $schema) => match ($schema['$ref']) {
          // @see \Drupal\image\Plugin\Field\FieldType\ImageItem
          // @see \Drupal\canvas\Hook\ShapeMatchingHooks::mediaLibraryStoragePropShapeAlter()
          // @todo Try decorating with adapter in https://www.drupal.org/project/canvas/issues/3536115.
          'json-schema-definitions://canvas.module/image' => new StorablePropShape(shape: $shape, fieldWidget: 'image_image', fieldTypeProp: new FieldTypeObjectPropsExpression('image', [
            // TRICKY: Additional computed property on image fields added by Drupal Canvas.
            // @see \Drupal\canvas\Plugin\Field\FieldTypeOverride\ImageItemOverride
            // @todo Remove the next line in favor of the commented out lines in https://www.drupal.org/project/canvas/issues/3536115.
            'src' => new FieldTypePropExpression('image', 'src_with_alternate_widths'),
            // @phpcs:disable
            /*
            'src' => new ReferenceFieldTypePropExpression(
              new FieldTypePropExpression('image', 'entity'),
              new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'url'),
            ),
            */
            // @phpcs:enable
            'alt' => new FieldTypePropExpression('image', 'alt'),
            'width' => new FieldTypePropExpression('image', 'width'),
            'height' => new FieldTypePropExpression('image', 'height'),
          ])),
          // @see \Drupal\file\Plugin\Field\FieldType\FileItem
          // @see \Drupal\canvas\Hook\ShapeMatchingHooks::mediaLibraryStoragePropShapeAlter()
          'json-schema-definitions://canvas.module/video' => new StorablePropShape(
            shape: $shape,
            fieldWidget: 'file_generic',
            fieldTypeProp: new FieldTypeObjectPropsExpression('file', [
              'src' => new ReferenceFieldTypePropExpression(
                new FieldTypePropExpression('file', 'entity'),
                new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'url'),
              ),
            ]),
            fieldInstanceSettings: ['file_extensions' => 'mp4'],
          ),
          default => NULL,
        },
        default => NULL,
      },
    };
  }

  /**
   * Converts JSON schema `pattern` to PCRE.
   *
   * `pattern` in JSON schema requires no start/end delimiters, but PHP's
   * `preg_match()` does.
   *
   * @see https://json-schema.org/understanding-json-schema/reference/regular_expressions
   */
  public static function patternToPcre(string $pattern): string {
    return '/' . str_replace('/', '\/', $pattern) . '/';
  }

}
