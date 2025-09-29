<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\EcosystemSupport;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\Plugin\Component;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;
use Drupal\canvas\ShapeMatcher\FieldForComponentSuggester;
use Drupal\canvas\ShapeMatcher\JsonSchemaFieldInstanceMatcher;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Checks that instances of all field types can be mapped to SDC props.
 *
 * TRICKY: all of the shape matching infrastructure is aimed at finding field
 * props that fit into a given set of SDC props. But here we actually need to
 * test the INVERSE, to ensure that every field prop can be matched against some
 * SDC prop.
 * That's why this test:
 * 1. installs every module providing >=1 field type
 * 2. creates both a required and optional instance of every field type on the
 *    `entity_test` entity type
 * 3. installs the module providing Canvas's special `all-props` SDC, which has one
 *    prop of EVERY possible shape (JSON Schema `type`, `format`, etc.)
 * 4. then asks the Canvas infrastructure for suggesting all compatible field
 *    instances (2.) for the `all-props` SDC (3.)
 *
 * The result is that the purpose of this test is achieved while truly using the
 * very same infrastructure the rest of Canvas uses.
 *
 * @todo Also test non-default FieldStorageConfig setting in https://www.drupal.org/project/canvas/issues/3512848
 *
 * @covers \Drupal\canvas\ShapeMatcher\FieldForComponentSuggester
 * @see \Drupal\Tests\canvas\Kernel\FieldForComponentSuggesterTest
 * @covers \Drupal\canvas\ShapeMatcher\JsonSchemaFieldInstanceMatcher
 * @see \Drupal\Tests\canvas\Kernel\PropShapeToFieldInstanceTest
 * @see docs/shape-matching-into-field-types.md#3.1.2.a
 * @group canvas
 */
final class FieldInstanceSupportTest extends EcosystemSupportTestBase {

  /**
   * The current % of supported field types whose instances can be matched.
   */
  public const COMPLETION = 0.8928571428571429;

  /**
   * The current % of supported field type props.
   */
  public const COMPLETION_PROPS = 0.9024390243902439;

  /**
   * Supported field types (keys), with explicitly unsupported props (values).
   *
   * Most of the unsupported field props are due to core bugs: these are marked
   * with FALSE ("not a real bug"), the real ones are marked with TRUE.
   *
   * @var array<string, array<string, bool>>
   */
  public const SUPPORTED = [
    'boolean' => [],
    'changed' => [],
    'comment' =>
      [
        // 🐛 Core bug: these are computed properties that are read at entity
        // load time from the comment_entity_statistics table.
        // @see \Drupal\comment\Hook\CommentHooks::entityStorageLoad
        'cid' => FALSE,
        // 🐛 Core bug: these are computed properties that are read at entity
        // load time from the comment_entity_statistics table.
        // @see \Drupal\comment\Hook\CommentHooks::entityStorageLoad
        'last_comment_uid' => FALSE,
        // 🐛 Core bug: these are computed properties that are read at entity
        // load time from the comment_entity_statistics table.
        // @see \Drupal\comment\Hook\CommentHooks::entityStorageLoad
        'last_comment_name' => FALSE,
      ],
    'created' => [],
    'daterange' => [
      // 🐛 Core bug: this is the computed equivalent of `value`, should be marked internal.
      // @see \Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem::propertyDefinitions()
      'start_date' => FALSE,
      // 🐛 Core bug: this is the computed equivalent of `end_value`, should be marked internal.
      // @see \Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem::propertyDefinitions()
      'end_date' => FALSE,
    ],
    'datetime' => [
      // 🐛 Core bug: this is the computed equivalent of `value`, should be marked internal.
      // @todo Give this similar treatment as `daterange` in https://www.drupal.org/project/canvas/issues/3512853
      'date' => FALSE,
    ],
    'email' => [],
    'entity_reference' => [],
    'file' => [],
    'file_uri' => [
      // 🐛 Core bug: this is the computed equivalent of `value`, should be marked internal.
      // @todo Give this similar treatment as `daterange` in https://www.drupal.org/project/canvas/issues/3512853
      'url' => FALSE,
    ],
    'float' => [],
    'image' => [
      'srcset_candidate_uri_template' => FALSE,
    ],
    'integer' => [],
    'link' => [
      // @todo Decide in https://www.drupal.org/project/canvas/issues/3512849 whether this is okay or not; if it is: document rationale here.
      'options' => FALSE,
      // We want to support the computed 'url' field instead as this handles
      // resolving URIs such as entity:node/1, base:/node/1 and
      // route:entity.node.canonical;node=1
      'uri' => FALSE,
    ],
    'list_float' => [],
    'list_integer' => [],
    // Note that 'password' is deliberately not here (unsupported) as we don't
    // want any of its properties to be associated with a dynamic prop source.
    'path' => [
      // 🐛 Core bug: PathFieldItemList is entirely computed so the individual
      // properties are therefore also computed.
      'alias' => FALSE,
      // 🐛 Core bug: PathFieldItemList is entirely computed so the individual
      // properties are therefore also computed.
      'pid' => FALSE,
      // 🐛 Core bug: PathFieldItemList is entirely computed so the individual
      // properties are therefore also computed.
      'langcode' => FALSE,
    ],
    'string' => [],
    'string_long' => [],
    'timestamp' => [],
    'uri' => [],
    'uuid' => [],
    'text' => [],
    'text_long' => [],
    'text_with_summary' => [],
  ];

  /**
   * Intentionally unsupported field instances' field types.
   *
   * @var array<lowercase-string, array{class: class-string, exceptions: array<array>}>
   */
  public const INTENTIONALLY_UNSUPPORTED = JsonSchemaFieldInstanceMatcher::IGNORE_FIELD_TYPES;

  private const CANVAS_TEST_FIELD_PREFIX = 'test_';

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // @todo Core bug: this is missing config schema: `type: field.storage_settings.uri` does not exist! This is being fixed in https://www.drupal.org/project/drupal/issues/3324140.
    'field.storage.entity_test.test_required__file_uri',
    'field.storage.entity_test.test_optional__file_uri',
    // @todo Core bug: this is missing config schema: `type: field.storage_settings.uuid` does not exist! This is being fixed in https://www.drupal.org/project/drupal/issues/3324140.
    'field.storage.entity_test.test_required__uuid',
    'field.storage.entity_test.test_optional__uuid',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The module providing the sample SDC to test all JSON schema types.
    'sdc_test_all_props',
    // The minimum infrastructure to create fields: a test-only entity type plus
    // the `field` module.
    'entity_test',
    'field',
    // All core modules providing field types.
    'comment',
    'datetime',
    'datetime_range',
    'file',
    'image',
    'link',
    'options',
    'path',
    'telephone',
    'text',
    // Modules that field type-providing modules depend on.
    'filter',
    'media',
  ];

  public function test(): void {
    // Assert that every module which provides >=1 field type is installed,
    // except Layout Builder. At a later time, a Layout Builder-to-Experience
    // Builder upgrade path will be provided.
    $this->assertSame(['layout_builder'], self::getUninstalledStableModulesWithPlugin('Plugin/Field/FieldType'));

    // Create a required and optional configurable field of EVERY field type on
    // the `entity_test` entity type.
    $this->installEntitySchema('entity_test');
    $created_fields = $this->createFieldsForAllFieldTypes();

    // Generate expectations:
    // - each field type must be presentable via an SDC (required + optional)
    // - for each field type, *all* field props must be presentable via an SDC.
    $entity_data = EntityDataDefinition::createFromDataType('entity:entity_test:entity_test');
    $expected_fields = [];
    $expected_field_props = [];
    $all_field_props = [];
    $expected_supported_fields = [];
    $expected_unsupported_fields = [];
    $expected_supported_field_props = [];
    foreach ($entity_data->getPropertyDefinitions() as $field_name => $field_definition) {
      assert($field_definition instanceof FieldDefinitionInterface);
      if (!str_starts_with($field_name, self::CANVAS_TEST_FIELD_PREFIX)) {
        continue;
      }
      $expected_fields[] = $field_name;
      $field_type = $field_definition->getType();
      if (array_key_exists($field_type, self::SUPPORTED)) {
        $expected_supported_fields[] = $field_name;
      }
      if (\array_key_exists($field_type, self::INTENTIONALLY_UNSUPPORTED) && empty(self::INTENTIONALLY_UNSUPPORTED[$field_type]['exceptions'])) {
        $expected_unsupported_fields[] = $field_name;
        // Remove from expected fields.
        $expected_fields = \array_diff($expected_fields, [$field_name]);
        // Don't consider the properties of unsupported fields.
        continue;
      }
      assert($field_definition->getItemDefinition() instanceof FieldItemDataDefinitionInterface);
      foreach ($field_definition->getItemDefinition()->getPropertyDefinitions() as $field_prop_name => $field_prop_definition) {
        // It makes no sense to map reference *targets*, only the *actual*
        // references. IOW: ignore `target_id` on entity reference fields, only
        // expect `entity` to need to be mapped.
        if ($field_prop_definition instanceof DataReferenceTargetDefinition) {
          continue;
        }
        // Similarly, it makes no sense to expect matches for the field
        // properties that are the source for generating another field property.
        if ($field_prop_definition->getSetting('is source for') !== NULL) {
          continue;
        }
        $all_field_props[] = "$field_name.$field_prop_name";
        // All field props are expected to be supported by Canvas, except the ones
        // that are for known core bugs.
        if (!array_key_exists($field_type, self::SUPPORTED) || (self::SUPPORTED[$field_type][$field_prop_name] ?? TRUE) === TRUE) {
          $expected_field_props[] = "$field_name.$field_prop_name";
        }
        // All known-to-be-supported field types are expected to have all props
        // supported, except the ones known to not yet work, either due to a
        // core bug, or due to an Canvas bug.
        if (array_key_exists($field_type, self::SUPPORTED) && !array_key_exists($field_prop_name, self::SUPPORTED[$field_type])) {
          $expected_supported_field_props[] = "$field_name.$field_prop_name";
        }
      }
    }
    sort($expected_fields);
    sort($expected_supported_fields);
    sort($all_field_props);
    sort($expected_field_props);
    sort($expected_supported_field_props);

    // Ensure the Typed Data representation is in sync with the fields that were
    // created. This assertion is technically unnecessary, but helps ensure this
    // test itself is accurate.
    $this->assertEqualsCanonicalizing([
      ...$expected_fields,
      ...$expected_unsupported_fields,
    ], $created_fields);

    // Perform the actual shape matching: find suggestions for every prop in the
    // test-only `all-props` SDC, which contains EVERY possible SDC prop shape.
    $component = \Drupal::service(ComponentPluginManager::class)->find('sdc_test_all_props:all-props');
    assert($component instanceof Component);
    $suggestions = $this->container->get(FieldForComponentSuggester::class)
      ->suggest(
        $component->getPluginId(),
        $component->metadata,
        EntityDataDefinition::createFromDataType('entity:entity_test:entity_test'),
      );

    // Invert the results from shape matching: for this test we need:
    // - NOT: "SDC -> field (prop)"
    // - but: "field (prop) -> SDC"
    $compatible_sdc_prop_shapes_per_field = [];
    $compatible_sdc_prop_shapes_per_field_prop = [];
    foreach ($suggestions as $cpe => ['instances' => $suggested_instances]) {
      foreach ($suggested_instances as $expr) {
        assert($expr instanceof StructuredDataPropExpressionInterface);
        $field_name = match (get_class($expr)) {
          FieldPropExpression::class, FieldObjectPropsExpression::class => $expr->fieldName,
          ReferenceFieldPropExpression::class => $expr->referencer->fieldName,
        };
        // Even though FieldPropExpression's `fieldName` can be an array at the
        // data structure level, it can only be a string here: because the logic
        // in JsonSchemaFieldInstanceMatcher asses one entity type + bundle at
        // time.
        assert(is_string($field_name));
        if (!str_starts_with($field_name, self::CANVAS_TEST_FIELD_PREFIX)) {
          continue;
        }

        // First: "field -> SDC".
        $compatible_sdc_prop_shapes_per_field[$field_name][] = $cpe;
        // Second: "field prop -> SDC".
        foreach ((array) FieldForComponentSuggester::getUsedFieldProps($expr) as $field_prop_name) {
          $compatible_sdc_prop_shapes_per_field_prop["$field_name.$field_prop_name"][] = $cpe;
        }
      }
    }
    ksort($compatible_sdc_prop_shapes_per_field);
    ksort($compatible_sdc_prop_shapes_per_field_prop);

    // Does the reality match the claims in this test's constants?
    // Note: a direct comparison against `::SUPPORTED` is impossible because
    // that lists supported field types, whereas this test generates both a
    // required and optional instance of each field type, effectively doubling
    // that list. That doubling expectation is what `$expected_supported_fields`
    // is for.
    // 💁‍♂️️ Debugging tip: put a breakpoint here and inspect $compatible_sdc_prop_shapes_per_field and $expected_supported_field_props.
    $this->assertSame([], array_values(array_diff($expected_supported_fields, array_keys($compatible_sdc_prop_shapes_per_field))), 'The known supported field types are actually supported.');
    self::assertSame([], \array_intersect(\array_keys($compatible_sdc_prop_shapes_per_field), $expected_unsupported_fields), 'The known supported field types are actually supported.');
    $actually_supported_fields = array_intersect($expected_fields, array_keys($compatible_sdc_prop_shapes_per_field));
    $missing_fields = array_diff($expected_fields, array_keys($compatible_sdc_prop_shapes_per_field));
    $this->assertSame([], array_values(array_diff($expected_supported_fields, $actually_supported_fields)), 'Field types that were expected to be supported are NOT.');
    $this->assertSame([], array_values(array_diff($actually_supported_fields, $expected_supported_fields)), 'Field types that were NOT expected to be supported are.');
    $this->assertSame(
      self::COMPLETION,
      count($actually_supported_fields) / count($expected_fields),
      sprintf('Not yet supported: a JSON schema (prop shape) for the following fields: %s', implode(', ', $missing_fields))
    );
    // @phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
    @trigger_error(sprintf('Not yet supported: a JSON schema (prop shape) for the following fields: %s', implode(', ', $missing_fields)), E_USER_DEPRECATED);

    // Verify that also at the field type props level, all expectations are met.
    $this->assertSame([], array_values(array_diff($expected_supported_field_props, array_keys($compatible_sdc_prop_shapes_per_field_prop))), 'The known supported field types are actually supported, for all their field props.');
    $actually_supported_field_props = array_intersect($expected_field_props, array_keys($compatible_sdc_prop_shapes_per_field_prop));
    $missing_field_props = array_diff($expected_field_props, array_keys($compatible_sdc_prop_shapes_per_field_prop));
    $this->assertSame([], array_values(array_diff($expected_supported_field_props, $actually_supported_field_props)), 'Field type props that were expected to be supported are NOT.');
    $this->assertSame([], array_values(array_diff($actually_supported_field_props, $expected_supported_field_props)), 'Field type props that were NOT expected to be supported are.');
    $this->assertSame(
      self::COMPLETION_PROPS,
      count($actually_supported_field_props) / count($expected_field_props),
      sprintf('Not yet supported: a JSON schema (prop shape) for the following field properties: %s', implode(', ', $missing_field_props))
    );
    // @phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
    @trigger_error(sprintf('Not yet supported: a JSON schema (prop shape) for the following field properties: %s', implode(', ', $missing_field_props)), E_USER_DEPRECATED);
  }

  private function createFieldsForAllFieldTypes(): array {
    $expected_fields = [];

    $entity_type_id = $bundle = 'entity_test';

    $field_type_definitions = $this->container->get('plugin.manager.field.field_type')->getDefinitions();
    ksort($field_type_definitions);

    foreach ($field_type_definitions as $field_type_id => $def) {
      if ($def['provider'] === 'entity_test') {
        continue;
      }
      // There is no need to map Canvas component trees *into* Canvas component trees.
      if ($def['class'] === ComponentTreeItem::class) {
        continue;
      }
      foreach ([TRUE, FALSE] as $required) {
        $field_name = implode('', [
          self::CANVAS_TEST_FIELD_PREFIX,
          $required ? 'required__' : 'optional__',
          $field_type_id,
        ]);
        FieldStorageConfig::create([
          'entity_type' => $entity_type_id,
          'type' => $field_type_id,
          'field_name' => $field_name,
          'settings' => \Drupal::service('plugin.manager.field.field_type')->getDefaultStorageSettings($field_type_id),
        ])->save();
        FieldConfig::create([
          'entity_type' => $entity_type_id,
          'bundle' => $bundle,
          'type' => $field_type_id,
          'field_name' => $field_name,
          'settings' => \Drupal::service('plugin.manager.field.field_type')->getDefaultFieldSettings($field_type_id),
        ])
          ->setRequired($required)
          ->save();
        $expected_fields[] = $field_name;
      }
    }
    return $expected_fields;
  }

}
