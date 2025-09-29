<?php

declare(strict_types=1);

namespace Drupal\canvas\ShapeMatcher;

use Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;
use Drupal\canvas\PropSource\DynamicPropSource;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\Component\ComponentMetadata;
use Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType;
use Drupal\canvas\Plugin\Adapter\AdapterInterface;
use Drupal\canvas\PropExpressions\Component\ComponentPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression;

/**
 * @todo Rename things for clarity: this handles all props for an SDC simultaneously, JsonSchemaFieldInstanceMatcher handles a single prop at a time
 */
final class FieldForComponentSuggester {

  use StringTranslationTrait;

  public function __construct(
    private readonly JsonSchemaFieldInstanceMatcher $propMatcher,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {}

  /**
   * @param string $component_plugin_id
   * @param \Drupal\Core\Theme\Component\ComponentMetadata $component_metadata
   * @param \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface|null $host_entity_type
   *   Host entity type, if the given component is being used in the context of
   *   an entity.
   *
   * @return array<string, array{required: bool, instances: array<string, \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression>, adapters: array<AdapterInterface>}>
   */
  public function suggest(string $component_plugin_id, ComponentMetadata $component_metadata, ?EntityDataDefinitionInterface $host_entity_type): array {
    $host_entity_type_bundle = $host_entity_type_id = NULL;
    if ($host_entity_type) {
      $host_entity_type_id = $host_entity_type->getEntityTypeId();
      assert(is_string($host_entity_type_id));
      $bundles = $host_entity_type->getBundles();
      assert(is_array($bundles) && array_key_exists(0, $bundles));
      $host_entity_type_bundle = $bundles[0];
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($host_entity_type_id, $host_entity_type_bundle);
    }

    // 1. Get raw matches.
    $raw_matches = $this->getRawMatches($component_plugin_id, $component_metadata, $host_entity_type_id, $host_entity_type_bundle);

    // 2. Process (filter and order) matches based on context and what Drupal
    //    considers best practices.
    $processed_matches = [];
    foreach ($raw_matches as $cpe => $m) {
      // Instance matches: filter to the ones matching the current host entity
      // type + bundle.
      if ($host_entity_type) {
        $m['instances'] = array_filter(
          $m['instances'],
          fn($expr) => self::getHostEntityDataDefinition($expr)->getDataType() === $host_entity_type->getDataType(),
        );
      }

      // Bucket the raw matches by entity type ID, bundle and field name.
      // The field name order is determined by the form display, to ensure a
      // familiar order for site builders.
      $bucketed = [];
      foreach ($m['instances'] as $expr) {
        $expr_entity_data_definition = self::getHostEntityDataDefinition($expr);
        $expr_entity_data_type = $expr_entity_data_definition->getDataType();

        // When first encountering a new entity type + bundle, generate an empty
        // array structure in which to fit all of the raw matches, keyed by
        // field, in the order of the entity form display. (Later, filter away
        // empty ones).
        if (!array_key_exists($expr_entity_data_type, $bucketed)) {
          assert(is_string($expr_entity_data_definition->getEntityTypeId()));
          assert(is_array($expr_entity_data_definition->getBundles()));
          assert(count($expr_entity_data_definition->getBundles()) === 1);
          $expected_order = $this->entityDisplayRepository->getFormDisplay(
            $expr_entity_data_definition->getEntityTypeId(),
            $expr_entity_data_definition->getBundles()[0],
          )->getComponents();
          uasort($expected_order, SortArray::sortByWeightElement(...));
          $bucketed[$expr_entity_data_type] = array_fill_keys(
            array_keys($expected_order),
            [],
          );
        }

        // Push each expression into the right (field) bucket.
        $bucketed[$expr_entity_data_type][self::getFieldName($expr)][] = $expr;
      }
      // Keep only non-empty (field) buckets.
      $bucketed = array_map('array_filter', $bucketed);
      $processed_matches[$cpe]['instances'] = $bucketed;

      // @todo filtering
      $processed_matches[$cpe]['adapters'] = $m['adapters'];
    }

    // 3. Generate appropriate labels for each. And specify whether required.
    $suggestions = [];
    foreach ($processed_matches as $cpe => $m) {
      // Required property or not?
      $prop_name = ComponentPropExpression::fromString($cpe)->propName;
      /** @var array<string, mixed> $schema */
      $schema = $component_metadata->schema;
      $suggestions[$cpe]['required'] = in_array($prop_name, $schema['required'] ?? [], TRUE);

      // Field instances.
      $suggestions[$cpe]['instances'] = [];
      if ($host_entity_type && !empty($m['instances'])) {
        assert([$host_entity_type->getDataType()] === array_keys($m['instances']));
        $debucketed = NestedArray::mergeDeep(...$m['instances'][$host_entity_type->getDataType()]);
        $suggestions[$cpe]['instances'] = array_combine(
          array_map(
            function (FieldPropExpression|FieldObjectPropsExpression|ReferenceFieldPropExpression $e) use ($field_definitions) {
              $field_name = self::getFieldName($e);
              $field_definition = $field_definitions[$field_name];
              assert($field_definition instanceof FieldDefinitionInterface);
              assert($field_definition->getItemDefinition() instanceof FieldItemDataDefinitionInterface);
              // To correctly represent this, this must take into account what
              // JsonSchemaFieldInstanceMatcher may or may not match. It will
              // never match:
              // - DataReferenceTargetDefinition field props: it considers these
              //   irrelevant; it's only the twin DataReferenceDefinition that
              //   is relevant
              // - props explicitly marked as internal
              // @see \Drupal\Core\TypedData\DataDefinition::isInternal
              $main_property = $field_definition->getItemDefinition()->getMainPropertyName();
              assert(is_string($main_property));
              $used_field_props = (array) static::getUsedFieldProps($e);
              return match (self::usesMainProperty($e, $field_definition)) {
                TRUE => match ($e instanceof ReferenceFieldPropExpression) {
                  FALSE => (string) $this->t("@field-label", [
                    '@field-label' => $field_definition->getLabel(),
                  ]),
                  TRUE => (string) $this->t("@field-label → @referenced-entity-type → @referenced-field", [
                    '@field-label' => $field_definition->getLabel(),
                    // Only a single level of indirection is surfaced,
                    // @phpstan-ignore-next-line property.notFound
                    '@referenced-entity-type' => $e->referenced->entityType->getLabel(),
                    '@referenced-field' => implode(', ', (array) self::getFieldName($e->referenced)),
                  ]),
                },
                FALSE => (string) $this->t("@field-label (only @field-prop-labels-used)", [
                  '@field-label' => $field_definition->getLabel(),
                  '@field-prop-labels-used' => implode(', ', $used_field_props),
                ])
              };
            },
            $debucketed
          ),
          $debucketed
        );
      }

      // Adapters.
      $suggestions[$cpe]['adapters'] = array_combine(
      // @todo Introduce a plugin definition class that provides a guaranteed label, which will allow removing the PHPStan ignore instruction.
      // @phpstan-ignore-next-line
        array_map(fn (AdapterInterface $a): string => (string) $a->getPluginDefinition()['label'], $m['adapters']),
        $m['adapters']
      );
      // Sort alphabetically by label.
      ksort($suggestions[$cpe]['adapters']);
    }

    return $suggestions;
  }

  public static function getUsedFieldProps(FieldPropExpression|ReferenceFieldPropExpression|FieldObjectPropsExpression $expr): string|array {
    return match (get_class($expr)) {
      FieldPropExpression::class => $expr->propName,
      ReferenceFieldPropExpression::class => $expr->referencer->propName,
      FieldObjectPropsExpression::class => array_map(
        fn (FieldPropExpression|ReferenceFieldPropExpression $obj_expr) => self::getUsedFieldProps($obj_expr),
        $expr->objectPropsToFieldProps
      ),
    };
  }

  /**
   * @return array<string, array{instances: array<int, \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression|\Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression|\Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression>, adapters: array<\Drupal\canvas\Plugin\Adapter\AdapterInterface>}>
   */
  private function getRawMatches(string $component_plugin_id, ComponentMetadata $component_metadata, ?string $host_entity_type, ?string $host_entity_bundle): array {
    $raw_matches = [];

    foreach (GeneratedFieldExplicitInputUxComponentSourceBase::getComponentInputsForMetadata($component_plugin_id, $component_metadata) as $cpe_string => $prop_shape) {
      $cpe = ComponentPropExpression::fromString($cpe_string);
      // @see https://json-schema.org/understanding-json-schema/reference/object#required
      // @see https://json-schema.org/learn/getting-started-step-by-step#required
      $is_required = in_array($cpe->propName, $component_metadata->schema['required'] ?? [], TRUE);
      $schema = $prop_shape->resolvedSchema;

      $primitive_type = JsonSchemaType::from($schema['type']);

      $instance_candidates = $this->propMatcher->findFieldInstanceFormatMatches($primitive_type, $is_required, $schema, $host_entity_type, $host_entity_bundle);
      $adapter_candidates = $this->propMatcher->findAdaptersByMatchingOutput($schema);
      $raw_matches[(string) $cpe]['instances'] = $instance_candidates;
      $raw_matches[(string) $cpe]['adapters'] = $adapter_candidates;
    }

    return $raw_matches;
  }

  private static function getHostEntityDataDefinition(FieldPropExpression|FieldObjectPropsExpression|ReferenceFieldPropExpression $expr): EntityDataDefinitionInterface {
    return $expr instanceof ReferenceFieldPropExpression
      ? $expr->referencer->entityType
      : $expr->entityType;
  }

  private static function getFieldName(FieldPropExpression|FieldObjectPropsExpression|ReferenceFieldPropExpression $expr): string {
    $expr_field_name = match (get_class($expr)) {
      ReferenceFieldPropExpression::class => $expr->referencer->fieldName,
      FieldPropExpression::class, FieldObjectPropsExpression::class => $expr->fieldName,
    };
    // TRICKY: FieldPropExpression::$fieldName can be an array, but only
    // when used in a reference.
    // @see https://www.drupal.org/i/3530521
    assert(is_string($expr_field_name));
    return $expr_field_name;
  }

  private static function usesMainProperty(FieldPropExpression|FieldObjectPropsExpression|ReferenceFieldPropExpression $expr, FieldDefinitionInterface $field_definition): bool {
    // Easiest case: a reference field's entire purpose is to reference, so
    // following the reference definitely is considered using the main property.
    if ($expr instanceof ReferenceFieldPropExpression) {
      return TRUE;
    }

    assert($field_definition->getItemDefinition() instanceof FieldItemDataDefinitionInterface);
    $main_property = $field_definition->getItemDefinition()->getMainPropertyName();
    assert(is_string($main_property));

    $used_props = (array) self::getUsedFieldProps($expr);
    assert(count($used_props) >= 1);

    // Easy case: if the main property is used directly.
    if (in_array($main_property, $used_props, TRUE)) {
      return TRUE;
    }

    // Otherwise, check if one of the used field properties is a computed one
    // that depends on the main one.
    // Drupal core does not have native support for this; Canvas adds additional
    // metadata to be able to determine this. Any contributed field types that
    // wish to have computed properties automatically matched/suggested, need to
    // provide this additional metadata too.
    // @see \Drupal\canvas\Plugin\Field\FieldTypeOverride\ImageItemOverride
    // @see \Drupal\canvas\Plugin\DataType\ComputedUrlWithQueryString
    foreach ($used_props as $prop_name) {
      $property_definition = $field_definition->getItemDefinition()->getPropertyDefinition($prop_name);
      assert($property_definition !== NULL);
      $expr_used_by_computed_property = JsonSchemaFieldInstanceMatcher::getReferenceDependency($property_definition);
      if ($expr_used_by_computed_property === NULL) {
        continue;
      }
      // Final sanity check: the reference expression found in the computed
      // property definition's settings MUST target the field type used by this
      // field instance.
      assert($expr_used_by_computed_property->referencer->fieldType === $field_definition->getType());
      return TRUE;
    }

    return FALSE;
  }

  public static function structureSuggestionsForResponse(array $suggestions): array {
    return array_combine(
    // Top-level keys: the prop names of the targeted component.
      array_map(
        fn (string $key): string => ComponentPropExpression::fromString($key)->propName,
        array_keys($suggestions),
      ),
      array_map(
        fn (array $instances): array => array_combine(
        // Second level keys: opaque identifiers for the suggestions to
        // populate the component prop.
          array_map(
            fn (StructuredDataPropExpressionInterface $expr): string => \hash('xxh64', (string) $expr),
            array_values($instances),
          ),
          // Values: objects with "label" and "source" keys, with:
          // - "label": the human-readable label that the Content Template UI
          //   should present to the human
          // - "source": the array representation of the DynamicPropSource that,
          //   if selected by the human, the client should use verbatim as the
          //   source to populate this component instance's prop.
          array_map(
            function (string $label, StructuredDataPropExpressionInterface $expr) {
              return [
                'label' => $label,
                // @phpstan-ignore-next-line argument.type
                'source' => (new DynamicPropSource($expr))->toArray(),
              ];
            },
            array_keys($instances),
            array_values($instances),
          ),
        ),
        array_column($suggestions, 'instances'),
      ),
    );
  }

}
