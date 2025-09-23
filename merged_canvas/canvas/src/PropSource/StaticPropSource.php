<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\canvas\PropExpressions\StructuredData\Evaluator;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;

/**
 * Contains unstructured data for 1 explicit input of a component instance.
 *
 * @todo Finalize name. "Fixed", "Local" and "Stored" all seem better. (Note: "Stored" would match nicely with StorablePropShape.)
 *
 * Always contains a FieldItemListInterface object (even if cardinality is 1),
 * to remove the need for branched logic throughout this class. However, for
 * single cardinality prop sources (i.e. those that are NOT intended to return
 * a list of values), to a caller of StaticPropSource it will appear as if they
 * interact with only a FieldItemInterface, not a FieldItemListInterface.
 *
 * @internal
 *
 * @phpstan-import-type PropSourceArray from PropSourceBase
 */
final class StaticPropSource extends PropSourceBase {

  /**
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED|int<1, max>|null $cardinality
   */
  public function __construct(
    public readonly FieldItemListInterface $fieldItemList,
    private readonly StructuredDataPropExpressionInterface $expression,
    // - which cardinality to use in case of a list (`type: array`)
    // @see \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    private readonly ?int $cardinality,
    // - (optionally) which storage settings to specify for an instance of this
    //   field type — crucial for e.g. the `enum` use case. In theory this is
    //   the same as $this->fieldItemList->getFieldDefinition()->getSettings(),
    //   but in practice that is unusable: it contains all default settings too.
    private readonly ?array $fieldStorageSettings = NULL,
    // - (optionally) which storage settings to specify for an instance of this
    //   field type — necessary for the `entity_reference` field type
    private readonly ?array $fieldInstanceSettings = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSourceTypePrefix(): string {
    return 'static';
  }

  /**
   * Two StaticPropSources have the same shape if they have identical storage.
   *
   * @return bool
   */
  public function hasSameShapeAs(StaticPropSource $other): bool {
    if ((string) $this->expression !== (string) $other->expression) {
      return FALSE;
    }
    // Cardinality, field storage settings and field instance settings all are
    // optional:
    // - cardinality defaults to 1
    if (($this->cardinality ?? 1) !== ($other->cardinality ?? 1)) {
      return FALSE;
    }
    // - field storage settings defaults to NULL
    if (($this->fieldStorageSettings ?? []) !== ($other->fieldStorageSettings ?? [])) {
      return FALSE;
    }
    // - field instance settings defaults to NULL
    if (($this->fieldInstanceSettings ?? []) !== ($other->fieldInstanceSettings ?? [])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * @return PropSourceArray
   */
  public function toArray(): array {
    $array_representation = [
      'sourceType' => $this->getSourceType(),
      'value' => $this->getValue(),
      'expression' => (string) $this->expression,
    ];
    if ($this->fieldStorageSettings !== NULL && $this->fieldStorageSettings !== []) {
      $array_representation['sourceTypeSettings']['storage'] = $this->fieldStorageSettings;
    }
    if ($this->fieldInstanceSettings !== NULL && $this->fieldInstanceSettings !== []) {
      $array_representation['sourceTypeSettings']['instance'] = $this->fieldInstanceSettings;
    }
    // Single-cardinality is the default.
    // @see ::parse()
    if ($this->cardinality !== NULL && $this->cardinality !== 1) {
      $array_representation['sourceTypeSettings']['cardinality'] = $this->cardinality;
    }

    return $array_representation;
  }

  private static function conjureFieldItemList(FieldTypePropExpression|FieldTypeObjectPropsExpression|ReferenceFieldTypePropExpression $expression, ?int $cardinality, ?array $field_storage_settings, ?array $field_instance_settings): FieldItemListInterface {
    $typed_data_manager = \Drupal::service(TypedDataManagerInterface::class);

    // First: determine field type.
    $field_type = $expression instanceof ReferenceFieldTypePropExpression
      ? $expression->referencer->fieldType
      : $expression->fieldType;

    // Second: conjure a FieldStorageDefinitionInterface instance using the:
    // - field type
    // - cardinality
    // @see \Drupal\Core\Field\FieldStorageDefinitionInterface
    // TRICKY: this does not work due to it using BaseFieldDefinition, and BaseFieldDefinition::getOptionsProvider() assuming it to exist on the host entity. Hence the use of Canvas's own \Drupal\canvas\PropSource\FieldStorageDefinition.
    // @see \Drupal\Core\Field\TypedData\FieldItemDataDefinition::createFromDataType()
    // @todo Refactor this after https://www.drupal.org/node/2280639 is fixed.
    // $field_item_definition = $typed_data_manager->createDataDefinition($data_type);
    $storage_definition = FieldStorageDefinition::create($field_type);
    // @see \Drupal\Core\Field\BaseFieldDefinition::getCardinality()
    if ($cardinality) {
      $storage_definition->setCardinality($cardinality);
    }
    $field_item_definition = $storage_definition->getItemDefinition();
    assert($field_item_definition instanceof DataDefinition);

    // Third: respect field type-specific storage and instance settings.
    if ($field_storage_settings) {
      $field_item_class = $field_item_definition->getClass();
      $storage_definition->setSettings($field_item_class::storageSettingsFromConfigData($field_storage_settings) + $field_item_definition->getSettings());
    }
    if ($field_instance_settings) {
      $field_item_class = $field_item_definition->getClass();
      $storage_definition->setSettings($field_item_class::fieldSettingsFromConfigData($field_instance_settings) + $storage_definition->getSettings());
    }

    // Fourth: instantiate a FieldItemList object using the storage definition.
    // TRICKY: FieldTypePluginManager::createFieldItemList() cannot be used
    // because it assumes a parent Typed Data object (an entity). For the same
    // reason, TypedDataManager::getPropertyInstance() is also unusable. So use
    // the lower API, and pass fewer parameters: TypedDataManager::create().
    // @see \Drupal\Core\Field\FieldTypePluginManager::createFieldItemList()
    // @see \Drupal\Core\TypedData\TypedDataManagerInterface::getPropertyInstance()
    $field_item_list = $typed_data_manager->create($storage_definition);
    assert($field_item_list instanceof FieldItemListInterface);
    return $field_item_list;
  }

  /**
   * Generates a new (empty) prop source.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED|int<1, max>|null $cardinality
   */
  public static function generate(FieldTypePropExpression|FieldTypeObjectPropsExpression|ReferenceFieldTypePropExpression $expression, ?int $cardinality, ?array $field_storage_settings = NULL, ?array $field_instance_settings = NULL): static {
    return new StaticPropSource(self::conjureFieldItemList($expression, $cardinality, $field_storage_settings, $field_instance_settings), $expression, $cardinality, $field_storage_settings, $field_instance_settings);
  }

  /**
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED|int<1, max>
   */
  private function getCardinality() : int {
    // TRICKY: unfortunately, `field.storage_settings.*` does not store
    // cardinality, but the FieldStorageConfig entity does (config schema:
    // `field.storage.*.*`). Hence the need for an additional key-value pair.
    return $this->cardinality ?? 1;
  }

  /**
   * @return \Drupal\canvas\PropSource\StaticPropSource
   *
   * @internal
   *   This is currently only intended to be used by Drupal Canvas's tests.
   */
  public function randomizeValue(): static {
    // Determine how many values to generate.
    $shape_cardinality = $this->getCardinality();
    $value_cardinality = $shape_cardinality !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
      ? $shape_cardinality
      // Randomly generate between 2 and 5 values for this array.
      : mt_rand(2, 5);

    // Clone (avoid modifying this StaticPropSource object) and randomize.
    $field_item_list = clone $this->fieldItemList;
    $field_item_list->generateSampleItems($value_cardinality);

    if ($field_item_list instanceof EntityReferenceFieldItemListInterface) {
      for ($i = 0; $i < $field_item_list->count(); $i++) {
        // TRICKY: the target_id MUST be set for this StaticPropSource
        // serialize and then restore. But Drupal core (sensibly!) does not save
        // sample entities. However, for this
        // @see \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::onChange()
        // @see \Drupal\Core\Entity\Plugin\DataType\EntityReference::isTargetNew()
        $field_item = $field_item_list[$i];
        assert($field_item instanceof EntityReferenceItem);
        if ($field_item->get('target_id')->getValue() === NULL && $field_item->get('entity')->getValue()->isNew()) {
          $entity = $field_item->get('entity')->getValue();
          $entity->save();
          $field_item->setValue(['entity' => $entity]);
        }
      }
    }
    return new StaticPropSource(
      $field_item_list,
      $this->expression,
      $this->cardinality,
      $this->fieldStorageSettings,
      $this->fieldInstanceSettings,
    );
  }

  /**
   * @param mixed $value
   *   The value to initialize the field item list with.
   * @param bool $allow_empty
   *   By default, no empty values are allowed, because they're pointless to
   *   store in a StaticPropSource.
   *   In very specific circumstances, this may be acceptable and even necessary
   *   though:
   *   - when validating
   *   - when loading stored data (a field type might change its logic for what
   *     it considers empty)
   *   - when *previewing* the user input, which may be "mid-input" and hence in
   *     a temporarily empty state.
   *   Validation will prevent empty values from being stored.
   *
   * @see \Drupal\canvas\PropSource\StaticPropSource::isMinimalRepresentation()
   */
  public function withValue(mixed $value, bool $allow_empty = FALSE): static {
    $field_item_list = clone $this->fieldItemList;
    $field_item_list->setValue($value);

    if (!$allow_empty) {
      // Detect values considered empty by the field type, and abort early.
      $before = $field_item_list->count();
      $field_item_list->filterEmptyItems();
      $after = $field_item_list->count();
      if ($before !== $after) {
        throw new \LogicException(sprintf('%s called with invalid value for field type %s.', __METHOD__, $this->fieldItemList->getFieldDefinition()->getItemDefinition()->getDataType()));
      }
    }

    return new StaticPropSource(
      $field_item_list,
      $this->expression,
      $this->cardinality,
      $this->fieldStorageSettings,
      $this->fieldInstanceSettings,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function parse(array $sdc_prop_source): static {
    // `sourceType = static` requires a value and an expression to be specified.
    $missing = array_diff(['value', 'expression'], array_keys($sdc_prop_source));
    if (!empty($missing)) {
      throw new \LogicException(sprintf('Missing the keys %s.', implode(',', $missing)));
    }
    assert(array_key_exists('value', $sdc_prop_source));
    assert(array_key_exists('expression', $sdc_prop_source));

    // First: construct an expression object from the expression string.
    $expression = StructuredDataPropExpression::fromString($sdc_prop_source['expression']);
    assert($expression instanceof FieldTypePropExpression || $expression instanceof FieldTypeObjectPropsExpression || $expression instanceof ReferenceFieldTypePropExpression);

    // Second: retrieve the field storage settings, if any.
    $cardinality = $sdc_prop_source['sourceTypeSettings']['cardinality'] ?? 1;
    $field_storage_settings = $sdc_prop_source['sourceTypeSettings']['storage'] ?? NULL;
    $field_instance_settings = $sdc_prop_source['sourceTypeSettings']['instance'] ?? NULL;

    // Third: conjure the expected FieldItemList instance.
    $field_item_list = self::conjureFieldItemList($expression, $cardinality, $field_storage_settings, $field_instance_settings);
    // TRICKY: Setting `[]` is the equivalent of emptying a field. 🤷 (NULL
    // causes *some* field widgets (e.g. image) to fail.)
    // @see \Drupal\Core\Entity\ContentEntityBase::__unset()
    $field_item_list->setValue($sdc_prop_source['value'] ?? []);

    return new StaticPropSource($field_item_list, $expression, $cardinality, $field_storage_settings, $field_instance_settings);
  }

  /**
   * Checks that the given raw prop source is a minimal representation.
   *
   * To be used when storing a StaticPropSource.
   *
   * @param array{value: mixed, expression: string, sourceType: string} $sdc_prop_source
   *   A raw static prop source.
   *
   * @return void
   *
   * @throws \LogicException
   *
   * @see \Drupal\canvas\PropSource\StaticPropSource::denormalizeValue()
   */
  public static function isMinimalRepresentation(array $sdc_prop_source): void {
    $expression = StructuredDataPropExpression::fromString($sdc_prop_source['expression']);
    assert($expression instanceof FieldTypePropExpression || $expression instanceof FieldTypeObjectPropsExpression || $expression instanceof ReferenceFieldTypePropExpression);
    $cardinality = $sdc_prop_source['sourceTypeSettings']['cardinality'] ?? NULL;
    $field_storage_settings = $sdc_prop_source['sourceTypeSettings']['storage'] ?? NULL;
    $field_instance_settings = $sdc_prop_source['sourceTypeSettings']['instance'] ?? NULL;
    $field_item_list = self::conjureFieldItemList($expression, $cardinality, $field_storage_settings, $field_instance_settings);

    $stored_value = $sdc_prop_source['value'];
    $field_item_list->setValue($stored_value);

    // Detect values considered empty by the field type, and abort early.
    // @see \Drupal\canvas\PropSource\StaticPropSource::withValue()
    $before = $field_item_list->count();
    $field_item_list->filterEmptyItems();
    $after = $field_item_list->count();
    if ($before !== $after) {
      throw new \LengthException('Field item list length is a lie, because it contains items considered empty by the field type. This is acceptable when previewing and auto-saving, but unacceptable when saving.');
    }

    // Single-cardinality StaticPropSources MUST store only a single value, in
    // minimal representation.
    $storage_definition = $field_item_list->getFieldDefinition();
    assert($storage_definition instanceof FieldStorageDefinitionInterface);
    if ($cardinality === NULL) {
      assert($field_item_list->count() === 1);
      $sole_field_item = $field_item_list->first();
      assert($sole_field_item instanceof FieldItemInterface);
      static::isMinimalFieldItemRepresentation($stored_value, $sole_field_item);
    }
    // Multiple-cardinality StaticPropSources MUST store a list of minimal
    // representations.
    else {
      if (!is_array($stored_value) || !array_is_list($stored_value)) {
        throw new \LogicException('Multiple-cardinality prop source expects a list of values.');
      }
      // The deltas can be assumed to be 0-based and sequential.
      // @see \Drupal\Core\Field\FieldItemList::setValue()
      foreach ($field_item_list as $delta => $field_item) {
        static::isMinimalFieldItemRepresentation($stored_value[$delta], $field_item);
      }
    }
  }

  private static function isMinimalFieldItemRepresentation(mixed $stored_value, FieldItemInterface $field_item): void {
    $expected_to_be_stored = $field_item->toArray();

    $item_definition = $field_item->getDataDefinition();
    assert($item_definition instanceof FieldItemDataDefinitionInterface);

    match (count($item_definition->getPropertyDefinitions())) {
      1 => (function () use ($expected_to_be_stored, $stored_value, $field_item) {
        if ($expected_to_be_stored[$field_item::mainPropertyName()] !== $stored_value) {
          throw new \LogicException(sprintf('Unexpected static prop value: %s should be %s', json_encode($stored_value), json_encode($expected_to_be_stored[$field_item::mainPropertyName()])));
        }
      })(),
      default => (function () use ($expected_to_be_stored, $stored_value, $field_item) {
        if ($expected_to_be_stored != $stored_value) {
          $optional_field_properties = array_filter($field_item->getDataDefinition()->getPropertyDefinitions(), fn ($def) => !$def->isRequired());
          $missing_expected_properties = array_diff_key($expected_to_be_stored, $stored_value);
          $missing_required_expected_properties = array_diff_key($missing_expected_properties, $optional_field_properties);
          if (!empty($missing_required_expected_properties)) {
            throw new \LogicException(sprintf('Unexpected static prop value: %s should be %s — %s properties are missing', json_encode($stored_value), json_encode($expected_to_be_stored), implode(', ', $missing_required_expected_properties)));
          }
        }
      })(),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(?FieldableEntityInterface $host_entity, bool $is_required): mixed {
    return match ($this->getCardinality()) {
      // @phpstan-ignore-next-line
      1 => Evaluator::evaluate($this->fieldItemList->first(), $this->expression, $is_required),
      default => Evaluator::evaluate($this->fieldItemList->isEmpty() ? NULL : $this->fieldItemList, $this->expression, $is_required)
    };
  }

  public function asChoice(): string {
    return (string) $this->expression;
  }

  public function getSourceType(): string {
    return self::getSourceTypePrefix() . self::SOURCE_TYPE_PREFIX_SEPARATOR . $this->fieldItemList->getItemDefinition()->getDataType();
  }

  public function getValue(): mixed {
    // ⚠️ TRICKY: we cannot use `::isEmpty()`, only `::count()`.
    // @see https://www.drupal.org/project/canvas/issues/3467870#comment-15792177
    if ($this->fieldItemList->count() === 0) {
      return match ($this->getCardinality()) {
        1 => NULL,
        default => [],
      };
    }

    $denormalized_values = array_map(
      fn (FieldItemInterface $item) => $this->denormalizeValue($item->getValue()),
      iterator_to_array($this->fieldItemList),
    );
    return match ($this->getCardinality()) {
      1 => reset($denormalized_values),
      default => $denormalized_values,
    };
  }

  /**
   * Omits the wrapping main property name for single-property field types.
   *
   * This reduces the verbosity of the data stored in `component_tree` fields,
   * which improves both space requirements and the developer experience.
   *
   * @param array<string, mixed> $field_item_value
   *   The value for this static prop source's field item, with field property
   *   names as keys.
   *
   * @return mixed|array<string, mixed>
   *   The denormalized (simplified) value.
   *
   * @see \Drupal\Core\Field\FieldItemBase::setValue()
   *  @see \Drupal\Core\Field\FieldInputValueNormalizerTrait::normalizeValue()
   */
  private function denormalizeValue(array $field_item_value): mixed {
    return match (count($this->getFieldItemDefinition()->getPropertyDefinitions())) {
      1 => $field_item_value[$this->getFieldItemDefinition()->getMainPropertyName()] ?? NULL,
      default => $field_item_value,
    };
  }

  public function getWidget(string $component_config_entity_id, ?string $component_config_entity_version, string $prop_name, string $sdc_prop_label, ?string $field_widget_plugin_id): WidgetInterface {
    // @phpstan-ignore-next-line
    $field_widget_plugin_manager = \Drupal::service('plugin.manager.field.widget');
    assert($field_widget_plugin_manager instanceof WidgetPluginManager);
    $configuration = [];
    if ($field_widget_plugin_id) {
      $configuration['type'] = $field_widget_plugin_id;
    }
    $field_storage_definition = $this->fieldItemList->getFieldDefinition();
    assert($field_storage_definition instanceof FieldStorageDefinition);
    $widget = $field_widget_plugin_manager->getInstance([
      'field_definition' => $field_storage_definition
        // TRICKY: we would need to set a name that uniquely identifies this SDC
        // prop, to avoid the static caching in `options_allowed_values()`
        // interfering. As this name is used in the client UI and can have
        // consequences, `canvas_load_allowed_values_for_component_prop`
        // won't allow  caching.
        ->setName($prop_name)
        // This is a conjured field storage definition; the widget needs precise
        // context. (For example to load the correct allowed values.)
        // @see \Drupal\Core\Field\FieldDefinitionInterface::getDisplayOptions
        ->setDisplayOptions('form', [
          'third_party_settings' => [
            'canvas' => [
              'component_id' => $component_config_entity_id,
              'component_version' => $component_config_entity_version,
              'explicit_input_prop_name' => $prop_name,
            ],
          ],
        ])
        ->setLabel($sdc_prop_label),
      'configuration' => $configuration,
      'prepare' => TRUE,
    ]);
    assert($widget !== FALSE);
    return $widget;
  }

  public function formTemporaryRemoveThisExclamationExclamationExclamation(WidgetInterface $widget, string $sdc_prop_name, bool $is_required, ?FieldableEntityInterface $host_entity, array &$form, FormStateInterface $form_state): array {
    $field_definition = $this->fieldItemList->getFieldDefinition();
    assert($field_definition instanceof FieldStorageDefinition);
    $field_definition->setRequired($is_required);

    // A field widget needs a FieldItemListInterface object. Use cloning to
    // prevent the field widget plugin from being able to modify this
    // StaticPropSource's field item list by reference.
    $field = clone $this->fieldItemList;
    // Remove empty or invalid items.
    $field->filterEmptyItems();
    // Widgets assume there is at least one field item present for editing.
    if ($field->count() === 0) {
      $field->appendItem();
    }
    // Most widgets do not need an entity context, but some do:
    // @see \Drupal\file\Plugin\Field\FieldWidget\FileWidget
    // @see \Drupal\image\Plugin\Field\FieldWidget\ImageWidget
    if ($host_entity) {
      $field->setContext(NULL, EntityAdapter::createFromEntity($host_entity));
    }
    $widget_form = $widget->form($field, $form, $form_state);
    if ($widget->getPluginId() === 'datetime_default' && !$this->fieldItemList->isEmpty()) {
      // The datetime widget needs a DrupalDateTime object as the value.
      // @todo Figure out why this is necessary — \DateTimeWidgetBase::createDefaultValue() *is* getting called, but somehow it does not result in the default value being populated unless we do this.
      // @see \Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase::createDefaultValue()
      for ($i = 0; $i < $this->fieldItemList->count(); $i++) {
        assert($this->fieldItemList[$i] !== NULL);
        $widget_form['widget'][$i]['value']['#default_value'] = new DrupalDateTime($this->fieldItemList[$i]->value);
      }
    }

    return $widget_form;
  }

  private function getFieldItemDefinition(): FieldItemDataDefinitionInterface {
    // @phpstan-ignore-next-line
    return $this->fieldItemList->getItemDefinition();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $host_entity = NULL): array {
    assert($host_entity === NULL || $host_entity instanceof FieldableEntityInterface);
    // The only dependencies are those of the used expression. If a host entity
    // is given, then `content` dependencies may appear as well; otherwise the
    // calculated dependencies will be limited to the entity types, bundle (if
    // any) and fields (if any) that this expression depends on.
    // @see \Drupal\Tests\canvas\Kernel\PropExpressionDependenciesTest
    $expression_deps = $this->expression->calculateDependencies($this->fieldItemList);

    // Let the field type plugin specify its own dependencies, based on storage
    // settings and instance settings.
    $field_item_class = $this->fieldItemList->getItemDefinition()->getClass();
    assert(is_subclass_of($field_item_class, FieldItemInterface::class));
    $instance_deps = $field_item_class::calculateDependencies($this->fieldItemList->getFieldDefinition());
    $storage_deps = $field_item_class::calculateStorageDependencies($this->fieldItemList->getFieldDefinition()->getFieldStorageDefinition());

    $dependencies = NestedArray::mergeDeep(
      $expression_deps,
      $instance_deps,
      $storage_deps,
    );
    ksort($dependencies);
    return array_map(static function ($values) {
      $values = array_unique($values);
      sort($values);
      return $values;
    }, $dependencies);
  }

}
