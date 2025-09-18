<?php

declare(strict_types=1);

namespace Drupal\canvas\PropShape;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType;

/**
 * A prop shape: a normalized component prop's JSON schema.
 *
 * Pass a `Component` plugin instance to `PropShape::getComponentProps()` and
 * receive an array of PropShape objects.
 *
 * @phpstan-type JsonSchema array<string, mixed>
 * @internal
 */
final class PropShape {

  /**
   * The resolved schema of the prop shape.
   */
  public readonly array $resolvedSchema;

  public function __construct(
    // The schema of the prop shape.
    public readonly array $schema,
  ) {
    $normalized = self::normalizePropSchema($this->schema);
    if ($schema !== $normalized) {
      throw new \InvalidArgumentException(sprintf("The passed in schema (%s) should be normalized (%s).", print_r($schema, TRUE), print_r($normalized, TRUE)));
    }
    $this->resolvedSchema = self::resolveSchemaReferences($schema);
  }

  public static function normalize(array $raw_sdc_prop_schema): PropShape {
    return new PropShape(self::normalizePropSchema($raw_sdc_prop_schema));
  }

  /**
   * @param JsonSchema $schema
   * @return JsonSchema
   *
   * @see \Drupal\canvas\Plugin\Adapter\AdapterBase::resolveSchemaReferences
   */
  private static function resolveSchemaReferences(array $schema): array {
    if (isset($schema['$ref'])) {
      // Perform the same schema resolving as `justinrainbow/json-schema`.
      // @todo Delete this method, actually use `justinrainbow/json-schema`.
      $schema = json_decode(file_get_contents($schema['$ref']) ?: '{}', TRUE);
    }

    // Recurse.
    if ($schema['type'] === 'object' && isset($schema['properties'])) {
      $schema['properties'] = array_map([__CLASS__, 'resolveSchemaReferences'], $schema['properties']);
    }
    elseif ($schema['type'] === 'array' && isset($schema['items'])) {
      $schema['items'] = self::resolveSchemaReferences($schema['items']);
    }

    return $schema;
  }

  public function uniquePropSchemaKey(): string {
    // A reliable key thanks to ::normalizePropSchema().
    return urldecode(http_build_query($this->schema));
  }

  /**
   * @param JsonSchema $prop_schema
   *
   * @return JsonSchema
   */
  public static function normalizePropSchema(array $prop_schema): array {
    ksort($prop_schema);

    // Normalization is not (yet) possible when `$ref`s are still present.
    if (!array_key_exists('type', $prop_schema) && array_key_exists('$ref', $prop_schema)) {
      return $prop_schema;
    }

    // Ensure that `type` is always listed first.
    $normalized_prop_schema = ['type' => $prop_schema['type']] + $prop_schema;

    // Title, description, examples and meta:enum (and its associated optional
    // x-translation-context) do not affect which field type + widget should be
    // used.
    unset($normalized_prop_schema['title']);
    unset($normalized_prop_schema['description']);
    unset($normalized_prop_schema['examples']);
    unset($normalized_prop_schema['meta:enum']);
    unset($normalized_prop_schema['x-translation-context']);
    // @todo Add support to `SDC` for `default` in https://www.drupal.org/project/canvas/issues/3462705?
    // @see https://json-schema.org/draft/2020-12/draft-bhutton-json-schema-validation-00#rfc.section.9.2
    unset($normalized_prop_schema['default']);

    $normalized_prop_schema['type'] = JsonSchemaType::from(
    // TRICKY: SDC always allowed `object` for Twig integration reasons.
    // @see \Drupal\sdc\Component\ComponentMetadata::parseSchemaInfo()
      is_array($prop_schema['type']) ? $prop_schema['type'][0] : $prop_schema['type']
    )->value;

    // If this is a `type: object` with not a `$ref` but `properties`, normalize
    // those too.
    if ($normalized_prop_schema['type'] === JsonSchemaType::Object->value && array_key_exists('properties', $normalized_prop_schema)) {
      $normalized_prop_schema['properties'] = array_map(
        fn (array $prop_schema) => self::normalizePropSchema($prop_schema),
        $normalized_prop_schema['properties'],
      );
    }

    return $normalized_prop_schema;
  }

  public function getStorage(): ?StorablePropShape {
    // The default storable prop shape, if any. Prefer the original prop shape,
    // which may contain `$ref`, and allows hook_storage_prop_shape_alter()
    // implementations to suggest a field type based on the
    // definition name.
    // If that finds no field type storage, resolve `$ref`, which removes `$ref`
    // altogether. Try to find a field type storage again, but then the decision
    // relies solely on the final (fully resolved) JSON schema.
    $json_schema_type = JsonSchemaType::from($this->schema['type']);
    $storable_prop_shape = JsonSchemaType::from($this->schema['type'])->computeStorablePropShape($this);
    if ($storable_prop_shape === NULL) {
      $resolved_prop_shape = PropShape::normalize($this->resolvedSchema);
      $storable_prop_shape = $json_schema_type->computeStorablePropShape($resolved_prop_shape);
    }

    $alterable = $storable_prop_shape
      ? CandidateStorablePropShape::fromStorablePropShape($storable_prop_shape)
      // If no default storable prop shape exists, generate an empty candidate.
      : new CandidateStorablePropShape($this);

    // Allow modules to alter the default.
    self::moduleHandler()->alter(
      'storage_prop_shape',
      // The value that other modules can alter.
      $alterable,
    );

    // @todo DX: validate that the field type exists.
    // @todo DX: validate that the field prop exists.
    // @todo DX: validate that the field widget exists.

    return $alterable->toStorablePropShape();
  }

  private static function moduleHandler(): ModuleHandlerInterface {
    return \Drupal::moduleHandler();
  }

}
