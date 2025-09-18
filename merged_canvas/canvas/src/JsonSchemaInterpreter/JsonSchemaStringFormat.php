<?php

declare(strict_types=1);

namespace Drupal\canvas\JsonSchemaInterpreter;

use Drupal\Core\TypedData\Type\DateTimeInterface;
use Drupal\Core\TypedData\Type\UriInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\canvas\Plugin\Validation\Constraint\UriTargetMediaTypeConstraint;
use Drupal\canvas\Plugin\Validation\Constraint\UriTemplateWithVariablesConstraint;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropShape\PropShape;
use Drupal\canvas\PropShape\StorablePropShape;
use Drupal\canvas\ShapeMatcher\DataTypeShapeRequirement;
use Drupal\canvas\ShapeMatcher\DataTypeShapeRequirements;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;
use Symfony\Component\Validator\Constraints\Ip;

// phpcs:disable Drupal.Files.LineLength.TooLong
// phpcs:disable Drupal.Commenting.PostStatementComment.Found

/**
 * @see https://json-schema.org/understanding-json-schema/reference/string#format
 * @see https://json-schema.org/understanding-json-schema/reference/string#built-in-formats
 *
 * @phpstan-type JsonSchema array<string, mixed>
 * @internal
 */
enum JsonSchemaStringFormat: string {
  // Dates and times.
  // @see https://json-schema.org/understanding-json-schema/reference/string#dates-and-times
  case DateTime = 'date-time'; // RFC3339 section 5.6 — subset of ISO8601.
  case Time = 'time'; // Since draft 7.
  case Date = 'date'; // Since draft 7.
  case Duration = 'duration'; // Since draft 2019-09.

  // Email addresses.
  case Email = 'email'; // RFC5321 section 4.1.2.
  case IdnEmail = 'idn-email'; // Since draft 7, RFC6531.

  // Hostnames.
  case Hostname = 'hostname'; // RFC1123, section 2.1.
  case IdnHostname = 'idn-hostname'; // Since draft 7, RFC5890 section 2.3.2.3.

  // IP Addresses.
  case Ipv4 = 'ipv4'; // RFC2673 section 3.2.
  case Ipv6 = 'ipv6'; // RFC2373 section 2.2.

  // Resource identifiers.
  case Uuid = 'uuid'; // Since draft 2019-09. RFC4122.
  case Uri = 'uri'; // RFC3986.
  // Because FILTER_VALIDATE_URL does not conform to RFC-3986, and cannot handle
  // relative URLs, to support the relative URLs the 'uri-reference' format must
  // be used.
  // @see \JsonSchema\Constraints\FormatConstraint::check()
  // @see \Drupal\Core\Validation\Plugin\Validation\Constraint\PrimitiveTypeConstraintValidator::validate()
  case UriReference = 'uri-reference'; // Since draft 6, RFC3986 section 4.1.
  case Iri = 'iri'; // Since draft 7, RFC3987.
  case IriReference = 'iri-reference'; // Since draft 7, RFC3987.

  // URI template.
  case UriTemplate = 'uri-template'; // Since draft 7, RFC6570.

  // JSON Pointer.
  case JsonPointer = 'json-pointer'; // Since draft 6, RFC6901.
  case RelativeJsonPointer = 'relative-json-pointer'; // Since draft 7.

  // Regular expressions.
  case Regex = 'regex'; // Since draft 7, ECMA262.

  /**
   * @param JsonSchema $schema
   * @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::toDataTypeShapeRequirements()
   */
  public function toDataTypeShapeRequirements(array $schema): DataTypeShapeRequirement|DataTypeShapeRequirements {
    return match($this) {
      // Built-in formats: dates and times.
      // @see https://json-schema.org/understanding-json-schema/reference/string#dates-and-times
      // @todo Restrict to only fields with the storage setting set to \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::DATETIME_TYPE_DATETIME
      // @todo Somehow allow \Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem too, even though it is int-based, thanks to the use of an adapter? Infer this from \Drupal\Core\Field\FieldTypePluginManager::getGroupedDefinitions(), specifically `category = "date_time"`?
      static::DateTime => new DataTypeShapeRequirement('PrimitiveType', [], DateTimeInterface::class),
      // @todo Restrict to only fields with the storage setting set to \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::DATETIME_TYPE_DATE
      // @todo Somehow allow \Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem too, even though it is int-based, thanks to the use of an adapter? Infer this from \Drupal\Core\Field\FieldTypePluginManager::getGroupedDefinitions(), specifically `category = "date_time"`?
      static::Date => new DataTypeShapeRequirement('PrimitiveType', [], DateTimeInterface::class),
      // @todo Somehow allow \Drupal\Core\Field\Plugin\Field\FieldType\TimestampItem too, even though it is int-based, thanks to the use of an adapter? Infer this from \Drupal\Core\Field\FieldTypePluginManager::getGroupedDefinitions(), specifically `category = "date_time"`?
      static::Time => new DataTypeShapeRequirement('NOT YET SUPPORTED', []),
      static::Duration => new DataTypeShapeRequirement('NOT YET SUPPORTED', []),

      // Built-in formats: email addresses.
      // @see https://json-schema.org/understanding-json-schema/reference/string#email-addresses
      static::Email, static::IdnEmail => new DataTypeShapeRequirement('Email', []),

      // Built-in formats: hostnames.
      // @see https://json-schema.org/understanding-json-schema/reference/string#hostnames
      static::Hostname, static::IdnHostname => new DataTypeShapeRequirement('Hostname', []),

      // Built-in formats: IP addresses.
      // @see https://json-schema.org/understanding-json-schema/reference/string#ip-addresses
      static::Ipv4 => new DataTypeShapeRequirement('Ip', ['version' => Ip::V4]),
      static::Ipv6 => new DataTypeShapeRequirement('Ip', ['version' => Ip::V6]),

      // Built-in formats: resource identifiers.
      // @see https://json-schema.org/understanding-json-schema/reference/string#resource-identifiers
      static::Uuid => new DataTypeShapeRequirement('Uuid', []),
      // TRICKY: Drupal core does not support RFC3987 aka IRIs, but it's a superset of RFC3986.
      static::UriReference, static::Uri, static::IriReference, static::Iri => match (TRUE) {
        // Custom: the targeted resource has `contentMediaType: image/*` or
        // `contentMediaType: video/*`.
        // @see https://github.com/json-schema-org/json-schema-spec/issues/1557
        array_key_exists('contentMediaType', $schema) && in_array($schema['contentMediaType'], ['image/*', 'video/*'], TRUE) => new DataTypeShapeRequirements([
          new DataTypeShapeRequirement(UriTargetMediaTypeConstraint::PLUGIN_ID, ['mimeType' => $schema['contentMediaType']]),
          new DataTypeShapeRequirement('PrimitiveType', [], UriInterface::class),
        ]),
        default => new DataTypeShapeRequirement('PrimitiveType', [], UriInterface::class),
      },

      // Built-in formats: URI template.
      // @see https://json-schema.org/understanding-json-schema/reference/string#uri-template
      static::UriTemplate => match(array_key_exists('x-required-variables', $schema)) {
        TRUE => new DataTypeShapeRequirement(UriTemplateWithVariablesConstraint::PLUGIN_ID, ['requiredVariables' => $schema['x-required-variables']]),
        default => new DataTypeShapeRequirement('NOT YET SUPPORTED', []),
      },

      // Built-in formats: JSON Pointer.
      // @see https://json-schema.org/understanding-json-schema/reference/string#json-pointer
      static::JsonPointer, static::RelativeJsonPointer => new DataTypeShapeRequirement('NOT YET SUPPORTED', []),

      // Built-in formats: Regular expressions.
      // @see https://json-schema.org/understanding-json-schema/reference/string#regular-expressions
      static::Regex => new DataTypeShapeRequirement('NOT YET SUPPORTED', []),
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
    return match($this) {
      // Built-in formats: dates and times.
      // @see https://json-schema.org/understanding-json-schema/reference/string#dates-and-times
      // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem
      static::DateTime => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('datetime', 'value'), fieldStorageSettings: ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME], fieldWidget: 'datetime_default'),
      // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem
      static::Date => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('datetime', 'value'), fieldStorageSettings: ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATE], fieldWidget: 'datetime_default'),
      // @todo A new subclass of DateTimeItem, to allow storing only time?
      static::Time => NULL,
      // @todo A new field type powered by \Drupal\Core\TypedData\Plugin\DataType\DurationIso8601, to allow storing a duration?
      // @see \Drupal\Core\TypedData\Plugin\DataType\DurationIso8601
      static::Duration => NULL,

      // Built-in formats: email addresses.
      // @see https://json-schema.org/understanding-json-schema/reference/string#email-addresses
      // @see \Drupal\Core\Field\Plugin\Field\FieldType\EmailItem
      static::Email, static::IdnEmail => new StorablePropShape(shape: $shape, fieldTypeProp: new FieldTypePropExpression('email', 'value'), fieldWidget: 'email_default'),

      // Built-in formats: hostnames.
      // @see https://json-schema.org/understanding-json-schema/reference/string#hostnames
      static::Hostname, static::IdnHostname => NULL,

      // Built-in formats: IP addresses.
      // @see https://json-schema.org/understanding-json-schema/reference/string#ip-addresses
      static::Ipv4 => NULL,
      static::Ipv6 => NULL,

      // Built-in formats: resource identifiers.
      // @see https://json-schema.org/understanding-json-schema/reference/string#resource-identifiers
      // ⚠️ This field type has no widget in Drupal core, otherwise it'd be
      // possible to support! But … would allowing the Content Creator to
      // enter a UUID really make sense?
      // @see \Drupal\Core\Field\Plugin\Field\FieldType\UuidItem
      static::Uuid => NULL,
      // TRICKY: Drupal core does not support RFC3987 aka IRIs, but it's a superset of RFC3986.
      // TRICKY: the `uri` and `iri` prop types will only pass validation with absolute paths, so we
      // instead use the link widget which is more permissive about the URI/IRI content.
      // @see \Drupal\Core\Field\Plugin\Field\FieldType\UriItem
      // @see \Drupal\link\Plugin\Field\FieldType\LinkItem::defaultFieldSettings()
      static::UriReference, static::Uri, static::IriReference, static::Iri => match (TRUE) {
        // Custom: the targeted resource has `contentMediaType: image/*`.
        array_key_exists('contentMediaType', $shape->schema) && $shape->schema['contentMediaType'] === 'image/*' => match (TRUE) {// Canvas only supports either of the two above out of the box. For more
          // Canvas only supports either of the two below out of the box. For more
          // complicated needs, use hook_storage_prop_shape_alter().
          !array_key_exists('pattern', $shape->schema) => NULL,

          // Browser-accessible image URLs.
          // @see json-schema-definitions://canvas.module/image-uri
          // @see \Drupal\Tests\canvas\Unit\SchemaJsonPatternsTest::testImageUriPattern
          $shape->schema['pattern'] === '^(/|https?://)?(?!.*\://)[^\s]+$' => new StorablePropShape(
            shape: $shape,
            fieldTypeProp: new FieldTypePropExpression('image', 'src_with_alternate_widths'),
            fieldWidget: 'image_image',
          ),

          // Stream wrapper image URIs.
          // @see json-schema-definitions://canvas.module/stream-wrapper-image-uri
          // @todo Update in https://www.drupal.org/project/canvas/issues/3542895  to only do this if `format==uri` — right now this also is used for `format=uri-reference`. Theoretical problem though, because using `format=uri-reference` does not make sense for stream wrapper URIs.
          $shape->schema['pattern'] === '^(?!https?://)[\w\-]+://' => new StorablePropShape(
            shape: $shape,
            fieldTypeProp: new ReferenceFieldTypePropExpression(
              new FieldTypePropExpression('image', 'entity'),
              new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'value'),
            ),
            fieldWidget: 'image_image',
          ),

          // Canvas only supports either of the two above out of the box. For more
          // complicated needs, use hook_storage_prop_shape_alter().
          default => NULL,
        },
        default => new StorablePropShape(
          shape: $shape,
          fieldTypeProp: new FieldTypePropExpression('link', 'url'),
          // @see \Drupal\link\Plugin\Field\FieldType\LinkItem::defaultFieldSettings()
          fieldInstanceSettings: [
            // This shape only needs the URI, not a title.
            'title' => 0,
          ],
          fieldWidget: 'link_default',
        ),
      },

      // Built-in formats: URI template.
      // @see https://json-schema.org/understanding-json-schema/reference/string#uri-template
      static::UriTemplate => NULL,

      // Built-in formats: JSON Pointer.
      // @see https://json-schema.org/understanding-json-schema/reference/string#json-pointer
      static::JsonPointer, static::RelativeJsonPointer => NULL,

      // Built-in formats: Regular expressions.
      // @see https://json-schema.org/understanding-json-schema/reference/string#regular-expressions
      static::Regex => NULL,
    };
  }

}
