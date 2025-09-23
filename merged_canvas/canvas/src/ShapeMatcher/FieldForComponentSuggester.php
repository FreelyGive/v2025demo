<?php

declare(strict_types=1);

namespace Drupal\canvas\ShapeMatcher;

use Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\Component\ComponentMetadata;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
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
    private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
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
      $processed_matches[$cpe]['instances'] = [];
      if ($host_entity_type) {
        $processed_matches[$cpe]['instances'] = array_filter(
          $m['instances'],
          fn(FieldPropExpression|FieldObjectPropsExpression|ReferenceFieldPropExpression $e) => $e instanceof ReferenceFieldPropExpression
            ? $e->referencer->entityType->getDataType() === $host_entity_type->getDataType()
            : $e->entityType->getDataType() === $host_entity_type->getDataType()
        );
      }

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
      // @todo Ensure these expressions do not break: https://www.drupal.org/project/canvas/issues/3452848
      $suggestions[$cpe]['instances'] = [];
      if ($host_entity_type) {
        $suggestions[$cpe]['instances'] = array_combine(
          array_map(
            function (FieldPropExpression|FieldObjectPropsExpression|ReferenceFieldPropExpression $e) use ($field_definitions, $host_entity_type_id, $host_entity_type_bundle) {
              $field_name = $e instanceof ReferenceFieldPropExpression
                ? $e->referencer->fieldName
                : $e->fieldName;
              // Even though FieldPropExpression's `fieldName` can be an array
              // at the data structure level, it can only be a string here:
              // because the logic in JsonSchemaFieldInstanceMatcher asses one
              // entity type + bundle at a time.
              assert(is_string($field_name));
              $field_definition = $field_definitions[$field_name];
              assert($field_definition instanceof FieldDefinitionInterface);
              assert($field_definition->getItemDefinition() instanceof FieldItemDataDefinitionInterface);
              // Generate a label for the suggestion:
              // - one that points to the entity field if ALL field props are
              //   present in the expression
              // - one that describes the subset of the entity field otherwise,
              //   with explicit (developer-friendly, user-overwhelming) info on
              //   which field props are present vs absent.
              // To correctly represent this, this must take into account what
              // JsonSchemaFieldInstanceMatcher may or may not match. It will
              // never match:
              // - DataReferenceTargetDefinition field props: it considers these
              //   irrelevant; it's only the twin DataReferenceDefinition that
              //   is relevant
              // - props explicitly marked as internal
              // @see \Drupal\Core\TypedData\DataDefinition::isInternal
              $used_field_props = (array) static::getUsedFieldProps($e);
              $relevant_field_props = array_filter(
                $field_definition->getItemDefinition()->getPropertyDefinitions(),
                // @phpstan-ignore-next-line
                fn (DataDefinitionInterface $def) => !$def instanceof DataReferenceTargetDefinition && $def['internal'] !== TRUE,
              );
              return match (count($used_field_props)) {
                count($relevant_field_props) => (string) $this->t("This @entity's @field-label", [
                  '@entity' => $this->entityTypeBundleInfo->getBundleInfo($host_entity_type_id)[$host_entity_type_bundle]['label'],
                  '@field-label' => $field_definition->getLabel(),
                ]),
                default => (string) $this->t("Subset of this @entity's @field-label: @field-prop-labels-used (@field-prop-used-count of @field-prop-total-count props — absent: @field-prop-labels-absent)", [
                  '@entity' => $this->entityTypeBundleInfo->getBundleInfo($host_entity_type_id)[$host_entity_type_bundle]['label'],
                  '@field-label' => $field_definition->getLabel(),
                  '@field-prop-labels-used' => implode(', ', $used_field_props),
                  '@field-prop-used-count' => count($used_field_props),
                  '@field-prop-total-count' => count($relevant_field_props),
                  '@field-prop-labels-absent' => implode(', ', array_diff(array_keys($relevant_field_props), $used_field_props)),
                ])
              };
            },
            $m['instances']
          ),
          $m['instances']
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

}
