<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Canvas\ComponentSource;

use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\PropSource\DynamicPropSource;
use Drupal\canvas\ShapeMatcher\FieldForComponentSuggester;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Component as ComponentPlugin;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use Drupal\Core\Render\Component\Exception\InvalidComponentException;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Component\ComponentMetadata;
use Drupal\Core\Theme\Component\ComponentValidator;
use Drupal\canvas\ComponentSource\ComponentSourceBase;
use Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Component as ComponentEntity;
use Drupal\canvas\MissingHostEntityException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\PropExpressions\Component\ComponentPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropShape\PropShape;
use Drupal\canvas\PropShape\StorablePropShape;
use Drupal\canvas\PropSource\DefaultRelativeUrlPropSource;
use Drupal\canvas\PropSource\PropSource;
use Drupal\canvas\PropSource\PropSourceBase;
use Drupal\canvas\PropSource\StaticPropSource;
use Drupal\canvas\ShapeMatcher\JsonSchemaFieldInstanceMatcher;
use Drupal\canvas\Utility\TypedDataHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Explicit input UX generated from SDC metadata, using field types and widgets.
 *
 * Canvas ComponentSource plugins that do not have their own (native) explicit
 * input UX only need to map their explicit information to SDC metadata and can
 * then get an automatically generated field widget explicit UX, whose values
 * are stored in dangling field instances, by mapping schema to field types.
 *
 * @see \Drupal\Core\Theme\Component\ComponentMetadata
 * @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::computeStorablePropShape()
 *
 * They can *also* be populated using structured data whose shape matches the
 * shape specified in the SDC metadata.
 *
 * @see \Drupal\canvas\ShapeMatcher\JsonSchemaFieldInstanceMatcher
 *
 * Component Source plugins included in the Drupal Canvas module using it:
 * - "SDC"
 * - "code components"
 *
 * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent
 * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent
 *
 * @phpstan-import-type PropSourceArray from \Drupal\canvas\PropSource\PropSourceBase
 * @phpstan-import-type OptimizedExplicitInput from \Drupal\canvas\Plugin\DataType\ComponentInputs
 * @phpstan-import-type OptimizedSingleComponentInputArray from \Drupal\canvas\Plugin\DataType\ComponentInputs
 *
 * @internal
 */
abstract class GeneratedFieldExplicitInputUxComponentSourceBase extends ComponentSourceBase implements ComponentSourceWithSlotsInterface, ContainerFactoryPluginInterface {

  public const EXPLICIT_INPUT_NAME = 'props';

  /**
   * @var array<string, \Drupal\canvas\PropSource\StaticPropSource>
   */
  private array $defaultStaticPropSources = [];

  /**
   * @var array<string, \Drupal\canvas\PropSource\DefaultRelativeUrlPropSource>
   */
  private array $defaultRelativeUrlPropSources = [];
  protected ?ComponentPlugin $componentPlugin = NULL;
  protected ?ComponentMetadata $metadata;

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly ComponentValidator $componentValidator,
    private readonly WidgetPluginManager $fieldWidgetPluginManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FieldForComponentSuggester $fieldForComponentSuggester,
  ) {
    assert(array_key_exists('local_source_id', $configuration));
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @phpstan-ignore-next-line
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(ComponentValidator::class),
      $container->get('plugin.manager.field.widget'),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(FieldForComponentSuggester::class),
    );
  }

  /**
   * When validating explicit inputs, an SDC plugin instance is needed.
   *
   * This is imposed by the SDC validation infrastructure provided by Drupal
   * core.
   *
   * @see ::validateComponentInput()
   * @see \Drupal\Core\Theme\Component\ComponentValidator::validateProps()
   * @todo Deprecate and then remove this once ComponentValidator::validateProps() accepts a plugin ID + component metadata
   */
  abstract protected function getComponentPlugin(): ComponentPlugin;

  /**
   * The crucial metadata: describes the explicit inputs and slots.
   *
   * @return \Drupal\Core\Theme\Component\ComponentMetadata
   */
  public function getMetadata(): ComponentMetadata {
    if (!isset($this->metadata)) {
      $this->metadata = $this->getComponentPlugin()->metadata;
    }
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    assert(array_key_exists('prop_field_definitions', $this->configuration));
    assert(is_array($this->configuration['prop_field_definitions']));
    $dependencies = [];
    foreach ($this->configuration['prop_field_definitions'] as $prop_name => ['field_type' => $field_type, 'field_widget' => $field_widget]) {
      $field_widget_definition = $this->fieldWidgetPluginManager->getDefinition($field_widget);
      $dependencies['module'][] = $field_widget_definition['provider'];
      $prop_source = $this->getDefaultStaticPropSource($prop_name, FALSE);
      $dependencies = NestedArray::mergeDeep($dependencies, \array_diff_key($prop_source->calculateDependencies(), \array_flip(['plugin'])));
    }

    ksort($dependencies);
    return array_map(static function ($values) {
      $values = array_unique($values);
      sort($values);
      return $values;
    }, $dependencies);
  }

  /**
   * Build the default prop source for a prop.
   *
   * @param string $prop_name
   *   The prop name.
   * @param bool $validate_prop_name
   *   TRUE to validate the prop name against the current version of the SDC
   *   plugin. For past versions pass FALSE as a prop field definition may no
   *   longer exist.
   *
   * @return \Drupal\canvas\PropSource\StaticPropSource
   *   The prop source object.
   */
  private function getDefaultStaticPropSource(string $prop_name, bool $validate_prop_name = TRUE): StaticPropSource {
    if (\array_key_exists($prop_name, $this->defaultStaticPropSources)) {
      return $this->defaultStaticPropSources[$prop_name];
    }
    assert(isset($this->configuration['prop_field_definitions']));
    assert(is_array($this->configuration['prop_field_definitions']));
    $component_schema = $this->getMetadata()->schema ?? [];
    if ($validate_prop_name && !array_key_exists($prop_name, $component_schema['properties'] ?? [])) {
      throw new \OutOfRangeException(sprintf("'%s' is not a prop on the component '%s'.", $prop_name, $this->getComponentDescription()));
    }

    $sdc_prop_source = [
      'sourceType' => 'static:field_item:' . $this->configuration['prop_field_definitions'][$prop_name]['field_type'],
      'value' => $this->configuration['prop_field_definitions'][$prop_name]['default_value'],
      'expression' => $this->configuration['prop_field_definitions'][$prop_name]['expression'],
    ];
    if (array_key_exists('field_storage_settings', $this->configuration['prop_field_definitions'][$prop_name])) {
      $sdc_prop_source['sourceTypeSettings']['storage'] = $this->configuration['prop_field_definitions'][$prop_name]['field_storage_settings'];
    }
    if (array_key_exists('field_instance_settings', $this->configuration['prop_field_definitions'][$prop_name])) {
      $sdc_prop_source['sourceTypeSettings']['instance'] = $this->configuration['prop_field_definitions'][$prop_name]['field_instance_settings'];
    }
    if (array_key_exists('cardinality', $this->configuration['prop_field_definitions'][$prop_name])) {
      $sdc_prop_source['sourceTypeSettings']['cardinality'] = $this->configuration['prop_field_definitions'][$prop_name]['cardinality'];
    }

    $static_prop_source = StaticPropSource::parse($sdc_prop_source);
    $this->defaultStaticPropSources[$prop_name] = $static_prop_source;
    return $static_prop_source;
  }

  private function getDefaultRelativeUrlPropSource(string $component_id, string $prop_name): DefaultRelativeUrlPropSource {
    if (\array_key_exists($prop_name, $this->defaultRelativeUrlPropSources)) {
      return $this->defaultRelativeUrlPropSources[$prop_name];
    }
    assert(array_key_exists(0, $this->getMetadata()->schema['properties'][$prop_name]['examples'] ?? []));
    $default_relative_url_prop_source = new DefaultRelativeUrlPropSource(
    // @phpstan-ignore-next-line offsetAccess.notFound
      value: $this->getMetadata()->schema['properties'][$prop_name]['examples'][0],
      // @phpstan-ignore-next-line offsetAccess.notFound
      jsonSchema: PropShape::normalize($this->getMetadata()->schema['properties'][$prop_name])->resolvedSchema,
      componentId: $component_id,
    );
    $this->defaultRelativeUrlPropSources[$prop_name] = $default_relative_url_prop_source;
    return $default_relative_url_prop_source;
  }

  public function getSlotDefinitions(): array {
    return $this->getMetadata()->slots;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExplicitInputDefinitions(): array {
    $prop_shapes = self::getComponentInputsForMetadata($this->getSourceSpecificComponentId(), $this->getMetadata());

    return [
      'required' => $this->getMetadata()->schema['required'] ?? [],
      'shapes' => array_combine(
        array_map(fn (string $cpe) => ComponentPropExpression::fromString($cpe)->propName, array_keys($prop_shapes)),
        array_map(fn (PropShape $shape) => $shape->schema, $prop_shapes),
      ),
    ];
  }

  /**
   * @param string $plugin_id
   * @param \Drupal\Core\Theme\Component\ComponentMetadata $metadata
   *
   * @return \Drupal\canvas\PropShape\PropShape[]
   */
  public static function getComponentInputsForMetadata(string $plugin_id, ComponentMetadata $metadata): array {
    $prop_shapes = [];

    // Retrieve the full JSON schema definition from the SDC's metadata.
    // @see \Drupal\sdc\Component\ComponentValidator::validateProps()
    // @see \Drupal\sdc\Component\ComponentMetadata::parseSchemaInfo()
    /** @var array<string, mixed> $component_schema */
    $component_schema = $metadata->schema;
    foreach ($component_schema['properties'] ?? [] as $prop_name => $prop_schema) {
      // TRICKY: `Attribute`-typed props are a special case that we need to ignore.
      // Even more TRICKY, `attributes` named prop is even a more special case —
      // as it's initialized by default.
      // @see \Drupal\sdc\Twig\TwigExtension::mergeAdditionalRenderContext()
      // @see https://www.drupal.org/project/drupal/issues/3352063#comment-15277820
      // @see `canvas_test_sdc:attributes` component template as an example for
      // how to initialize the `Attribute`-typed prop.
      if (in_array(Attribute::class, $prop_schema['type'], TRUE)) {
        continue;
      }

      $component_prop_expression = new ComponentPropExpression($plugin_id, $prop_name);
      $prop_shapes[(string) $component_prop_expression] = PropShape::normalize($prop_schema);
    }

    return $prop_shapes;
  }

  /**
   * @return array<int, array{'value': mixed, 'label': 'string'}>
   *
   * @see \canvas_load_allowed_values_for_component_prop()
   * @todo Ensure that when Canvas adds translation support, that SDC
   *   `meta:enum`s are loaded from interface translation, and those for code
   *   components from config translation.
   */
  public function getOptionsForExplicitInputEnumProp(string $prop_name): array {
    $explicit_input_definitions = $this->getExplicitInputDefinitions();
    if (!array_key_exists($prop_name, $explicit_input_definitions['shapes'])) {
      throw new \LogicException("`$prop_name` is not an explicit input prop on `{$this->getPluginId()}.{$this->getSourceSpecificComponentId()}`.");
    }

    // Retrieve the JSON schema for this explicit input prop.
    $schema = (new PropShape($explicit_input_definitions['shapes'][$prop_name]))->resolvedSchema;
    if (!array_key_exists('enum', $schema)) {
      throw new \LogicException("`enum` is missing for schema of `$prop_name` explicit input prop of `{$this->getPluginId()}.{$this->getSourceSpecificComponentId()}`.");
    }
    // @todo Simplify in https://www.drupal.org/project/canvas/issues/3518247
    $raw_schema = $this->getMetadata()->schema['properties'][$prop_name] ?? [];
    if (!array_key_exists('meta:enum', $schema)) {
      if (!array_key_exists('meta:enum', $raw_schema)) {
        throw new \LogicException("`meta:enum` is missing for schema of `$prop_name` explicit input prop of `{$this->getPluginId()}.{$this->getSourceSpecificComponentId()}`.");
      }
      else {
        $schema['meta:enum'] = $raw_schema['meta:enum'];
      }
    }

    return $schema['meta:enum'];
  }

  /**
   * {@inheritdoc}
   */
  public function getExplicitInput(string $uuid, ComponentTreeItem $item, ?FieldableEntityInterface $host_entity = NULL): array {
    if (!$this->requiresExplicitInput()) {
      return [
        'resolved' => [],
        'source' => [],
      ];
    }

    // A component instance can be part of a dangling component tree if it is
    // contained by a config entity. If that config entity's component tree
    // uses DynamicPropSources, it needs a (fieldable) host entity to evaluate
    // DynamicPropSources.
    // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait::createDanglingComponentTreeItemList()
    // @see \Drupal\canvas\Entity\ContentTemplate
    $is_dangling = $item->getRoot() === $item->getParent();
    $entity = $is_dangling ? $host_entity : $item->getEntity();
    $values = $item->getInputs() ?? [];
    foreach ($values as $prop => $input) {
      $values[$prop] = $this->uncollapse($input, $prop)->toArray();
    }

    return [
      'source' => $values,
      'resolved' => array_map(
      // @phpstan-ignore-next-line
        fn(array $prop_source): mixed => PropSource::parse($prop_source)
          ->evaluate($entity, is_required: FALSE),
        $values,
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function hydrateComponent(array $explicit_input): array {
    $hydrated[self::EXPLICIT_INPUT_NAME] = $explicit_input['resolved'];

    if ($slots = $this->getSlotDefinitions()) {
      // Use the first example defined in SDC metadata, if it exists. Otherwise,
      // fall back to `"#plain_text => ''`, which is accepted by SDC's rendering
      // logic but still results in an empty slot.
      // @see https://www.drupal.org/node/3391702
      // @see \Drupal\Core\Render\Element\ComponentElement::generateComponentTemplate()
      $hydrated['slots'] = array_map(fn($slot) => $slot['examples'][0] ?? '', $slots);
    }

    return $hydrated;
  }

  /**
   * {@inheritdoc}
   */
  public function inputToClientModel(array $explicit_input): array {
    // @see PropSourceComponent type-script definition.
    // @see EvaluatedComponentModel type-script definition.
    $model = $explicit_input;

    foreach ($explicit_input['resolved'] as $prop_name => $value) {
      // Undo what ::clientModelToInput() and ::getExplicitInput() did: restore
      // the `source` to pass the necessary information to the client that
      // \Drupal\canvas\Form\ComponentInstanceForm expects (and hence
      // also ::buildConfigurationForm()).
      // Note this only changes `source`, not `resolved`, because the `resolved`
      // value must still be what the `DefaultRelativeUrlPropSource` evaluated
      // to in order to correctly render the component instance.
      // Also note that this will NOT run anymore for a given prop once the
      // Content Creator has specified a value in the generated field widget.
      if ($model['source'][$prop_name]['sourceType'] === DefaultRelativeUrlPropSource::getSourceTypePrefix()) {
        // TRICKY: use the default static prop source as-is, with its default
        // value, because:
        // - the server side can ONLY store a `StaticPropSource` if it actually
        //   contains a valid storable value (that also means not considered
        //   empty by the field type)
        // - the server side MUST fall back to a `DefaultRelativeUrlPropSource`
        //   to be able to render the component at all
        $model['source'][$prop_name] = $this->getDefaultStaticPropSource($prop_name)
          ->toArray();
      }
      // Don't duplicate value if the resolved value matches the static value.
      // TRICKY: it's thanks to the condition in this if-branch NOT being met
      // that it's possible for the preview ('resolved') to not match the input
      // ('source'): the source will retain its own value, even if that is the
      // empty array in for example the case of a default image.
      if (\array_key_exists('value', $model['source'][$prop_name]) && $value === $model['source'][$prop_name]['value']) {
        unset($model['source'][$prop_name]['value']);
      }
    }

    return $model;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresExplicitInput(): bool {
    return !empty($this->getMetadata()->schema['properties']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultExplicitInput(): array {
    $inputs = [];
    foreach (array_keys($this->configuration['prop_field_definitions']) as $prop_name) {
      assert(is_string($prop_name));
      $inputs[$prop_name] = $this->getDefaultStaticPropSource($prop_name)->toArray();
    }
    return $inputs;
  }

  /**
   * {@inheritdoc}
   */
  public function validateComponentInput(array $inputValues, string $component_instance_uuid, ?FieldableEntityInterface $entity): ConstraintViolationListInterface {
    $violations = new ConstraintViolationList();
    foreach ($inputValues as $component_prop_name => $raw_prop_source) {
      $raw_prop_source = $this->uncollapse($raw_prop_source, $component_prop_name)->toArray();
      // Store the expanded prop source with all the values populated from the
      // composite field type.
      $inputValues[$component_prop_name] = $raw_prop_source;

      if (str_starts_with($raw_prop_source['sourceType'], 'static:')) {
        try {
          \assert(\array_key_exists('expression', $raw_prop_source) && \array_key_exists('value', $raw_prop_source) && \array_key_exists('sourceType', $raw_prop_source));
          StaticPropSource::isMinimalRepresentation($raw_prop_source);
        }
        catch (\LengthException $e) {
          // During previews, empty values are intentionally allowed. Those must
          // be filtered away when validating, which then in turn MAY trigger an
          // error from ComponentValidator::validateProps() — if this is for a
          // required prop.
          // In other words: let a prop source being emptier than it portrays
          // result in the appropriate validation errors at the component level.
          // @see \Drupal\canvas\PropSource\StaticPropSource::withValue(allow_empty: TRUE)
          // @todo Expand to support multiple-cardinality.
          unset($inputValues[$component_prop_name]);
          continue;
        }
        catch (\LogicException $e) {
          $violations->add(new ConstraintViolation(
            sprintf("For component `%s`, prop `%s`, an invalid field property value was detected: %s.",
              $component_instance_uuid,
              $component_prop_name,
              $e->getMessage()),
            NULL,
            [],
            $entity,
            "inputs.$component_instance_uuid.$component_prop_name",
            $raw_prop_source,
          ));
        }
      }
    }
    try {
      $resolvedInputValues = array_map(
      // @phpstan-ignore-next-line
        fn(array $prop_source): mixed => PropSource::parse($prop_source)
          ->evaluate($entity, is_required: FALSE),
        $inputValues,
      );
    }
    catch (MissingHostEntityException $e) {
      // DynamicPropSources cannot be validated in isolation, only in the
      // context of a host content entity.
      if ($entity === NULL) {
        // This case can only be hit when using a DynamicPropSource
        // inappropriately, which is validated elsewhere.
        // @see \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeMeetsRequirementsConstraintValidator
        return $violations;
      }
      // Some component inputs (SDC props) may not be resolvable yet because\
      // required fields do not yet have values specified.
      // @see https://www.drupal.org/project/drupal/issues/2820364
      // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem::postSave()
      elseif ($entity->isNew()) {
        // Silence this exception until the required field is populated.
        return $violations;
      }
      else {
        // The required field must be populated now (this branch can only be
        // hit when the entity already exists and hence all required fields
        // must have values already), so do not silence the exception.
        throw $e;
      }
    }

    try {
      $this->componentValidator->validateProps($resolvedInputValues, $this->getComponentPlugin());
    }
    catch (ComponentNotFoundException) {
      // The violation for a missing component will be added in the validation
      // of the tree structure.
      // @see \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator
    }
    catch (InvalidComponentException $e) {
      // Deconstruct the multi-part exception message constructed by SDC.
      // @see \Drupal\Core\Theme\Component\ComponentValidator::validateProps()
      $errors = explode("\n", $e->getMessage());
      foreach ($errors as $error) {
        // An example error:
        // @code
        // [style] Does not have a value in the enumeration ["primary","secondary"]
        // @endcode
        // In that string, `[style]` is the bracket-enclosed SDC prop name
        // for which an error occurred. This string must be parsed.
        $sdc_prop_name_closing_bracket_pos = strpos($error, ']', 1);
        assert($sdc_prop_name_closing_bracket_pos !== FALSE);
        // This extracts `style` and the subsequent error message from the
        // example string above.
        $prop_name = substr($error, 1, $sdc_prop_name_closing_bracket_pos - 1);
        $prop_error_message = substr($error, $sdc_prop_name_closing_bracket_pos + 2);

        if (\str_contains($prop_name, '/')) {
          [, $prop_name] = \explode('/', $prop_name);
        }
        $violations->add(
          new ConstraintViolation(
            $prop_error_message,
            NULL,
            [],
            $entity,
            "inputs.$component_instance_uuid.$prop_name",
            $resolvedInputValues[$prop_name] ?? NULL,
          )
        );
      }
    }
    return $violations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildComponentInstanceForm(
    array $form,
    FormStateInterface $form_state,
    ?Component $component = NULL,
    string $component_instance_uuid = '',
    array $inputValues = [],
    ?EntityInterface $entity = NULL,
    array $settings = [],
  ): array {
    $transforms = [];
    $component_schema = $this->getMetadata()->schema ?? [];

    // @todo Uncomment this once it is guaranteed that the POST request to add
    // the component instance happens first.
    // assert(!is_null(\Drupal::service(ComponentTreeLoader::class)->load($entity)->getComponentTreeItemByUuid($component_instance_uuid)), 'The passed $entity does not contain the component instance being edited.');

    // Some field widgets need an entity object. Provide such a "parent" entity.
    // @see \Drupal\Core\Field\FieldItemListInterface::getEntity()
    // @see \Drupal\canvas\PropSource\StaticPropSource::formTemporaryRemoveThisExclamationExclamationExclamation()
    $entity_object_for_field_widget = match (TRUE) {
      $entity instanceof FieldableEntityInterface => $entity,
      $entity instanceof ContentTemplate => $entity->createEmptyTargetEntity(),
      default => throw new \LogicException(),
    };

    // Allow form alterations specific to Canvas component instance forms (currently
    // only "static prop sources").
    $form_state->set('is_canvas_static_prop_source', TRUE);

    $prop_field_definitions = $settings['prop_field_definitions'];

    // To ensure the order of the fields always matches the order of the schema
    // we loop over the properties from the schema, but first we have to
    // exclude props that aren't storable.
    foreach (self::getComponentInputsForMetadata($this->getSourceSpecificComponentId(), $this->getMetadata()) as $component_prop_expression => $prop_shape) {
      $storable_prop_shape = $prop_shape->getStorage();
      // @todo Remove this once every SDC prop shape can be stored. See PropShapeRepositoryTest::getExpectedUnstorablePropShapes()
      // @todo Create a status report that lists which SDC prop shapes are not storable.
      if (!$storable_prop_shape) {
        continue;
      }

      $component_prop = ComponentPropExpression::fromString($component_prop_expression);
      $sdc_prop_name = $component_prop->propName;
      // Uncollapse if set; otherwise fall back to the default static prop
      // source, but *made empty* instead of the default value.
      // Note that ::clientModelToInput() guarantees $inputValues contains a
      // value for every required prop, even when a required property is allowed
      // to be empty during editing for improved usability.
      // @see ::getDefaultExplicitInput()
      // @see ::clientModelToInput()
      // @see https://www.drupal.org/i/3529788
      assert(array_key_exists($sdc_prop_name, $inputValues) || !in_array($sdc_prop_name, $this->getExplicitInputDefinitions()['required'], TRUE));
      $source = $this->uncollapse($inputValues[$sdc_prop_name] ?? NULL, $sdc_prop_name);
      $disabled = FALSE;
      $linked_prop_source = $source instanceof DynamicPropSource ? $source : NULL;
      if (!$source instanceof StaticPropSource) {
        // @todo Build DynamicPropSource UX in https://www.drupal.org/i/3541037. Related: https://www.drupal.org/project/canvas/issues/3459234
        // @todo Design is undefined for the AdaptedPropSource UX.
        // Fall back to the static version, disabled for now where the design is undefined.
        $disabled = !$source instanceof DefaultRelativeUrlPropSource;
        $source = $this->getDefaultStaticPropSource($sdc_prop_name);
      }

      // 1. If the given static prop source matches the *current* field type
      // configuration, use the configured widget.
      // 2. Worst case: fall back to the default widget for this field type.
      // @todo Implement 2. in https://www.drupal.org/project/canvas/issues/3463996
      $field_widget_plugin_id = NULL;
      if ($source->getSourceType() === 'static:field_item:' . $prop_field_definitions[$sdc_prop_name]['field_type']) {
        $field_widget_plugin_id = $prop_field_definitions[$sdc_prop_name]['field_widget'];
      }
      assert(isset($component_schema['properties'][$sdc_prop_name]['title']));
      $label = $component_schema['properties'][$sdc_prop_name]['title'];
      assert($component instanceof Component);
      $widget = $source->getWidget($component->id(), $component->getLoadedVersion(), $sdc_prop_name, $label, $field_widget_plugin_id);
      $is_required = isset($component_schema['required']) && in_array($sdc_prop_name, $component_schema['required'], TRUE);
      $form[$sdc_prop_name] = $source->formTemporaryRemoveThisExclamationExclamationExclamation($widget, $sdc_prop_name, $is_required, $entity_object_for_field_widget, $form, $form_state);
      $form[$sdc_prop_name]['#disabled'] = $disabled;

      if ($entity instanceof ContentTemplate) {
        $suggestions = FieldForComponentSuggester::structureSuggestionsForResponse($this->fieldForComponentSuggester->suggest(
          $this->getSourceSpecificComponentId(),
          $this->getMetadata(),
          $entity->getTargetEntityDataDefinition(),
        ));
        $could_use_dynamic_prop_source = !empty($suggestions[$sdc_prop_name]);

        // If the prop is already linked, replace the widget entirely. The
        // replacement will show the linker by the field label, and replace the
        // form elements with a linked field badge.
        if ($linked_prop_source) {
          // This full replacement of the widget ensures that the resulting form
          // has consistent and valid html, regardless of field type and widget.
          $form[$sdc_prop_name]['widget'] = [
            '#type' => 'linked_prop_source',
            '#sdc_prop_name' => $sdc_prop_name,
            '#sdc_prop_label' => $label,
            '#linked_prop_source' => $linked_prop_source,
            '#field_link_suggestions' => $suggestions[$sdc_prop_name],
            '#component' => $component,
            '#is_required' => $is_required,
          ];
        }
        // If the prop can be linked, but isn't yet, add attributes that will
        // result in the prop linker appearing next to the field label.
        elseif ($could_use_dynamic_prop_source) {
          $form[$sdc_prop_name]['widget']['#prop_link_data'] = [
            'linked' => FALSE,
            'prop_name' => $form[$sdc_prop_name]['widget']['#field_name'],
            'suggestions' => $suggestions[$sdc_prop_name],
          ];
        }
      }

      $widget_definition = $this->fieldWidgetPluginManager->getDefinition($widget->getPluginId());
      if (\array_key_exists('canvas', $widget_definition) && \array_key_exists('transforms', $widget_definition['canvas'])) {
        $transforms[$sdc_prop_name] = $widget_definition['canvas']['transforms'];
      }
      else {
        throw new \LogicException(sprintf(
          "Drupal Canvas determined the `%s` field widget plugin must be used to populate the `%s` prop on the `%s` component. However, no `canvas.transforms` metadata is defined on the field widget plugin definition. This makes it impossible for this widget to work. Please define the missing metadata. See %s for guidance.",
          $field_widget_plugin_id,
          $this->getSourceSpecificComponentId(),
          $sdc_prop_name,
          'https://git.drupalcode.org/project/canvas/-/raw/0.x/canvas.api.php?ref_type=heads',
        ));
      }
    }
    $form['#attached']['canvas-transforms'] = $transforms;
    if ($entity instanceof ContentTemplate) {
      $form['#after_build'][] = [static::class, 'moveSuggestionsToLabel'];
    }
    return $form;
  }

  public static function moveSuggestionsToLabel(array $element, FormStateInterface $form_state): array {
    // Recursively traverse elements and add prop_link_data to title attributes
    static::processElementTreeLinkerLabels($element);
    return $element;
  }

  /**
   * Recursively processes the element so labels get link suggestion data.
   *
   * @param array $element
   *   The form element to process.
   * @param array $propLinkData
   *   Prop link data from a parent element to pass down.
   */
  public static function processElementTreeLinkerLabels(array &$element, array $propLinkData = []): void {
    // If this element has prop_link_data, use it for this subtree
    if (!empty($element['#prop_link_data'])) {
      $propLinkData = $element['#prop_link_data'];
    }

    // If we have prop link data and this element has a title display, add the
    // data to label attributes.
    if (!empty($propLinkData) && isset($element['#title_display'])) {
      $element['#label_attributes']['prop_link_data'] = $propLinkData;
    }

    foreach (Element::children($element) as $key) {
      static::processElementTreeLinkerLabels($element[$key], $propLinkData);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getClientSideInfo(ComponentEntity $component): array {
    $prop_field_definitions = $component->getSettings()['prop_field_definitions'];

    $field_data = [];
    $default_props_for_default_markup = [];
    $unpopulated_props_for_default_markup = [];
    $transforms = [];
    foreach (self::getComponentInputsForMetadata($this->getSourceSpecificComponentId(), $this->getMetadata()) as $component_prop_expression => $prop_shape) {
      $storable_prop_shape = $prop_shape->getStorage();
      // @todo Remove this once every SDC prop shape can be stored. See PropShapeRepositoryTest::getExpectedUnstorablePropShapes()
      // @todo Create a status report that lists which SDC prop shapes are not storable.
      if (!$storable_prop_shape) {
        continue;
      }

      $component_prop = ComponentPropExpression::fromString($component_prop_expression);
      $prop_name = $component_prop->propName;

      // Determine the default:
      // - resolved value (used for the preview of the component)
      // - source value (used to populate
      // Typically, they are different representations of the same value:
      // - resolved: value conforming to an SDC prop shape
      // - source: value as stored by the corresponding storable prop shape, so
      //   in an instance of a field type, which can either be a single field
      //   prop (for field types with a single property) or an array of field
      //   props (for field types with >1 properties)
      // @see \Drupal\canvas\PropShape\PropShape
      // @see \Drupal\canvas\PropShape\StorablePropShape
      // @see \Drupal\Core\Field\FieldItemInterface::propertyDefinitions()
      // @see ::exampleValueRequiresEntity()

      // Inspect the Component config entity to check for the presence of a
      // default value.
      // Defaults are guaranteed to exist for required props, may exist for
      // optional props. When an optional prop has no default value, the value
      // stored as the default in the Component config entity is NULL.
      // @see \Drupal\canvas\ComponentMetadataRequirementsChecker
      assert(self::exampleValueRequiresEntity($storable_prop_shape) === ($this->configuration['prop_field_definitions'][$prop_name]['default_value'] === []));
      $default_source_value = $this->configuration['prop_field_definitions'][$prop_name]['default_value'];
      $has_default_source_value = match ($default_source_value) {
        // NULL is stored to signal this is an optional SDC prop without an
        // example value.
        NULL => FALSE,
        // The empty array is stored to signal this is an SDC prop (optional or
        // required) whose example value would need an entity to be created,
        // which is not allowed.
        // @see ::exampleValueRequiresEntity()
        [] => FALSE,
        // In all other cases, a default value is present.
        default => TRUE,
      };

      // Compute the default 'resolved' value, which will be used to:
      // - generate the preview of the component
      // - populate the client-side (data) `model`
      // … which in both cases boils down to: "this value is passed directly
      // into the SDC".
      $default_resolved_value = NULL;
      // Use the stored default, if any. This is required for all required SDC
      // props, optional for all optional SDC props.
      $default_static_prop_source = $this->getDefaultStaticPropSource($prop_name);
      if ($has_default_source_value) {
        $default_resolved_value = $default_static_prop_source->evaluate(NULL, is_required: FALSE);
      }
      // One special case: example values that require a Drupal entity to
      // exist. In these cases (for either required or optional SDC props),
      // fall back to the literal example value in the SDC.
      elseif (self::exampleValueRequiresEntity($storable_prop_shape)) {
        // An example may be present in the SDC metadata, it just cannot be
        // mapped to a default value in the prop source.
        if (isset($this->getMetadata()->schema['properties'][$prop_name]['examples'][0])) {
          $default_resolved_value = $this->getMetadata()->schema['properties'][$prop_name]['examples'][0];
        }
      }

      // Collect the 'resolved' values for all SDC props, to generate a preview
      // ("default markup").
      if ($default_resolved_value !== NULL) {
        $default_props_for_default_markup[$prop_name] = $default_resolved_value;
      }
      // Track those SDC props without a 'resolved' value (because an example
      // value is missing, which is allowed for optional SDC props), because it
      // will still be necessary to generate the necessary 'source' information
      // for them (to send to ComponentInstanceForm).
      else {
        $unpopulated_props_for_default_markup[$prop_name] = NULL;
      }

      // Gather the information that the client will pass to the server to
      // generate a form.
      // @see \Drupal\canvas\Form\ComponentInstanceForm
      $field_data[$prop_name] = [
        'required' => in_array($prop_name, $this->getMetadata()->schema['required'] ?? [], TRUE),
        'jsonSchema' => array_diff_key($prop_shape->resolvedSchema, array_flip(['meta:enum', 'x-translation-context'])),
      ] + \array_diff_key($default_static_prop_source->toArray(), \array_flip(['value']));
      if ($default_resolved_value !== NULL) {
        $field_data[$prop_name]['default_values']['source'] = $default_source_value;
        $field_data[$prop_name]['default_values']['resolved'] = $default_resolved_value;
      }

      // Now that the JSON schema is available, generate the final resolved
      // example value (with relative URLs rewritten), if needed for this prop.
      if (self::exampleValueRequiresEntity($storable_prop_shape) && $default_resolved_value !== NULL) {
        $default_props_for_default_markup[$prop_name] = $field_data[$prop_name]['default_values']['resolved'] = (new DefaultRelativeUrlPropSource(
          value: $default_resolved_value,
          jsonSchema: $field_data[$prop_name]['jsonSchema'],
          componentId: $component->id(),
        ))->evaluate(NULL, is_required: FALSE);
      }

      // Build transforms from widget metadata.
      $field_widget_plugin_id = NULL;
      $static_prop_source = $storable_prop_shape->toStaticPropSource();
      $prop_field_definition = $prop_field_definitions[$prop_name];
      if ($static_prop_source->getSourceType() === 'static:field_item:' . $prop_field_definition['field_type']) {
        $field_widget_plugin_id = $prop_field_definition['field_widget'];
      }
      if ($field_widget_plugin_id === NULL) {
        continue;
      }
      $widget_definition = $this->fieldWidgetPluginManager->getDefinition($field_widget_plugin_id);
      if (!(\array_key_exists('canvas', $widget_definition) && \array_key_exists('transforms', $widget_definition['canvas']))) {
        throw new \LogicException(sprintf(
          "Drupal Canvas determined the `%s` field widget plugin must be used to populate the `%s` prop on the `%s` component. However, no `canvas.transforms` metadata is defined on the field widget plugin definition. This makes it impossible for this widget to work. Please define the missing metadata. See %s for guidance.",
          $field_widget_plugin_id,
          $component_prop->sourceSpecificComponentId,
          $component_prop->propName,
          'https://git.drupalcode.org/project/canvas/-/raw/0.x/canvas.api.php?ref_type=heads',
        ));
      }
    }

    return [
      'source' => (string) $this->getSourceLabel(),
      'build' => $this->renderComponent([self::EXPLICIT_INPUT_NAME => $default_props_for_default_markup], $component->uuid(), TRUE),
      // Additional data only needed for SDCs.
      // @todo UI does not use any other metadata - should `slots` move to top level?
      'metadata' => ['slots' => $this->getSlotDefinitions()],
      'propSources' => $field_data,
      'transforms' => $transforms,
    ];
  }

  /**
   * Returns the source label for this component.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The source label.
   */
  abstract protected function getSourceLabel(): TranslatableMarkup;

  /**
   * Build the prop settings for an SDC component.
   *
   * @param \Drupal\Core\Plugin\Component $component_plugin
   *   The SDC component.
   *
   * @return array<string, array{field_type: string, field_widget: string, expression: string, default_value: mixed, field_storage_settings: array<string, mixed>, field_instance_settings: array<string, mixed>, cardinality?: int}>
   *   The prop settings.
   */
  public static function getPropsForComponentPlugin(ComponentPlugin $component_plugin): array {
    $props = [];
    foreach (self::getComponentInputsForMetadata($component_plugin->pluginId, $component_plugin->metadata) as $cpe_string => $prop_shape) {
      $cpe = ComponentPropExpression::fromString($cpe_string);

      $storable_prop_shape = $prop_shape->getStorage();
      if (is_null($storable_prop_shape)) {
        continue;
      }

      $props[$cpe->propName] = [
        'field_type' => $storable_prop_shape->fieldTypeProp instanceof ReferenceFieldTypePropExpression
          ? $storable_prop_shape->fieldTypeProp->referencer->fieldType
          : $storable_prop_shape->fieldTypeProp->fieldType,
        'field_widget' => $storable_prop_shape->fieldWidget,
        'expression' => (string) $storable_prop_shape->fieldTypeProp,
        'default_value' => self::computeDefaultFieldValue($storable_prop_shape, $component_plugin->metadata, $cpe->propName),
        'field_storage_settings' => $storable_prop_shape->fieldStorageSettings ?? [],
        'field_instance_settings' => $storable_prop_shape->fieldInstanceSettings ?? [],
      ];
      if ($storable_prop_shape->cardinality !== NULL) {
        $props[$cpe->propName]['cardinality'] = $storable_prop_shape->cardinality;
      }
    }

    return $props;
  }

  private static function computeDefaultFieldValue(StorablePropShape $storable_prop_shape, ComponentMetadata $sdc_metadata, string $sdc_prop_name): mixed {
    // Special case.
    // TRICKY: Do not store a default value for field types that reference
    // entities, because that would require those entities to be created.
    // @see ::getClientSideInfo()
    if (self::exampleValueRequiresEntity($storable_prop_shape)) {
      return [];
    }

    assert(is_array($sdc_metadata->schema));
    // @see https://json-schema.org/understanding-json-schema/reference/object#required
    // @see https://json-schema.org/learn/getting-started-step-by-step#required
    $is_required = in_array($sdc_prop_name, $sdc_metadata->schema['required'] ?? [], TRUE);

    // @see `type: canvas.component.*`
    assert(array_key_exists('properties', $sdc_metadata->schema));

    // TRICKY: need to transform to the array structure that depends on the
    // field type.
    // @see `type: field.storage_settings.*`
    $static_prop_source = $storable_prop_shape->toStaticPropSource();
    $example_assigned_to_field_item_list = $static_prop_source->withValue(
      $is_required
        // Example guaranteed to exist if a required prop.
        ? $sdc_metadata->schema['properties'][$sdc_prop_name]['examples'][0]
        // Example may exist if an optional prop.
        : (
          array_key_exists('examples', $sdc_metadata->schema['properties'][$sdc_prop_name]) && array_key_exists(0, $sdc_metadata->schema['properties'][$sdc_prop_name]['examples'])
            ? $sdc_metadata->schema['properties'][$sdc_prop_name]['examples'][0]
            : NULL
        )
    )->fieldItemList;

    return !$example_assigned_to_field_item_list->isEmpty()
      // The actual value in the field if there is one.
      ? $example_assigned_to_field_item_list->getValue()
      // If empty: do not store anything in the Component config entity.
      : NULL;
  }

  /**
   * Whether this storable prop shape needs a (referenceable) entity created.
   *
   * TRICKY: SDCs whose storable prop shape uses an entity reference CAN NOT
   * ever have a default value specified in their corresponding Component
   * config entity.
   *
   * It is in fact possible to transform the example value in the SDC into a
   * corresponding real (saved) entity in Drupal, but that would pollute the
   * data stored in Drupal (the nodes, the media, …) with what would be
   * perceived as a nonsensical value.
   *
   * To avoid this pollution, we allow such SDC props to not specify a default
   * value for its StorablePropShape stored in the Component config entity.
   * To offer an equivalently smooth experience, with the specified example
   * value, Canvas instead is able to generate valid values for rendering the
   * SDC using a transformed-at-runtime relative URL.
   *
   * Typical examples:
   * - an SDC prop accepting an image, i.e.
   *   `json-schema-definitions://canvas.module/image`. But other
   * - an SDC prop accepting a URL for a link, i.e.
   *   `type: string, format: uri-reference`
   *
   * This is only necessary for URL-shaped props, because URLs must be
   * resolvable (by the browser), and for a relative URL to be resolvable it
   * must be rewritten for the current site. By contrast, other prop shapes
   * work in isolation.
   *
   * @see \Drupal\canvas\PropSource\DefaultRelativeUrlPropSource
   * @see \Drupal\canvas\ComponentSource\UrlRewriteInterface
   */
  public static function exampleValueRequiresEntity(StorablePropShape $storable_prop_shape): bool {
    if ($storable_prop_shape->fieldTypeProp instanceof ReferenceFieldTypePropExpression) {
      return TRUE;
    }

    if ($storable_prop_shape->fieldTypeProp instanceof FieldTypePropExpression) {
      return self::fieldTypePropExpressionExampleRequiresEntity($storable_prop_shape->fieldTypeProp) ?? FALSE;
    }

    // @phpstan-ignore-next-line instanceof.alwaysTrue
    if ($storable_prop_shape->fieldTypeProp instanceof FieldTypeObjectPropsExpression) {
      if ($storable_prop_shape->fieldTypeProp->fieldType === 'entity_reference') {
        return TRUE;
      }
      else {
        foreach ($storable_prop_shape->fieldTypeProp->objectPropsToFieldTypeProps as $field_type_prop) {
          if ($field_type_prop instanceof ReferenceFieldTypePropExpression) {
            return TRUE;
          }

          // If this is a field property that computes the combination of
          // multiple other field properties, then this property may actually
          // also be relying on a (referenced) entity.
          // @see \Drupal\canvas\Plugin\DataType\ComputedUrlWithQueryString
          // @todo Consider dropping this in favor of adding adapter support in https://www.drupal.org/project/canvas/issues/3464003
          $indirectly_uses_entity = self::fieldTypePropExpressionExampleRequiresEntity($field_type_prop);
          if ($indirectly_uses_entity === NULL) {
            continue;
          }
          return $indirectly_uses_entity;
        }
      }
    }
    return FALSE;
  }

  private static function fieldTypePropExpressionExampleRequiresEntity(FieldTypePropExpression $field_type_prop): ?bool {
    $property = TypedDataHelper::conjureFieldItemObject($field_type_prop->fieldType)->getProperties(TRUE)[$field_type_prop->propName] ?? NULL;
    assert($property !== NULL);
    // Detect if this is a field property relying on other properties.
    if (!$property instanceof DependentPluginInterface) {
      return NULL;
    }
    return JsonSchemaFieldInstanceMatcher::propertyDependsOnReferencedEntity($property->getDataDefinition());
  }

  /**
   * {@inheritdoc}
   */
  public function clientModelToInput(string $component_instance_uuid, ComponentEntity $component, array $client_model, ?FieldableEntityInterface $host_entity, ?ConstraintViolationListInterface $violations = NULL): array {
    $props = [];

    $required_props = $this->getExplicitInputDefinitions()['required'];
    foreach (($client_model['source'] ?? []) as $prop => $prop_source) {
      $is_required_prop = in_array($prop, $required_props, TRUE);
      // The client should always provide a resolved value when providing a
      // corresponding source but may not.
      $prop_value = $client_model['resolved'][$prop] ?? NULL;
      try {
        // TRICKY: this is always set, *except* in the case of an auto-saved
        // code component that just gained a new prop.
        $default_source_value = $this->configuration['prop_field_definitions'][$prop]['default_value'] ?? NULL;

        // Valueless prop, for the case where an example is provided that cannot
        // be be expressed as/stored in the field type in the matched
        // `StaticPropSource`. This is true for any example values that must be
        // transformed into browser-resolvable URLs, rather than component
        // -relative URLs: links, image URLs, video URLs, etc.
        // These example values are used both in Canvas's preview and when rendering
        // the live site. The Content Author must be given the opportunity to
        // specify a value different from the example. But both are powered by
        // different prop sources:
        // - actual values specified by the Content Creator are represented in
        //   `StaticPropSource`s
        // - example values (of this very specific nature that a URL rewrite is
        //   needed) specified by the Component Developer are represented in
        //   `DefaultRelativeUrlPropSource`s
        // Note: example values that *can* be stored in the field type powering
        // the `StaticPropSource`, are and must be stored in there — those would
        // never hit this edge case.
        // This happens when the Content Creator instantiates a component with a
        // video/image prop (required or optional) that has a default value, and
        // no value is specified in the generated field widget, when either:
        // - the component is freshly instantiated; no value was specified yet
        // - the prop's field widget has had its value erased by the Content
        //   Creator (e.g. removed the image picked from the media library)
        // In these cases, fall back to `DefaultRelativeUrlPropSource`.
        // @see \Drupal\canvas\PropSource\DefaultRelativeUrlPropSource
        // @see ::exampleValueRequiresEntity()
        if ($default_source_value === []) {
          assert($this->configuration['prop_field_definitions'][$prop]['default_value'] === []);
          if (array_key_exists(0, $this->getMetadata()->schema['properties'][$prop]['examples'] ?? [])) {
            // Detect 2 possible `resolved` values from the client model:
            // 1. the empty array
            // 2. an exact match for what's in the client-side info
            // Ignore these and fall back fall back to the example value stored
            // in the component itself,
            // @see ::getClientSideInfo()
            $client_side_info = $this->getClientSideInfo($component);
            \assert(isset($client_side_info['propSources'][$prop]['jsonSchema']));
            if (empty($prop_value) || $prop_value == $client_side_info['propSources'][$prop]['default_values']['resolved']) {
              $props[$prop] = $this->getDefaultRelativeUrlPropSource($component->id(), $prop)->toArray();
              continue;
            }
          }
        }

        // @see PropSourceComponent type-script definition.
        // @see EvaluatedComponentModel type-script definition.
        // For static props undo what ::inputToClientModel() did: restore the
        // omitted `'value'` in cases where it is the same as the source value.
        if (str_starts_with($prop_source['sourceType'] ?? '', StaticPropSource::getSourceTypePrefix()) && !\array_key_exists('value', $prop_source)) {
          $prop_source['value'] = $prop_value;
        }
        $source = PropSource::parse($prop_source);
        // Make sure we can evaluate this prop source with the passed values.
        $evaluated = $source->evaluate($host_entity, $is_required_prop);

        // Optional component props that evaluate to NULL can be omitted:
        // storing these would be a waste of storage space.
        if (!$is_required_prop && $evaluated === NULL) {
          continue;
        }

        // Required string component props that are completely free-form (so:
        // without a non-zero `minLength`, without a `format` or `pattern`) that
        // evaluate to '' must be retained: while the empty string is NOT
        // considered a valid value, this is the fallback behavior Canvas opts for
        // to enhance the user experience: it allows a component to render even
        // at the point in time where a Content Author has *emptied* the string
        // input, as they're thinking about what string they do want.
        // ⚠️ This won't work for components whose logic specifically checks for
        // an empty string and refuses to render then.
        // @todo Expand to support multiple-cardinality.
        if ($is_required_prop && $evaluated === '' && $this->getExplicitInputDefinitions()['shapes'][$prop] === ['type' => 'string']) {
          // Confirm that *if* this weren't special-cased, that this would
          // indeed enter the next branch, which would cause it to be skipped.
          // @todo Consider adding a new `GracefulDegradationPropSource` to
          // encapsulate this similarly to `DefaultRelativeUrlPropSource`.
          assert(!$source instanceof StaticPropSource || ($source->fieldItemList->count() > 0 && $source->fieldItemList->isEmpty()));
        }
        // 💡 Automatically inform developers of missing client-side transforms,
        // which is the most likely explanation for a value sent by the Canvas UI
        // not being accepted by the field type. However, gracefully degrade and
        // log a deprecation error.
        // @see https://en.wikipedia.org/wiki/Robustness_principle
        elseif ($source instanceof StaticPropSource && $source->fieldItemList->count() > 0 && $source->fieldItemList->isEmpty()) {
          // @todo Investigate in https://www.drupal.org/project/canvas/issues/3535024, and preferably add extra guardrails and convert this to an exception
          // @phpcs:ignore Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed
          @trigger_error(sprintf('Client-side transformation for the `%s` prop failed: `%s` provided, but the %s data type logic considers it to be empty, hence indicating a mismatch.', $prop, json_encode($prop_value), $source->getSourceType()), E_USER_DEPRECATED);
          continue;
        }
      }
      catch (\OutOfRangeException) {
        // If this is a required property without a value, we can leave
        // subsequent validation to bubble up any errors.
        // @see \Drupal\canvas\PropExpressions\StructuredData\Evaluator::doEvaluate()
        continue;
      }
      $props[$prop] = $this->collapse($source, $prop);
    }

    return $props;
  }

  public function optimizeExplicitInput(array $values): array {
    foreach ($values as $prop => $input) {
      // Every input for a component instance of this ComponentSource plugin
      // base class MUST be a PropSourceBase, which all are stored as arrays.
      // @see \Drupal\canvas\PropSource\PropSourceBase::toArray()
      if (!\is_array($input) || !\array_key_exists('sourceType', $input)) {
        // The inputs have already been stored collapsed. Prove using assertions
        // (which does not have a production performance impact).
        assert($this->uncollapse($input, $prop) instanceof StaticPropSource);
        assert($this->uncollapse($input, $prop)->hasSameShapeAs($this->getDefaultStaticPropSource($prop)));
        continue;
      }
      // phpcs:ignore
      /** @var PropSourceArray $input */
      $source = PropSource::parse($input);
      $collapsed_input = $this->collapse($source, $prop);
      if ($input !== $collapsed_input) {
        $values[$prop] = $collapsed_input;
      }
    }
    return $values;
  }

  /**
   * Collapse prop source for storage whenever possible.
   *
   * StaticPropSources are dangling field item lists, which require a lot of
   * metadata to be known: field type, storage settings, instance settings and
   * expression.
   * When a StaticPropSource is being stored (to populate some component prop),
   * check if it matches that metadata in the `prop_field_definitions` for this
   * component instance's referenced version of the Component config entity. If
   * it does match, all metadata can be omitted, which significantly reduces the
   * amount of data stored.
   *
   * @param \Drupal\canvas\PropSource\PropSourceBase $source
   *
   * @return OptimizedExplicitInput|PropSourceArray
   *   Either:
   *   - the collapsed prop source storage representation, which means either a
   *     scalar or an array without a `sourceType` key
   *   - the uncollapsed prop source storage representation, which means this
   *     will be an array with a `sourceType` key.
   *
   * @see ::uncollapse()
   */
  private function collapse(PropSourceBase $source, string $prop_name): mixed {
    // @todo Simplify this to just `if ($source instanceof StaticPropSource && $source->hasSameShapeAs($this->getDefaultStaticPropSource($prop_name))) { return $source->getValue(); }` in https://www.drupal.org/project/canvas/issues/3532414
    if ($source instanceof StaticPropSource) {
      try {
        $default_source = $this->getDefaultStaticPropSource($prop_name);
        return $source->hasSameShapeAs($default_source)
          ? $source->getValue()
          : $source->toArray();
      }
      catch (\OutOfRangeException) {
        // TRICKY: https://www.drupal.org/node/3500386 and its test coverage
        // assume that even auto-saves of code components can have their props
        // appear. This never really made sense, but especially no longer since
        // we introduced component versions. It never made sense though, because
        // no entry would exist in `prop_field_definitions` for the code
        // component, meaning no widget would ever have appeared.
        return $source->toArray();
      }
    }
    return $source->toArray();
  }

  /**
   * Uncollapses a (collapsed or not) prop source.
   *
   * @param OptimizedExplicitInput|PropSourceArray $value
   * @param string $prop_name
   *
   * @return \Drupal\canvas\PropSource\PropSourceBase
   *
   * @see ::collapse()
   */
  private function uncollapse(mixed $value, string $prop_name): PropSourceBase {
    if (!\is_array($value) || !\array_key_exists('sourceType', $value)) {
      return $this->getDefaultStaticPropSource($prop_name)->withValue($value, allow_empty: TRUE);
    }
    // phpcs:ignore
    /** @var PropSourceArray $value */
    return PropSource::parse($value);
  }

}
