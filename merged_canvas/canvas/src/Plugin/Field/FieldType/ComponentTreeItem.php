<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldType;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Block\MessagesBlockPluginInterface;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\DataReferenceInterface;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\MissingComponentInputsException;
use Drupal\canvas\Plugin\DataType\ConfigEntityVersionAdapter;
use Drupal\canvas\PropSource\ContentAwareDependentInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * A component instance in a component tree.
 *
 * @todo Implement PreconfiguredFieldUiOptionsInterface?
 * @todo How to achieve https://www.previousnext.com.au/blog/pitchburgh-diaries-decoupled-layout-builder-sprint-1-2?
 * @see https://git.drupalcode.org/project/metatag/-/blob/2.0.x/src/Plugin/Field/FieldType/MetatagFieldItem.php
 *
 * @phpstan-import-type ComponentConfigEntityId from \Drupal\canvas\Entity\Component
 * @phpstan-import-type ConfigDependenciesArray from \Drupal\canvas\Entity\VersionedConfigEntityInterface
 * @phpstan-type ComponentTreeItemPropName 'uuid'|'inputs'|'component_id'|'component'|'parent_item'|'slot'|'parent_uuid'|'label'|'component_version'
 *
 * @property \Drupal\canvas\HydratedTree $hydrated
 */
#[FieldType(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup("Drupal Canvas"),
  description: new TranslatableMarkup("Field to use Drupal Canvas for presenting these entities"),
  default_formatter: "canvas_naive_render_sdc_tree",
  // @todo Revisit this prior to 1.0.
  // @see https://www.drupal.org/project/canvas/issues/3497926
  no_ui: TRUE,
  list_class: ComponentTreeItemList::class,
  // This only makes sense in a multi-value context: each item is a node in the
  // component tree.
  cardinality: FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
  constraints: [
    'ValidComponentTreeItem' => [],
    'ComponentTreeMeetRequirements' => [
      // Only StaticPropSources may be used, because using DynamicPropSources is
      // a decision that should be made at the Content Type Template level by a
      // Site Builder, not by each Content Creator.
      // @see https://www.drupal.org/project/canvas/issues/3455629
      'inputs' => [
        'absence' => [
          'dynamic',
          // @todo Allow adapters that consume a single shape and output that same single shape in https://www.drupal.org/project/canvas/issues/3536115
          'adapter',
        ],
        'presence' => NULL,
      ],
      'tree' => [
        'absence' => [
          // Components implementing either of these 2 interfaces are only
          // allowed to live at the PageRegion level.
          // @see \Drupal\canvas\Entity\PageRegion
          // @see `type: canvas.page_region.*`
          TitleBlockPluginInterface::class,
          MessagesBlockPluginInterface::class,
        ],
        'presence' => NULL,
      ],
    ],
  ],
  // @see docs/data-model.md
  // @see \Drupal\content_translation\Hook\ContentTranslationHooks::fieldInfoAlter()
  column_groups: [
    'inputs' => [
      'label' => new TranslatableMarkup('Component input values'),
      'translatable' => TRUE,
      'columns' => [
        'inputs',
        // Even when keeping the same component tree, content authors should
        // be able to specify a translated label to provide context.
        'label',
      ],
    ],
    'tree' => [
      'label' => new TranslatableMarkup('Component tree'),
      'translatable' => TRUE,
      // If the tree is translated, then the inputs also need to be.
      'require_all_groups_for_translation' => TRUE,
      'columns' => [
        'parent_uuid',
        'slot',
        'uuid',
        'component_id',
        'component_version',
      ],
    ],
  ],
)]
class ComponentTreeItem extends FieldItemBase {

  public const string PLUGIN_ID = 'component_tree';

  use ComponentTreeItemListInstantiatorTrait;

  // phpcs:disable Drupal.Commenting.DataTypeNamespace.DataTypeNamespace
  /**
   * {@inheritdoc}
   *
   * @param ComponentTreeItemPropName $name
   *
   * @return ($name is 'parent_item' ? \Drupal\canvas\Plugin\DataType\ParentComponentReference : ($name is 'inputs' ? \Drupal\canvas\Plugin\DataType\ComponentInputs : ($name is 'component' ? \Drupal\Core\Entity\Plugin\DataType\EntityReference : \Drupal\Core\TypedData\Plugin\DataType\StringData)))
   */
  // phpcs:enable Drupal.Commenting.DataTypeNamespace.DataTypeNamespace
  public function get($name) {
    // @phpstan-ignore-next-line
    return parent::get($name);
  }

  /**
   * Calculates all dependencies of the field item (all field props).
   *
   * @return ConfigDependenciesArray
   *
   * @see \Drupal\Component\Plugin\DependentPluginInterface
   */
  public function calculateFieldItemValueDependencies(?FieldableEntityInterface $host_entity = NULL): array {
    // Every field property that has dependencies on config or extensions must
    // implement DependentPluginInterface to ensure accurate dependency (i.e.
    // usage) tracking.
    $dependencies = [];
    $component = $this->getComponent();
    if ($component !== NULL) {
      $dependencies['config'] = [$component->getConfigDependencyName()];
    }
    foreach ($this->getProperties() as $property) {
      if ($property instanceof DependentPluginInterface) {
        $dependencies = NestedArray::mergeDeep($dependencies, $property->calculateDependencies());
      }
      elseif ($property instanceof ContentAwareDependentInterface) {
        $dependencies = NestedArray::mergeDeep($dependencies, $property->calculateDependencies($host_entity));
      }
    }

    $dependency_types = ['config', 'content', 'module', 'theme'];

    // Normalize.
    ksort($dependencies);
    $normalized_dependencies = [];
    foreach ($dependency_types as $type) {
      $deps_for_type = array_unique($dependencies[$type] ?? []);
      if ($type === 'module') {
        $deps_for_type = array_diff($deps_for_type, [
          // `core` is always present.
          'core',
          // This very field type is provided by Drupal Canvas, so
          // obviously this module is also always present.
          'canvas',
        ]);
      }
      sort($deps_for_type);
      $normalized_dependencies[$type] = $deps_for_type;
    }
    return $normalized_dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(FieldDefinitionInterface $field_definition): array {
    $dependencies = parent::calculateDependencies($field_definition);

    if (empty($field_definition->getDefaultValueLiteral())) {
      return $dependencies;
    }

    $default_value = $field_definition->getDefaultValueLiteral();
    $list = self::staticallyCreateDanglingComponentTreeItemList(\Drupal::typedDataManager());
    $list->setValue($default_value);

    $dependencies = NestedArray::mergeDeep($dependencies, $list->calculateDependencies());
    foreach ($list as $item) {
      \assert($item instanceof ComponentTreeItem);
      $dependencies = NestedArray::mergeDeep(
        $dependencies,
        $item->calculateFieldItemValueDependencies(NULL),
      );
    }
    // Remove duplicates and sort into a reliable order.
    return \array_map(function (array $dependencies): array {
      sort($dependencies);
      return \array_values(\array_unique($dependencies));
    }, $dependencies);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'parent_uuid' => [
          'description' => 'UUID of the parent component instance',
          'type' => 'varchar_ascii',
          // These are case-insensitive.
          'binary' => FALSE,
          // These are UUIDs
          'length' => 36,
          // NULL represents either:
          // - the root of the tree
          // - or the root of a bonsai tree (a tree in a content template's exposed slot)
          // In the latter case, `slot` must match an exposed slot of the associated `ContentTemplate`.
          // @see \Drupal\canvas\Plugin\Validation\Constraint\ValidParentAndSlotConstraintValidator
          'not null' => FALSE,
        ],
        'slot' => [
          'description' => 'Machine name of the slot in the parent component instance',
          'type' => 'varchar_ascii',
          // These are arbitrary machine names with no enforced length.
          'length' => 255,
          // NULL represents the root of the tree.
          'not null' => FALSE,
        ],
        'uuid' => [
          'description' => 'UUID of the component instance',
          'type' => 'varchar_ascii',
          // These are case-insensitive.
          'binary' => FALSE,
          // These are UUIDs
          'length' => 36,
          'not null' => TRUE,
        ],
        'component_id' => [
          'description' => 'The Component config entity ID.',
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
        ],
        'component_version' => [
          'description' => 'The Component config entity version identifier.',
          'type' => 'varchar_ascii',
          // These are xxh64 hashes.
          'length' => 16,
          'not null' => TRUE,
        ],
        'inputs' => [
          'description' => 'The input for this component instance in the component tree.',
          'type' => 'json',
          'pgsql_type' => 'jsonb',
          'mysql_type' => 'json',
          'sqlite_type' => 'json',
          'not null' => FALSE,
        ],
        'label' => [
          'description' => 'Optional label for the component instance to provide context for content authors',
          'type' => 'varchar',
          'length' => 255,
          // NULL means no label, meaning the Component config entity label will
          // be shown ("inherited") to the content author.
          // @see \Drupal\canvas\Entity\Component::$label
          'not null' => FALSE,
        ],
      ],
      'indexes' => [
        'component_id' => ['component_id'],
        'component_id_version' => ['component_id', 'component_version'],
        'parent_slot' => ['parent_uuid', 'slot'],
        'slot' => ['slot'],
        'uuid' => ['uuid'],
      ],
      'foreign keys' => [
        // @todo Add the "hash" part the proposal at https://www.drupal.org/project/drupal/issues/3440578
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['parent_uuid'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Parent component instance UUID'))
      ->setSetting('case_sensitive', FALSE)
      ->setSetting('max_length', 36)
      // Note we don't add a UUID constraint here as that is validated by the
      // ComponentTreeStructure constraint on the item list.
      // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList::getConstraints
      ->setRequired(FALSE);

    $properties['parent_item'] = DataReferenceDefinition::create(\sprintf('field_item:%s', self::PLUGIN_ID))
      ->setLabel('Parent component field item')
      ->setDescription(t('The referenced parent component instance'))
      // The parent object is computed out of the parent UUID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE);

    $properties['slot'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Parent slot machine name'))
      ->setSetting('case_sensitive', FALSE)
      ->setSetting('max_length', 255)
      ->addConstraint('Length', ['max' => 255])
      ->setRequired(FALSE);

    $properties['uuid'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Component instance UUID'))
      ->setSetting('case_sensitive', FALSE)
      ->setSetting('max_length', 36)
      // Note we don't add a UUID constraint here as that is validated by the
      // ComponentTreeStructure constraint on the item list.
      // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList::getConstraints
      ->setRequired(TRUE);

    $properties['component_id'] = DataReferenceTargetDefinition::create('string')
      // Note we don't add a ConfigExists constraint here as that is validated by
      // ComponentTreeStructure constraint on the item list.
      // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList::getConstraints
      ->setLabel(new TranslatableMarkup('Component ID'))
      ->setRequired(TRUE);

    $properties['component_version'] = DataReferenceTargetDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Component version ID'))
      // Note we don't add a ValidConfigEntityVersion or
      // ValidConfigEntityVersionConstraint constraint here as they are both
      // validated by ComponentTreeStructure constraint on the item list.
      // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList::getConstraints
      ->setRequired(TRUE);

    $properties['component'] = DataReferenceDefinition::create(ConfigEntityVersionAdapter::PLUGIN_ID)
      ->setLabel('Component')
      ->setDescription(new TranslatableMarkup('The referenced component entity, for the given version'))
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create(Component::ENTITY_TYPE_ID))
      ->addConstraint('EntityType', ['type' => Component::ENTITY_TYPE_ID]);

    $properties['inputs'] = DataDefinition::create('component_inputs')
      ->setLabel(new TranslatableMarkup('Input values for each component in the component tree'))
      ->setRequired(TRUE);

    $properties['label'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Optional label for the component instance to provide context for content authors. Not visible to end users.'))
      ->setSetting('max_length', 255)
      ->addConstraint('Length', ['max' => 255])
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // If either `uuid` or `inputs` is set, consider this not empty
    return $this->get('uuid')->getValue() === NULL || $this->get('inputs')->getValue() === NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange(mixed $property_name, $notify = TRUE): void {
    if ($property_name === 'inputs') {
      $this->values[$property_name] = $this->get($property_name)->getValue();
    }
    $pairs = [
      ['component_id', 'component'],
      ['parent_uuid', 'parent_item'],
    ];
    foreach ($pairs as $pair) {
      // Make sure that the linked properties stay in sync.
      [$property1, $property2] = $pair;
      if ($property_name === $property2) {
        $property = $this->get($property2);
        \assert($property instanceof DataReferenceInterface);
        $this->writePropertyValue($property1, $property->getTargetIdentifier());
        continue;
      }
      if ($property_name === $property1) {
        $this->writePropertyValue($property2, $this->get($property1)->getValue());
      }
    }
    if ($property_name === 'component_version') {
      // Reset the component reference property.
      $component_id = $this->get('component')->getTargetIdentifier();
      $version_id = $this->get('component_version')->getValue();
      if ($component_id !== NULL && $version_id !== NULL) {
        $this->writePropertyValue('component', [
          'target_id' => $component_id,
          'version' => $version_id,
        ]);
      }
    }
    // DX: if no version is specified, set it automatically to the active
    // version of the Component.
    // TRICKY: do this *only* when no version is specified, otherwise this would
    // unintentionally "upgrade" instances of older component versions to newer
    // ones!
    if ($this->get('component_version')->getValue() === NULL && ($property_name === 'component_id' || $property_name === 'component')) {
      // Set the version ID based on the loaded component.
      $this->writePropertyValue('component_version', $this->getComponent()?->getLoadedVersion());
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE): void {
    if (is_array($values)) {
      parent::setValue($values, FALSE);
      $pairs = [
        ['component_id', 'component'],
        ['parent_uuid', 'parent_item'],
      ];
      foreach ($pairs as $pair) {
        [$property1, $property2] = $pair;
        if (array_key_exists($property1, $values) && !isset($values[$property2])) {
          $this->onChange($property1, FALSE);
        }
        if (!array_key_exists($property1, $values) && isset($values[$property2])) {
          $this->onChange($property2, FALSE);
        }
        if (array_key_exists($property1, $values) && isset($values[$property2])) {
          // If both properties are passed, verify the passed values match.
          $reference = $this->get($property2);
          \assert($reference instanceof DataReferenceInterface);
          $identifier = $reference->getTargetIdentifier();
          if ($values[$property1] !== NULL && ($identifier != $values[$property1])) {
            throw new \InvalidArgumentException(\sprintf('The %s id and %s passed do not match.', $property2, $property2));
          }
        }
      }
      if (\array_key_exists('component_id', $values) || \array_key_exists('component', $values) && !\array_key_exists('component_version', $values)) {
        $this->onChange('component_id', FALSE);
      }
      if (\array_key_exists('component_version', $values) && $this->getComponent()?->getLoadedVersion() !== $values['component_version']) {
        $this->onChange('component_version', FALSE);
      }
    }

    // If inputs are missing, fall back to the default value of the non-computed
    // properties. This avoids a *repeated* validation error:
    // if there already is a validation error for a missing key, another
    // validation error for an invalid value is not helpful.
    // @see \Drupal\canvas\Plugin\Validation\Constraint\ValidComponentTreeItemConstraintValidator
    if (!is_array($values) || !array_key_exists('inputs', $values)) {
      $this->getProperties()['inputs']->applyDefaultValue(FALSE);
    }

    // Notify the parent if necessary.
    if ($notify && $this->parent) {
      $name = $this->getName();
      \assert(\is_string($name));
      $this->parent->onChange($name);
    }
  }

  public function getParentUuid(): ?string {
    return $this->get('parent_uuid')->getValue();
  }

  public function getParentComponentTreeItem(): ?ComponentTreeItem {
    return $this->get('parent_item')->getTarget();
  }

  public function getSlot(): ?string {
    return $this->get('slot')->getValue();
  }

  public function getComponent(): ?ComponentInterface {
    return $this->get('component')->getTarget()?->getValue();
  }

  public function getComponentVersion(): string {
    $version = $this->get('component_version')->getValue();
    if ($version === NULL) {
      throw new \InvalidArgumentException('Component version is required.');
    }
    return $version;
  }

  public function getComponentId(): string {
    $component_id = $this->get('component_id')->getValue();
    if ($component_id === NULL) {
      throw new \InvalidArgumentException('Component ID is required.');
    }
    return $component_id;
  }

  public function getUuid(): string {
    return $this->get('uuid')->getValue();
  }

  public function getInputs(): ?array {
    return $this->get('inputs')->getValues();
  }

  public function getInput(): ?string {
    return $this->get('inputs')->getValue();
  }

  public function setInput(string|array $input): self {
    return $this->set('inputs', $input);
  }

  public function getLabel(): ?string {
    return $this->get('label')->getValue();
  }

  public function setLabel(?string $label): self {
    return $this->set('label', $label);
  }

  /**
   * @todo This belongs in a normalizer */
  public function getClientSideRepresentation(): array {
    return [
      'uuid' => $this->getUuid(),
      'nodeType' => 'component',
      'type' => sprintf('%s@%s', $this->getComponentId(), $this->getComponentVersion()),
      // TRICKY: the client-side representation uses `name`, the server-side
      // representation uses `label`, due to TypedData limitations.
      // @see \Drupal\Core\TypedData\TypedData::$name
      'name' => $this->getLabel(),
      'slots' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(): void {
    $entity = $this->getRoot() === $this ? NULL : $this->getEntity();
    $violations = new ConstraintViolationList();
    $source = $this->getComponent()?->getComponentSource();
    $component_instance_uuid = $this->getUuid();
    if ($source === NULL) {
      $violations->add(new ConstraintViolation(
        \sprintf('Unable to load component with ID "%s".', $this->getComponentId()),
        NULL,
        [],
        NULL,
        "inputs." . $component_instance_uuid,
        NULL,
      ));
      return;
    }
    // Ensure that only ever valid inputs for component instances in an Canvas
    // field are saved. When a field is saved that somehow was not validated,
    // this will catch that.
    // @see \Drupal\canvas\Plugin\Validation\Constraint\ValidComponentTreeItemConstraintValidator
    $input_values = $this->getInputs();
    $component_violations = $source->validateComponentInput($input_values ?? [], $component_instance_uuid, $entity);
    if ($component_violations->count() > 0) {
      // @todo Remove the foreach and use ::addAll once
      // https://www.drupal.org/project/drupal/issues/3490588 has been resolved.
      foreach ($component_violations as $violation) {
        $violations->add($violation);
      }
    }
    if ($violations->count() > 0) {
      throw new \LogicException(
        \implode("\n", \array_map(
            static fn(ConstraintViolationInterface $violation) => \sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage()),
            \iterator_to_array($violations)
          )
        )
      );
    }

    // This *internal-only* validation does not need to happen using validation
    // constraints because it does not validate user input: it only helps ensure
    // that the logic of this field type is correct.
    if ($input_values === NULL && $source->requiresExplicitInput()) {
      throw new \LogicException(sprintf('Missing input for component instance with UUID %s', $component_instance_uuid));
    }
    $this->optimizeInputs();
    // @todo Omit defaults that are stored at the content type template level, e.g. in core.entity_view_display.node.article.default.yml
    // $template_tree = '@todo';
    // $template_inputs = '@todo';
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    // @todo Remove this method once Drupal allows validating some constraints after some other constraints (i.e. ValidComponentTreeItemConstraintValidator must run after all other fields on an entity have been validated).

    // Re-run the validation logic now that fields that are required on this
    // entity are guaranteed to exist (i.e. the entity is no longer new, because
    // it already was saved).
    assert($this->getEntity()->isNew() === FALSE);
    // Because the entity is now guaranteed to not be new, a slightly stricter
    // validation is performed — if it fails, then an exception is thrown and
    // the entity saving database transaction is rolled back, and an error
    // message is displayed.
    // This should NEVER occur, but until Drupal Canvas is stable and/or
    // https://www.drupal.org/project/drupal/issues/2820364 is unresolved, this
    // ensures Drupal Canvas developers are informed early.
    // @see \Drupal\canvas\Plugin\Validation\Constraint\ValidComponentTreeItemConstraintValidator::validate()
    $this->validate();
    return FALSE;
  }

  public function updatePropSourcesOnDependencyRemoval(string $dependency_type, string $dependency_name, ?FieldableEntityInterface $host_entity = NULL): bool {
    $prop_sources_to_update = $this->get('inputs')
      ->getPropSourcesWithDependency($dependency_type, $dependency_name, $host_entity);

    $changed = FALSE;
    $inputs = $this->getInputs();

    foreach ($prop_sources_to_update as $name => $prop_source) {
      // Remove this prop source; it depends on the removed config.
      unset($inputs[$name]);

      $component_source = $this->getComponent()?->getComponentSource();

      // If the component source requires explicit input, replace the removed
      // prop source with a static prop source. If we don't have a component
      // source for this component instance, there's nothing else we can do;
      // end users will probably see an error message, but that's not our fault.
      $default_inputs = $component_source?->getDefaultExplicitInput() ?? [];
      if ($component_source?->requiresExplicitInput() && isset($default_inputs[$name])) {
        $inputs[$name] = $default_inputs[$name];
      }
      $changed = TRUE;
    }
    $this->setInput($inputs ?? []);
    return $changed;
  }

  public function optimizeInputs(): void {
    $source = $this->getComponent()?->getComponentSource();
    if ($source === NULL) {
      // This could be running against data that has not been validated, in
      // which case there is nothing we can do without a valid component or
      // source.
      return;
    }
    // Allow component source plugins to normalize the stored data.
    try {
      $inputs = $this->getInputs();
    }
    catch (\UnexpectedValueException | MissingComponentInputsException) {
      $inputs = NULL;
    }
    if ($inputs !== NULL) {
      $inputs = $source->optimizeExplicitInput($inputs);
      $this->setInput($inputs);
    }
  }

}
