<?php

declare(strict_types=1);

namespace Drupal\canvas\ShapeMatcher;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\Plugin\DataType\ConfigEntityAdapter;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\Field\Plugin\Field\FieldType\PasswordItem;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\File\MimeType\ExtensionMimeTypeGuesser;
use Drupal\Core\ProxyClass\File\MimeType\ExtensionMimeTypeGuesser as LazyExtensionMimeTypeGuesser;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\Core\TypedData\Plugin\DataType\BooleanData;
use Drupal\Core\TypedData\Plugin\DataType\FloatData;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Validation\ConstraintManager;
use Drupal\Core\Validation\Plugin\Validation\Constraint\ComplexDataConstraint;
use Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType;
use Drupal\canvas\Plugin\AdapterManager;
use Drupal\canvas\Plugin\Validation\Constraint\UriTargetMediaTypeConstraint;
use Drupal\canvas\Plugin\Validation\Constraint\UriTargetMediaTypeConstraintValidator;
use Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\file\Plugin\Field\FieldType\FileUriItem;
use Drupal\options\Plugin\Field\FieldType\ListFloatItem;
use Drupal\options\Plugin\Field\FieldType\ListIntegerItem;
use Drupal\options\Plugin\Field\FieldType\ListStringItem;
use Drupal\text\TextProcessed;
use Symfony\Component\Validator\Constraint;

/**
 * Matches JSON schema type (+ constraints) with field instances.
 *
 * Starts from a JSON schema type and finds equivalent Drupal validation
 * constraints.
 *
 * @see \Drupal\canvas\ShapeMatcher\DataTypeShapeRequirement
 * @see \Drupal\canvas\ShapeMatcher\DataTypeShapeRequirements
 * @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::toDataTypeShapeRequirements()
 *
 * Then traverses all (base, bundle, configurable) field instances on all entity
 * types (and bundles), to find a match. Matches are described using structured
 * data prop expressions.
 *
 * @see \Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface
 * @see \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression
 * @see \Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression
 * @see \Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression
 *
 * These are then used in "dynamic prop sources".
 *
 * @see \Drupal\canvas\PropSource\DynamicPropSource
 *
 * For "static prop sources", the equivalents are:
 *
 * @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::computeStorablePropShape()
 * @see \Drupal\canvas\PropShape\StorablePropShape
 * @see \Drupal\canvas\PropSource\StaticPropSource
 *
 * @phpstan-import-type JsonSchema from \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType
 */
final class JsonSchemaFieldInstanceMatcher {

  /**
   * @var array<lowercase-string, class-string>
   */
  public const IGNORE_FIELD_TYPES = [
    // The `list` field types allows each field instance to define its own set
    // of possible values. The probability of this exactly matching the explicit
    // inputs for a component is astronomical.
    // If we ever decide to allow this, then the `Choice` constraint must be
    // correctly specified on it. Otherwise, `::toDataTypeShapeRequirement()`
    // does not find any constraints and matches every such field instance
    // against every integer/float.
    'list_float' => ListFloatItem::class,
    'list_integer' => ListIntegerItem::class,
    'list_string' => ListStringItem::class,
    // The `map` field type has no widget, is broken, and is hidden in the UI.
    // @see https://www.drupal.org/node/2563843
    // @see \Drupal\Core\Field\Plugin\Field\FieldType\MapItem
    'map' => MapItem::class,
    // The `password` field type can never contain data that could be reasonably
    // displayed in a component instance.
    // @see \Drupal\Core\Field\Plugin\Field\FieldType\PasswordItem
    'password' => PasswordItem::class,
  ];

  public function __construct(
    private readonly TypedDataManagerInterface $typedDataManager,
    private readonly ConstraintManager $constraintManager,
    private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly AdapterManager $adapterManager,
    private readonly CacheBackendInterface $cache,
    private readonly ExtensionMimeTypeGuesser|LazyExtensionMimeTypeGuesser $extensionMimeTypeGuesser,
  ) {
  }

  /**
   * @see https://json-schema.org/understanding-json-schema/reference/type
   * TRICKY: relying on \Drupal\Core\TypedData\Type\*Interface is not possible
   * because that interface conveys semantics, not storage mechanism. For
   * example: DurationInterface has 2 implementations in Drupal core:
   * - \Drupal\Core\TypedData\Plugin\DataType\TimeSpan, which is an integer
   * - \Drupal\Core\TypedData\Plugin\DataType\DurationIso8601, which is a string
   *
   * @param JsonSchema $sub_schema
   *
   * @return array<int, \Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression|\Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression>
   */

  /**
   * @param JsonSchema $schema
   *
   * @return array<int, \Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression|\Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression>
   */

  /**
   * @param JsonSchema $schema
   */
  public function iterateObjectJsonSchema(array $schema): \Generator {
    $schema = self::resolveSchemaReferences($schema);
    $primitive_type = JsonSchemaType::from(
    // TRICKY: SDC always allowed `object` for Twig integration reasons.
    // @see \Drupal\sdc\Component\ComponentMetadata::parseSchemaInfo()
      is_array($schema['type']) ? $schema['type'][0] : $schema['type']
    );

    if ($primitive_type !== JsonSchemaType::Object) {
      throw new \LogicException();
    }

    foreach ($schema['properties'] ?? [] as $prop_name => $prop_schema) {
      yield $prop_name => [
        // @see https://json-schema.org/understanding-json-schema/reference/object#required
        // @see https://json-schema.org/learn/getting-started-step-by-step#required
        'required' => in_array($prop_name, $schema['required'] ?? [], TRUE),
        'schema' => self::resolveSchemaReferences($prop_schema),
      ];
    }
  }

  /**
   * @todo Make *recursive* references work in justinrainbow/schema, see https://git.drupalcode.org/project/ui_patterns/-/blob/28cf60dd776fb349d9520377afa510b0d85f3334/src/SchemaManager/ReferencesResolver.php
   *
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
    return $schema;
  }

  /**
   * @param JsonSchema $schema
   * @return ($levels_to_recurse is positive-int ? array<int, \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression> : array<int, \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression>)
   */
  private function matchEntityProps(EntityDataDefinitionInterface $entity_data_definition, int $levels_to_recurse, JsonSchemaType $primitive_type, bool $is_required_in_json_schema, ?array $schema): array {
    if ($primitive_type === JsonSchemaType::Array) {
      assert(is_array($schema));
      // Drupal core's Field API only supports specifying "required or not",
      // and required means ">=1 value". There's no (native) ability to
      // configure a minimum number of values for a field. Plus, JSON schema
      // allows declaring that an array must be non-empty (`minItems: 1`) even
      // for an optional array (not listed in `required`). So, it is impossible
      // to support `minItems`. And in fact, marking an component prop as
      // required has the same effect as `minItems: 1`.
      // @see https://www.drupal.org/project/unlimited_field_settings
      // @see https://json-schema.org/draft/2020-12/draft-bhutton-json-schema-validation-00#rfc.section.6.4.2
      // @see https://stackoverflow.com/a/49548055
      if (!empty(array_diff(array_keys($schema), ['type', 'items', 'maxItems']))) {
        return [];
      }
      $cardinality = $schema['maxItems'] ?? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
      assert(isset($schema['items']) && isset($schema['items']['type']));
      $primitive_type = JsonSchemaType::from($schema['items']['type']);
      $schema = $schema['items'];
    }
    else {
      $cardinality = 1;
    }

    if ($primitive_type->isScalar()) {
      return $this->matchEntityPropsForScalar($entity_data_definition, $levels_to_recurse, $primitive_type, $is_required_in_json_schema, $schema, $cardinality);
    }
    else {
      return $this->matchEntityPropsForObject($entity_data_definition, $levels_to_recurse, $is_required_in_json_schema, $schema, $cardinality);
    }
  }

  /**
   * @param JsonSchema $schema
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED|int<1, max> $cardinality_in_json_schema
   * @return array<int, \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression>
   */
  private function matchEntityPropsForObject(EntityDataDefinitionInterface $entity_data_definition, int $levels_to_recurse, bool $is_required_in_json_schema, array $schema, int $cardinality_in_json_schema): array {
    $required_object_props = [];
    $all_object_props = [];
    $object_prop_matches = [];
    foreach ($this->iterateObjectJsonSchema($schema) as $name => ['required' => $sub_required, 'schema' => $sub_schema]) {
      $all_object_props[] = $name;
      if ($sub_required) {
        $required_object_props[] = $name;
      }
      // ⚠️ This does not support nested objects, so it's okay to directly
      // call ::matchEntityPropsForScalar(). If support for nested objects is
      // ever needed, this will need to call ::matchEntityProps() instead.
      $object_prop_matches[$name] = $this->matchEntityPropsForScalar($entity_data_definition, $levels_to_recurse, JsonSchemaType::from($sub_schema['type']), $sub_required, $sub_schema, $cardinality_in_json_schema);
    }

    // Invert $object_prop_matches to determine different match types.
    $inverted = [];
    foreach (array_keys($object_prop_matches) as $object_prop_name) {
      foreach ($object_prop_matches[$object_prop_name] as $field_prop_expr) {
        $field_name = match (get_class($field_prop_expr)) {
          FieldPropExpression::class => $field_prop_expr->fieldName,
          ReferenceFieldPropExpression::class => $field_prop_expr->referencer->fieldName,
          default => throw new \LogicException('Unhandled.'),
        };
        // Even though FieldPropExpression's `fieldName` can be an array at the
        // data structure level, it can only be a string here: because the logic
        // in ::matchEntityPropsForScalar() asses one entity type + bundle at a
        // time.
        assert(is_string($field_name));
        // The same field name prop should never be used multiple times; best
        // match is selected in object prop order.
        if (in_array($field_prop_expr, $inverted[$field_name] ?? [], FALSE)) {
          continue;
        }
        // Pick the first match, except:
        if (isset($inverted[$field_name][$object_prop_name])) {
          // 1. prefer non-reference matches on the field.
          if ($inverted[$field_name][$object_prop_name] instanceof ReferenceFieldPropExpression && $field_prop_expr instanceof FieldPropExpression) {
            $inverted[$field_name][$object_prop_name] = $field_prop_expr;
          }
          // 2. prefer a precise match between the component prop name and the
          //    the field prop name
          elseif ($field_prop_expr instanceof FieldPropExpression && $object_prop_name === $field_prop_expr->propName) {
            $inverted[$field_name][$object_prop_name] = $field_prop_expr;
          }
          elseif ($field_prop_expr instanceof ReferenceFieldPropExpression && $object_prop_name === $field_prop_expr->referencer->propName) {
            $inverted[$field_name][$object_prop_name] = $field_prop_expr;
          }
        }
        else {
          $inverted[$field_name][$object_prop_name] = $field_prop_expr;
        }
      }
    }

    // The minimal match: all required object props are present.
    $matches_minimal = array_filter(
      $inverted,
      fn ($supported_object_props) => empty(array_diff($required_object_props, array_keys($supported_object_props)))
    );
    ksort($matches_minimal);

    // The complete match: the complete set of object props is present.
    $matches_complete = array_filter(
      $inverted,
      fn ($supported_object_props) => array_keys($supported_object_props) == $all_object_props
    );
    ksort($matches_complete);

    $matches = [];
    // Prefer complete matches: list complete matches before minimal matches.
    foreach ($matches_complete + $matches_minimal as $field_name => $mapping) {
      // @todo Support nested/recursive/chained FieldObjectPropsExpression?
      // @see https://www.drupal.org/project/canvas/issues/3467890#comment-16036211
      /** @var array<string, \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression> $mapping */
      $matches[] = new FieldObjectPropsExpression($entity_data_definition, $field_name, NULL, $mapping);
    }
    return $matches;
  }

  /**
   * @param JsonSchema $schema
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED|int<1, max> $cardinality_in_json_schema
   * @return array<int, \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression>
   */
  private function matchEntityPropsForScalar(EntityDataDefinitionInterface $entity_data_definition, int $levels_to_recurse, JsonSchemaType $primitive_type, bool $is_required_in_json_schema, ?array $schema, int $cardinality_in_json_schema): array {
    if (!$primitive_type->isScalar()) {
      throw new \LogicException();
    }

    $matches = [];
    $field_definitions = $this->recurseDataDefinitionInterface($entity_data_definition);
    foreach ($field_definitions as $field_definition) {
      assert($field_definition instanceof FieldDefinitionInterface);
      foreach (self::IGNORE_FIELD_TYPES as $field_type_class) {
        if (is_a($field_definition->getItemDefinition()->getClass(), $field_type_class, TRUE)) {
          continue 2;
        }
      }
      if ($is_required_in_json_schema && !$field_definition->isRequired()) {
        continue;
      }
      $field_cardinality = match($field_definition instanceof FieldStorageDefinitionInterface) {
        TRUE => $field_definition->getCardinality(),
        FALSE => $field_definition->getFieldStorageDefinition()->getCardinality(),
      };
      if ($cardinality_in_json_schema !== $field_cardinality) {
        // For finite cardinalities, we can still allow a lower cardinality (>1)
        // field instance to be matched with a higher cardinality JSON schema.
        // For example: a `maxItems: 20` component prop could be populated by a
        // field instance with cardinality 5. But a single-cardinality field
        // would not make sense, because it's no longer an array.
        // All other cases would result in problematic UX.
        // @todo consider allowing/supporting (but needs UX to be designed first to disambiguate the cardinality mismatch) in https://www.drupal.org/i/3522718:
        // 1. JSON schema cardinality `unlimited`, field cardinality 1–N => would mean only partially populating an array
        // 2. JSON schema cardinality `1-N`, field cardinality `unlimited` => would mean some structured data values would not be visible; the content author would need to either be informed only the first N would be visible, or they'd need to be able to pick specific values
        if (!($field_cardinality > 1 && $cardinality_in_json_schema > $field_cardinality)) {
          continue;
        }
      }
      $properties = $this->recurseDataDefinitionInterface($field_definition);
      foreach ($properties as $property_name => $property_definition) {
        // Never match properties that are:
        // 1. DataReferenceTargetDefinitions: these are the internal
        //    implementation detail (typically named `target_id`) powering the
        //    twin DataReferenceDefinitionInterface (typically named `entity`)
        // 2. explicitly marked as internal (which means ::isInternal() cannot
        //    be used, due to its fallback to ::isComputed())
        // 3. sources for a computed property, even if they're not internal.
        // 4. on read-only non-computed base fields: these store non-user data such as the
        //    monotonically increasing integer entity ID, bundle name, entity
        //    UUID and so on.
        //    For now, the "uuid" field, to allow testing that prop shape.
        // @phpstan-ignore-next-line
        if ($property_definition instanceof DataReferenceTargetDefinition || $property_definition['internal'] === TRUE) {
          continue;
        }
        $field_property_is_source_for = $property_definition->getSetting('is source for');
        if ($field_property_is_source_for !== NULL) {
          if (!array_key_exists($field_property_is_source_for, $properties)) {
            throw new \LogicException("The property `$property_name` is a source for a non-existent other property.");
          }
          if (!$properties[$field_property_is_source_for]->isComputed()) {
            throw new \LogicException("The property `$property_name` is a source for another property, but that property is not computed.");
          }
          if ($properties[$field_property_is_source_for]->getSetting('is source for') !== NULL) {
            throw new \LogicException("Nested `is source for` situation detected; only single level allowed.");
          }
          continue;
        }
        if ($field_definition instanceof BaseFieldDefinition && $field_definition->getName() !== 'uuid' && $field_definition->isReadOnly() && !$property_definition->isComputed()) {
          continue;
        }
        $is_reference = $this->dataLeafIsReference($property_definition);
        if ($is_reference === NULL) {
          // Neither a reference nor a primitive.
          continue;
        }
        $current_entity_field_prop = new FieldPropExpression(
          $entity_data_definition,
          $field_definition->getName(),
          NULL,
          $property_name,
        );
        if ($is_reference) {
          if ($levels_to_recurse === 0) {
            continue;
          }
          // Only follow entity references, as deep as specified.
          // @see ::findFieldTypeStorageCandidates()
          if ($property_definition instanceof DataReferenceDefinitionInterface && is_a($property_definition->getClass(), EntityReference::class, TRUE)) {
            $target = $this->getConstrainedTargetDefinition($field_definition, $property_definition);

            // Matches in $target:
            // - both base + bundle fields if <=1 bundle is specified
            // - only base fields if >1 bundle is specified
            // @see \Drupal\Core\Entity\TypedData\EntityDataDefinition::getPropertyDefinitions()
            $referenced_matches = $this->matchEntityProps($target, $levels_to_recurse - 1, $primitive_type, $is_required_in_json_schema, $schema);
            foreach ($referenced_matches as $referenced_match) {
              $matches[] = new ReferenceFieldPropExpression($current_entity_field_prop, $referenced_match);
            }

            // When >1 bundle is specified, the above only matched base fields.
            // Iterate over all possible target bundles, set each on a clone of
            // $target, and hence repeat the same process as above — but exclude
            // base fields that are re-matched.
            // @see \Drupal\Core\Entity\TypedData\EntityDataDefinition::getPropertyDefinitions()
            $target_bundles = $field_definition->getItemDefinition()->getSettings()['handler_settings']['target_bundles'] ?? [];
            if (count($target_bundles) > 1) {
              $base_field_names = array_keys($target->getPropertyDefinitions());
              foreach ($target_bundles as $target_bundle) {
                assert($target->getBundles() === NULL);
                $bundle_specific_target = clone $target;
                $bundle_specific_target->setBundles([$target_bundle]);
                $referenced_matches = $this->matchEntityProps($bundle_specific_target, $levels_to_recurse - 1, $primitive_type, $is_required_in_json_schema, $schema);
                // Ignore base field matches; those are already handled by the
                // logic just before this ">1 target bundles" conditional.
                foreach ($referenced_matches as $referenced_match) {
                  $field_name = $referenced_match instanceof ReferenceFieldPropExpression
                    ? $referenced_match->referencer->fieldName
                    : $referenced_match->fieldName;
                  if (!in_array($field_name, $base_field_names, TRUE)) {
                    $matches[] = new ReferenceFieldPropExpression($current_entity_field_prop, $referenced_match);
                  }
                }
              }
            }
          }
        }
        else {
          // Extra care is necessary when matching properties on File entities:
          // any properties on the `uri` field is crucial for shape matching
          // against the expected *type* of file.

          // A property in a File entity's URI field.
          $is_file_uri_field = $entity_data_definition->getEntityTypeId() === 'file'
            && is_a($field_definition->getItemDefinition()->getClass(), FileUriItem::class, TRUE);

          // Any computed field property that depends on an entity reference
          // may be pointing to a File entity's URI field.
          $depends_on_file_uri_field = $property_definition->isComputed()
            && self::propertyDependsOnReferencedEntity($property_definition)
            // @phpstan-ignore-next-line argument.type
            && is_a(self::getReferenceDependency($property_definition)->getFieldDefinition()->getItemDefinition()->getClass(), FileUriItem::class, TRUE);

          // If either of those are true, the File entity's `FileExtension`
          // constraint must be reflected at the field property level to allow
          // for correct shape matching.
          $file_entity_constraints = match (TRUE) {
            $is_file_uri_field => $entity_data_definition->getConstraints(),
            // @phpstan-ignore-next-line argument.type
            $depends_on_file_uri_field => $this->getConstrainedTargetDefinition($field_definition, self::getReferenceDependency($property_definition))->getConstraints(),
            default => [],
          };
          if (!empty($file_entity_constraints)) {
            // Transform an entity-level `FileExtension` constraint to
            // corresponding property-level constraint.
            // @see \Drupal\file\Plugin\Validation\Constraint\FileExtensionConstraintValidator
            if (array_key_exists('FileExtension', $file_entity_constraints)) {
              // Clone to avoid polluting any static caches.
              // @todo verify if truly necessary?
              $transformed_property_data_definition = clone $property_definition;
              $transformed_property_data_definition->addConstraint(UriTargetMediaTypeConstraint::PLUGIN_ID, [
                'mimeType' => $this->fileExtensionsToTargetContentMediaType(explode(' ', $file_entity_constraints['FileExtension']['extensions'])),
              ]);
              $property_definition = $transformed_property_data_definition;
            }
          }
          // TRICKY: treat TextProcessed as a primitive, because it must retain its FilteredMarkup encapsulation to avoid Twig escaping the processed text.
          // @see \Drupal\filter\Render\FilteredMarkup
          assert(is_a($property_definition->getClass(), PrimitiveInterface::class, TRUE) || is_a($property_definition->getClass(), TextProcessed::class, TRUE));
          $field_item = $this->typedDataManager->createInstance("field_item:" . $field_definition->getType(), [
            'name' => NULL,
            'parent' => NULL,
            'data_definition' => $field_definition->getItemDefinition(),
          ]);
          $property = $this->typedDataManager->create(
            $property_definition,
            NULL,
            $property_name,
            $field_item,
          );
          // 💡 Debugging tip: put a conditional breakpoint here when figuring
          // out why a particular field instance prop is not being matched, use
          // a condition like
          // @code
          // (string) $current_entity_field_prop == 'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths'
          // @endcode
          // And add a test case to FieldForComponentSuggesterTest::provider(),
          // that will allow hitting this point in seconds.
          if ($this->dataLeafMatchesFormat($property, $primitive_type, $is_required_in_json_schema, $schema)) {
            $matches[] = $current_entity_field_prop;
          }
        }
      }
    }
    return $matches;
  }

  /**
   * Maps a set of file extensions to their corresponding media types.
   *
   * @param string[] $extensions
   *   A list of file extensions, such as ["avif", "jpg", "gif"].
   *
   * @return string
   *   The target wildcard target content media type, such as "image/*" or
   *   "video/*".
   *
   * @throws \OutOfRangeException
   *   Thrown when the list of file extensions maps to >1 content media type.
   */
  private function fileExtensionsToTargetContentMediaType(array $extensions): string {
    // @see https://github.com/json-schema-org/json-schema-spec/issues/1557
    // Determine the MIME types without inspecting any file: files are
    // not available anyway (this is operating on Typed Data
    // definitions, not concrete data). It is the responsibility of
    // the field type storing files to validate the uploaded files to
    // ensure security.
    // @see \Drupal\Tests\file\Kernel\Plugin\Validation\Constraint\FileExtensionConstraintValidatorTest
    // @see \Drupal\file\Validation\FileValidatorInterface
    $mime_types = array_filter(array_map(
      fn (string $extension): ?string => $this->extensionMimeTypeGuesser->guessMimeType("Jack.$extension"),
      $extensions
    ));
    // Strip subtypes, suffixes and parameters.
    // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/MIME_types#structure_of_a_mime_type
    // @see https://en.wikipedia.org/wiki/Media_type#Structure
    $mime_media_type_names = array_map(
      fn (string $mime_type): string => explode('/', $mime_type, 2)[0],
      $mime_types,
    );
    // Matching against multiple targeted media type names is for a
    // distant future; JSON Schema doesn't allow this either.
    // @see https://json-schema.org/understanding-json-schema/reference/non_json_data#contentmediatype-and-contentencoding
    if (count(array_unique($mime_media_type_names)) > 1) {
      throw new \OutOfRangeException();
    }
    $target_content_media_type = sprintf("%s/*", array_unique($mime_media_type_names)[0]);
    assert(UriTargetMediaTypeConstraintValidator::isValidWildCard($target_content_media_type));
    return $target_content_media_type;
  }

  /**
   * @param JsonSchema $schema
   * @return array<int, \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression>
   */
  public function findFieldInstanceFormatMatches(
    JsonSchemaType $primitive_type,
    bool $is_required_in_json_schema,
    array $schema,
    ?string $host_entity_type = NULL,
    ?string $host_entity_bundle = NULL,
  ): array {
    \ksort($schema);
    $cid = \sprintf('%s:%s:%s', $primitive_type->value, (string) $is_required_in_json_schema, \http_build_query($schema));
    if ($host_entity_type !== NULL && $host_entity_bundle !== NULL) {
      $cid .= \sprintf(':%s:%s', $host_entity_type, $host_entity_bundle);
    }
    $cached = $this->cache->get($cid);
    if ($cached !== FALSE && $cached->data) {
      return $cached->data;
    }
    $entity_type_bundles = $this->entityTypeBundleInfo->getAllBundleInfo();
    $matches = [];
    if ($host_entity_type !== NULL && $host_entity_bundle !== NULL) {
      $entity_data_definition = EntityDataDefinition::createFromDataType("entity:$host_entity_type:$host_entity_bundle");
      $matches = $this->matchEntityProps($entity_data_definition, 1, $primitive_type, $is_required_in_json_schema, $schema);
    }
    else {
      foreach ($entity_type_bundles as $entity_type_id => $bundles) {
        foreach (array_keys($bundles) as $bundle) {
          $entity_data_definition = EntityDataDefinition::createFromDataType("entity:$entity_type_id:$bundle");
          $matches = [
            ...$matches,
            ...$this->matchEntityProps($entity_data_definition, 1, $primitive_type, $is_required_in_json_schema, $schema),
          ];
        }
      }
    }
    /** @var array<\Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression> */
    $keyed_by_string = array_combine(array_map(fn ($e) => (string) $e, $matches), $matches);
    ksort($keyed_by_string);
    $instances = array_values($keyed_by_string);
    $this->cache->set($cid, $instances);
    return $instances;
  }

  private function dataDefinitionMatchesPrimitiveType(DataDefinitionInterface $data_definition, JsonSchemaType $json_schema_primitive_type, bool $is_required_in_json_schema): bool {
    $data_type_class = $data_definition->getClass();

    // Any data type that is more complex than a primitive is not accepted.
    // For example: `entity_reference`, `language_reference`, etc.
    // @see \Drupal\Core\Entity\Plugin\DataType\EntityReference
    if (!is_a($data_type_class, PrimitiveInterface::class, TRUE) && !is_a($data_type_class, TextProcessed::class, TRUE)) {
      throw new \LogicException();
    }

    $field_primitive_types = match (TRUE) {
      is_a($data_type_class, StringData::class, TRUE) => [JsonSchemaType::String],
      is_a($data_type_class, TextProcessed::class, TRUE) => [JsonSchemaType::String],
      // TRICKY: JSON Schema's `type: number` accepts both integers and floats,
      // but `type: `integer` accepts only integers.
      is_a($data_type_class, IntegerData::class, TRUE) => [JsonSchemaType::Integer, JsonSchemaType::Number],
      is_a($data_type_class, FloatData::class, TRUE) => [JsonSchemaType::Number],
      is_a($data_type_class, BooleanData::class, TRUE) => [JsonSchemaType::Boolean],
      // @todo object + array
      // - for object: initially support only a single level of nesting, then we can expect HERE a ComplexDataInterface with only primitives underneath (hence all leaves)
      // - for array: ListDefinitionInterface
      TRUE => [],
    };

    // If the primitive type does not match, this is not a candidate.
    if (!in_array($json_schema_primitive_type, $field_primitive_types)) {
      return FALSE;
    }

    // If required in component's JSON schema, it must be required in Drupal's
    // Typed Data too.
    if ($is_required_in_json_schema && !$data_definition->isRequired()) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @param JsonSchema $schema
   *   The JSON schema of the SDC prop to mach against the given field property.
   */
  private function dataLeafMatchesFormat(TypedDataInterface $data, JsonSchemaType $json_schema_primitive_type, bool $is_required_in_json_schema, ?array $schema): bool {
    // phpcs:disable Drupal.Commenting.InlineComment.NotCapital
    // 💡 Debugging tip: put a conditional breakpoint here when figuring out why
    // a particular field instance property is not being matched, use
    // @code
    // $schema['type'] == 'string' && isset($schema['contentMediaType']) && $data->getRoot()->getDataDefinition()->getDataType() == 'field_item:file_uri'
    // @endcode
    // to check:
    // - either the SDC prop for which no match is being found (by checking
    //   information in $schema)
    // - or the field type which has a field property for which a match was
    //   expected but is not being found
    // - or both (which is the case in the provided example)
    // phpcs:enable
    if (!$data->getParent()) {
      throw new \LogicException('must be a property with a field item as context for format checking');
    }
    $property_data_definition = $data->getDataDefinition();
    if (!$this->dataDefinitionMatchesPrimitiveType($property_data_definition, $json_schema_primitive_type, $is_required_in_json_schema)) {
      return FALSE;
    }

    // If the precise JSON schema is not specified, this only needs to match the
    // primitive type.
    if ($schema === NULL) {
      return TRUE;
    }

    $required_shape = $json_schema_primitive_type->toDataTypeShapeRequirements($schema);

    // One of JsonSchemaType, with no additional requirements.
    if ($required_shape === FALSE) {
      return TRUE;
    }

    $field_item = $data->getParent();
    assert($field_item instanceof FieldItemInterface);
    $field_property_name = $data->getName();

    // TRICKY: to correctly merge these, these arrays must be rekeyed to allow
    // the field type to override default property-level constraints.
    $rekey = function (array $constraints) {
      return array_combine(
        array_map(
          fn (Constraint $c): string => get_class($c),
          $constraints,
        ),
        $constraints
      );
    };

    // Gather all constraints that apply to this field item property. Note:
    // 1. all field item properties are DataType plugin instances
    // 2. DataType plugin definitions can define constraints
    // 3. all FieldType plugins defines which properties they contain and what
    //    DataType plugins they use in its `::propertyDefinitions()`
    // 4. in that `::propertyDefinitions()`, FieldType plugins can override the
    //    default constraints
    // 5. (per `DataDefinitionInterface::getConstraints()`, each constraint can
    //    be used only once — hence only overriding is possible)
    // 6. FieldType plugins can can narrow a particular use of a DataType
    //    further based on configuration in their `::getConstraints()` method by
    //    adding a `ComplexData` constraint; any constraint added here trumps a
    //    constraint defined at the property level
    //    e.g.: \Drupal\Core\Field\Plugin\Field\FieldType\NumericItemBase::getConstraints()
    // 7. EntityType plugins can similarly narrow the use of a DataType by
    //    calling `::addPropertyConstraints()` in their
    //    `::baseFieldDefinitions()`
    //   e.g.: \Drupal\path_alias\Entity\PathAlias::baseFieldDefinitions()
    // @see \Drupal\Core\TypedData\DataDefinition::addConstraint()
    // @see \Drupal\Core\Field\BaseFieldDefinition::addPropertyConstraints()
    // @see \Drupal\Core\Field\FieldConfigInterface::addPropertyConstraints()
    // @see \Drupal\Core\Field\FieldItemInterface::propertyDefinitions()
    // @see \Drupal\Core\TypedData\DataDefinitionInterface::getConstraints()
    // @see \Drupal\Core\Validation\Plugin\Validation\Constraint\ComplexDataConstraint
    // @see \Drupal\Core\Field\Plugin\Field\FieldType\NumericItemBase::getConstraints()
    $property_level_constraints = $rekey($data->getConstraints());
    $field_item_level_constraints = [];
    foreach ($field_item->getConstraints() as $field_item_constraint) {
      if ($field_item_constraint instanceof ComplexDataConstraint) {
        $field_item_level_constraints += $rekey($field_item_constraint->properties[$field_property_name] ?? []);
      }
    }
    $constraints = $field_item_level_constraints + $property_level_constraints;

    if ($required_shape instanceof DataTypeShapeRequirement) {
      if ($required_shape->constraint === 'NOT YET SUPPORTED') {
        // @phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
        @trigger_error(sprintf("NOT YET SUPPORTED: a `%s` Drupal field data type that matches the JSON schema %s.", $json_schema_primitive_type->value, json_encode($schema)), E_USER_DEPRECATED);
        return FALSE;
      }

      return $this->dataTypeShapeRequirementMatchesFinalConstraintSet($required_shape, $property_data_definition, $constraints);
    }
    else {
      // If there's >1 requirement, they must all be met.
      foreach ($required_shape->requirements as $r) {
        if (!$this->dataTypeShapeRequirementMatchesFinalConstraintSet($r, $property_data_definition, $constraints)) {
          if ($r->constraint === 'NOT YET SUPPORTED') {
            // @phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
            @trigger_error(sprintf("NOT YET SUPPORTED: a `%s` Drupal field data type that matches the JSON schema %s.", $json_schema_primitive_type->value, json_encode($schema)), E_USER_DEPRECATED);
            return FALSE;
          }
          return FALSE;
        }
      }
      return TRUE;
    }
  }

  /**
   * @param array<string, \Symfony\Component\Validator\Constraint> $constraints
   */
  private function dataTypeShapeRequirementMatchesFinalConstraintSet(DataTypeShapeRequirement $required_shape, DataDefinitionInterface $property_data_definition, array $constraints): bool {
    // Any data type that is more complex than a primitive is not accepted.
    // For example: `entity_reference`, `language_reference`, etc.
    // @see \Drupal\Core\Entity\Plugin\DataType\EntityReference
    if (!is_a($property_data_definition->getClass(), PrimitiveInterface::class, TRUE) && !is_a($property_data_definition->getClass(), TextProcessed::class, TRUE)) {
      throw new \LogicException();
    }

    // Is the data shape requirement met?
    // 1. Constraint.
    $constraint_found = in_array(
      $this->constraintManager->create($required_shape->constraint, $required_shape->constraintOptions),
      $constraints
    );
    // 2. Optionally: the interface.
    $interface_found = $required_shape->interface === NULL
      || is_a($property_data_definition->getClass(), $required_shape->interface, TRUE);
    return $constraint_found && $interface_found;
  }

  /**
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   */
  private function recurseDataDefinitionInterface(DataDefinitionInterface $dd): array {
    return match (TRUE) {
      // Entity level.
      $dd instanceof EntityDataDefinitionInterface => (function ($dd) {
        if ($dd->getClass() === ConfigEntityAdapter::class) {
          // @todo load config entity type, look at export properties?
          return [];
        }
        assert($dd->getClass() === EntityAdapter::class);
        $entity_type_id = $dd->getEntityTypeId();
        assert(is_string($entity_type_id));
        // If no bundles or multiple bundles are specified, inspect the base
        // fields. Otherwise (if a single bundle is specified), inspect all
        // fields.
        if ($dd->getBundles() !== NULL && count($dd->getBundles()) === 1) {
          return $this->entityFieldManager->getFieldDefinitions($entity_type_id, $dd->getBundles()[0]);
        }
        return $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
      })($dd),
      // Field level.
      $dd instanceof FieldDefinitionInterface => $this->recurseDataDefinitionInterface($dd->getItemDefinition()),
      $dd instanceof FieldItemDataDefinitionInterface => $dd->getPropertyDefinitions(),
      default => throw new \LogicException('Unhandled.'),
    };
  }

  private function dataLeafIsReference(TypedDataInterface|DataDefinitionInterface $td_or_dd): ?bool {
    if ($td_or_dd instanceof TypedDataInterface && !$td_or_dd->getParent() instanceof FieldItemInterface) {
      throw new \LogicException(__METHOD__ . ' was given a non-leaf.');
    }
    $dd = $td_or_dd instanceof TypedDataInterface
      ? $td_or_dd->getDataDefinition()
      : $td_or_dd;
    return match(TRUE) {
      // Reference.
      $dd instanceof DataReferenceDefinitionInterface => TRUE,
      // Primitive.
      is_a($dd->getClass(), PrimitiveInterface::class, TRUE) => FALSE,
      // ⚠️ Exception: treat processed text as a primitive.
      is_a($dd->getClass(), TextProcessed::class, TRUE) => FALSE,
      // Everything else. Most commonly:
      // - computed field properties
      // - \Drupal\Core\TypedData\Plugin\DataType\Map
      // 💁‍♂️️ Debugging tip: comment this line, uncomment the alternative.
      TRUE => NULL,
      // @phpcs:disable
      /*
      TRUE => (function ($td_or_dd) {
        match (TRUE) {
          $td_or_dd instanceof TypedDataInterface => @trigger_error(sprintf("Unhandled data type class: `%s` Drupal field type `%s` property uses `%s` data type class that is not yet supported", $td_or_dd->getParent()->getDataDefinition()->getFieldDefinition()->getType(), $td_or_dd->getName(), $td_or_dd->getDataDefinition()->getClass()), E_USER_DEPRECATED),
          $td_or_dd instanceof DataDefinitionInterface => @trigger_error(sprintf("Unhandled data type class: `%s` data type class that is not yet supported", $td_or_dd->getClass()), E_USER_DEPRECATED),

        };
        return NULL;
      })($td_or_dd),
      */
      // @phpcs:enable
    };
  }

  /**
   * @param JsonSchema $schema
   * @return \Drupal\canvas\Plugin\Adapter\AdapterInterface[]
   */
  public function findAdaptersByMatchingOutput(array $schema): array {
    return $this->adapterManager->getDefinitionsByOutputSchema($schema);
  }

  private function getConstrainedTargetDefinition(FieldDefinitionInterface $field_definition, ReferenceFieldTypePropExpression|DataReferenceDefinitionInterface $expr_or_property_definition): EntityDataDefinitionInterface {
    if ($expr_or_property_definition instanceof ReferenceFieldTypePropExpression) {
      $expr = $expr_or_property_definition;
      $field_properties = $field_definition->getFieldStorageDefinition()
        ->getPropertyDefinitions();
      $property_definition = $field_properties[$expr->referencer->propName];
    }
    else {
      $property_definition = $expr_or_property_definition;
    }
    assert($property_definition instanceof DataReferenceDefinitionInterface);
    assert(is_a($property_definition->getClass(), EntityReference::class, TRUE));

    $target = $property_definition->getTargetDefinition();
    assert($target instanceof EntityDataDefinitionInterface);
    // When referencing an entity, enrich the EntityDataDefinition with
    // constraints that are imposed by the entity reference field, to
    // narrow the matching.
    // @todo Generalize this so it works for all entity reference field types that do not allow *any* entity of the target entity type to be selected
    if (is_a($field_definition->getItemDefinition()->getClass(), FileItem::class, TRUE)) {
      $field_item = $this->typedDataManager->createInstance("field_item:" . $field_definition->getType(), [
        'name' => $field_definition->getName(),
        'parent' => NULL,
        'data_definition' => $field_definition->getItemDefinition(),
      ]);
      assert($field_item instanceof FileItem);
      $target->addConstraint('FileExtension', $field_item->getUploadValidators()['FileExtension']);
    }
    return $target;
  }

  public static function propertyDependsOnReferencedEntity(DataDefinitionInterface $data_definition): bool {
    return self::getReferenceDependency($data_definition) !== NULL;
  }

  private static function getReferenceDependency(DataDefinitionInterface $data_definition): ?ReferenceFieldTypePropExpression {
    assert(!str_starts_with($data_definition->getDataType(), 'field_item:'));

    if (!$data_definition->isReadOnly() && is_a($data_definition->getClass(), DependentPluginInterface::class, TRUE)) {
      return NULL;
    }

    // Find StructuredDataPropExpressions in the property's settings.
    $settings = $data_definition->getSettings();
    $found_expressions = [];
    array_walk_recursive($settings, function ($current) use (&$found_expressions) {
      if (is_string($current) && str_starts_with($current, StructuredDataPropExpressionInterface::PREFIX)) {
        $found_expressions[] = $current;
      }
    });

    // Check if >=1 relies on an entity reference.
    foreach ($found_expressions as $found_expression) {
      $expression = StructuredDataPropExpression::fromString($found_expression);
      if ($expression instanceof ReferenceFieldTypePropExpression) {
        return $expression;
      }
    }

    return NULL;
  }

}
