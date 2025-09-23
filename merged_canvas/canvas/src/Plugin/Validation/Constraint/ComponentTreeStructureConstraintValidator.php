<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\Plugin\Validation\Constraint\ConfigExistsConstraint;
use Drupal\Core\Config\Schema\Sequence;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Validation\BasicRecursiveValidatorFactory;
use Drupal\Core\Validation\Plugin\Validation\Constraint\LengthConstraint;
use Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ComponentTreeStructureConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BasicRecursiveValidatorFactory $validatorFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(EntityTypeManagerInterface::class),
      $container->get(BasicRecursiveValidatorFactory::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if ($value === NULL) {
      return;
    }
    if ($value instanceof ComponentTreeItemList) {
      $value = $value->getValue();
    }
    \assert($constraint instanceof ComponentTreeStructureConstraint);
    $base_property_path = $constraint->basePropertyPath;
    if ($base_property_path === '') {
      $base_property_path = $this->context->getPropertyPath();
    }
    $object = $this->context->getObject();
    if ($object instanceof Sequence) {
      // Remove keys from config-based component trees.
      $value = \array_values($value);
    }
    if (\is_array($value) && $object instanceof TypedDataInterface && !($object instanceof Sequence)) {
      $type = $object->getDataDefinition()->getDataType();
      if ($type === 'field.value.component_tree' && ($parent = $object->getParent()) !== NULL) {
        // We can only validate a single field value item in the case of a
        // default value for a field. So we need to traverse to the parent to
        // get the complete values for validation-sake. This is because the
        // 'field_config_base' data-type defines the 'default_value' as a
        // sequence of 'field.value.[%parent.%parent.field_type]' items and
        // therefore a field-type cannot control the sequence, only the
        // individual items in the sequence. We instead validate the parent
        // value so we have access to all the default values instead of a single
        // one.
        // @see core.data_types.schema.yml
        $delta = (int) $object->getName();
        if ($delta !== 0) {
          // We're validating a field default value here, but we don't need to
          // run it for any deltas other than zero, because we're reaching up
          // to the parent value and getting the full sequence and validating
          // that in a single pass.
          return;
        }
        // Validate the parent value - which is the sequence of all default
        // values for the field.
        $value = $parent->getValue();
        // Adjust the base property path appropriately.
        $base_property_path = (string) $parent->getName();
      }
    }
    if (!is_array($value)) {
      throw new \UnexpectedValueException(sprintf('The value must be a valid array, found %s.', \gettype($value)));
    }
    // TRICKY: The existing validator and execution context cannot be reused
    // because Drupal expects everything to be TypedData, whereas here it is a
    // plain array-based data structure.
    // @todo Re-assess this in https://www.drupal.org/project/canvas/issues/3462235: if that introduces TypedData objects, then this could be simplified.
    $non_typed_data_validator = $this->validatorFactory->createValidator();

    // Constraint to validate each component instance, which is represented in
    // the value by a "uuid,component" tuple.
    $component_instance_constraint = new Sequentially(
      [
        new Collection([
          'uuid' => new Required([
            new Type('string'),
            new NotBlank(),
            new Uuid(),
          ]),
          'component_id' => new Required([
            new Type('string'),
            new NotBlank(),
            new ConfigExistsConstraint(['prefix' => 'canvas.component.']),
          ]),
          'component_version' => new Required([
            new Type('string'),
            new NotBlank(),
            new ValidConfigEntityVersionConstraint([
              'configPrefix' => 'canvas.component.',
              'configName' => '%parent.component_id',
            ]),
          ]),
          'parent_uuid' => new Optional([
            new NotBlank(allowNull: TRUE),
            new Uuid(),
          ]),
          'slot' => new Optional([
            new NotBlank(allowNull: TRUE),
            new Type('string'),
            new ValidSlotNameConstraint(),
          ]),
          'label' => new Optional([
            new NotBlank(allowNull: TRUE),
            new Type('string'),
            new LengthConstraint(max: 255),
          ]),
        ], allowExtraFields: TRUE),
        new Callback(
          callback: self::validateComponentInstance(...),
          payload: [
            'entity_type_manager' => $this->entityTypeManager,
            'root' => $this->context->getRoot(),
          ]
        ),
      ]
    );

    $violations = $non_typed_data_validator->validate($value, [
      // Use sequentially because if the data is not an array, or is so mangled
      // that we can't continue, we don't want to attempt subsequent validation.
      new Sequentially([
        new Type('array'),
        new All([$component_instance_constraint]),
        new Unique(fields: ['uuid'], message: 'Not all component instance UUIDs in this component tree are unique.'),
      ]),
    ]);

    foreach ($violations as $violation) {
      $property_path = $violation->getPropertyPath();
      $new_path = self::translatePropertyPath($base_property_path, $property_path, $this->context->getPropertyPath());
      // We make use of ::add instead of using ::buildViolation and casting
      // the previous violation message to a string because the violation may
      // make use of placeholders and if we cast the message to a string, we
      // may end up with double escaping of placeholder that make use of <em>
      // tags.
      $this->context->getViolations()->add(new ConstraintViolation(
        $violation->getMessage(),
        $violation->getMessageTemplate(),
        $violation->getParameters(),
        // Use the original root.
        $this->context->getRoot(),
        // And the translated path.
        $new_path,
        $violation->getInvalidValue(),
        $violation->getPlural(),
        $violation->getCode(),
        $violation->getConstraint(),
        $violation->getCause(),
      ));
    }
  }

  private static function translatePropertyPath(string $base_path, string $property_path, string $context_path = ''): string {
    // Ensure we retain Drupal's dot based property paths instead of Symfony's
    // [] based notation.
    $new_path = \str_replace(
      ['][', '[', ']'],
      ['.', '', ''],
      \trim($base_path . '.' . $property_path, '.'),
    );
    if ($context_path !== '' &&
      \str_starts_with($new_path, $context_path) &&
      \substr_count($new_path, $context_path) > 1) {
      // Don't duplicate the context path.
      // Because we're mixing both the typed-data and non typed-data validators
      // here, we need to extra work to make sure the property paths are
      // accurate and consistent.
      return \trim(\substr($new_path, \strlen($context_path)), '.');
    }
    return $new_path;
  }

  private static function validateComponentInstance(array $component_instance, ExecutionContextInterface $context, array $payload): void {
    $entity_type_manager = $payload['entity_type_manager'];
    \assert($entity_type_manager instanceof EntityTypeManagerInterface);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $component_storage */
    $component_storage = $entity_type_manager->getStorage(Component::ENTITY_TYPE_ID);
    \assert($component_storage->getEntityTypeId() === 'component');
    $tree = $context->getRoot();

    if (!isset($component_instance['uuid'])) {
      // The \Symfony\Component\Validator\Constraints\Collection constraint
      // will add the violations for the unset key.
      return;
    }

    $root = $payload['root'];

    if (empty($component_instance['parent_uuid'])) {
      return;
    }

    if ($component_instance['parent_uuid'] === $component_instance['uuid']) {
      $context->buildViolation('Invalid component tree item with UUID %uuid claims to be parent of itself.', [
        '%uuid' => $component_instance['uuid'],
      ])
        ->atPath('parent_uuid')
        ->addViolation();
    }

    if (empty($component_instance['slot'])) {
      $context->buildViolation('Invalid component tree item with UUID %uuid. A slot name must be present if a parent uuid is provided.', [
        '%uuid' => $component_instance['uuid'],
      ])
        ->atPath('slot')
        ->addViolation();
      return;
    }

    $parent = \array_filter($tree, static fn (array $item) => $item['uuid'] === $component_instance['parent_uuid']);

    if ($root instanceof EntityAdapter) {
      // We might have a subtree here that works with a content template.
      // Attempt to fetch the template.
      $entity = $root->getValue();
      \assert($entity instanceof EntityInterface);
      $content_template_storage = $entity_type_manager->getStorage(ContentTemplate::ENTITY_TYPE_ID);
      $template_id = implode('.', [
        $entity->getEntityTypeId(),
        $entity->bundle(),
        // Only the full view mode can expose slots.
        // @see `type: canvas.content_template.*.*.*`'s
        'full',
      ]);
      $template = $content_template_storage->load($template_id);
      if ($template instanceof ContentTemplate) {
        $template_tree = $template->getComponentTree();
        $parent = \array_merge($parent, $template_tree->getValue());
      }
    }

    if (\count($parent) === 0) {
      $context->buildViolation('Invalid component tree item with UUID %uuid references an invalid parent %parent_uuid.', [
        '%uuid' => $component_instance['uuid'],
        '%parent_uuid' => $component_instance['parent_uuid'],
      ])
        ->atPath('parent_uuid')
        ->addViolation();
      return;
    }

    $parent_instance = \reset($parent);
    $parent_config_entity = $component_storage->load($parent_instance['component_id']);
    if ($parent_config_entity === NULL) {
      $context->buildViolation('Invalid component tree item with UUID %uuid references an invalid parent %parent_uuid component %component.', [
        '%uuid' => $component_instance['uuid'],
        '%parent_uuid' => $component_instance['parent_uuid'],
        '%component' => $parent_instance['component_id'],
      ])
        ->atPath('parent_uuid')
        ->addViolation();
      return;
    }

    assert($parent_config_entity instanceof Component);
    $component_source = $parent_config_entity->getComponentSource();
    $slots = $component_source instanceof ComponentSourceWithSlotsInterface ? $component_source->getSlotDefinitions() : [];
    if (\count($slots) === 0) {
      $context->buildViolation('Invalid component subtree. A component subtree must only exist for components with >=1 slot, but the component %component has no slots, yet a subtree exists for the instance with UUID %uuid.', [
        '%component' => $parent_instance['component_id'],
        '%uuid' => $parent_instance['uuid'],
      ])
        ->atPath('parent_uuid')
        ->addViolation();
      return;
    }
    if (!\array_key_exists($component_instance['slot'], $slots)) {
      $context->buildViolation('Invalid component subtree. This component subtree contains an invalid slot name for component %component: %invalid_slot_name. Valid slot names are: %valid_slot_names.', [
        '%component' => $parent_instance['component_id'],
        '%invalid_slot_name' => $component_instance['slot'],
        '%valid_slot_names' => implode(', ', \array_keys($slots)),
      ])
        ->atPath('slot')
        ->addViolation();
    }
  }

}
