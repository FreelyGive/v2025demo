<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\canvas\MissingComponentInputsException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Validation\ConstraintPropertyPathTranslatorTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ValidComponentTreeItemConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use ConfigComponentTreeTrait;
  use ConstraintPropertyPathTranslatorTrait;

  public function __construct(
    protected readonly TypedDataManagerInterface $typedDataManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(TypedDataManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if ($value === NULL) {
      return;
    }

    if (!$value instanceof ComponentTreeItem && !is_array($value)) {
      throw new \UnexpectedValueException(sprintf('The value must be a ComponentTreeItem object or an array, found %s.', gettype($value)));
    }

    // Validate the raw structure:
    // - if this is a `canvas.component_tree`, that is the received value
    // - if this is a `field_item:component_tree`, that is the array
    //   representation of the field item object
    if (!$this->validateRawStructure(is_array($value) ? $value : $value->toArray())) {
      // ::validateRawStructure()'s validation errors should be fixed first.
      return;
    }

    // Validate in-depth. This is simpler if the ComponentTreeItem-provided
    // infrastructure is available, so conjure one from $value if not already.
    if (!$value instanceof ComponentTreeItem) {
      assert(array_key_exists('uuid', $value));
      assert(array_key_exists('component_id', $value));
      assert(array_key_exists('inputs', $value));
      $component_tree_type = 'config';
      $value = $this->conjureFieldItemObject($value);
    }
    else {
      $component_tree_type = 'content';
    }

    $host_entity = NULL;
    if ($component_tree_type === 'content' && $value->getParent()?->getParent() !== NULL) {
      $host_entity = $value->getEntity();
    }

    // Validate the prop source resolves into a value that is considered
    // valid by the source plugin.
    $component_source = $value->getComponent()?->getComponentSource();
    if ($component_source === NULL) {
      // TRICKY: ignore missing Component config entities; that's the
      // responsibility of another validator.
      // @see \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator::validateComponentInstance()
      // @todo Refactor this away after https://www.drupal.org/project/drupal/issues/2820364 is fixed.
      return;
    }

    // Get the stored explicit input. Only add a violation error if the
    // Component in its current definition requires explicit input. (Silently
    // ignore stored inputs that are no longer required per Postel's law.)
    // @see https://en.wikipedia.org/wiki/Robustness_principle
    try {
      $stored_explicit_input = $value->get('inputs')->getValues();
      // We don't allow uncollapsed inputs.
      $before_optimize = \hash('xxh64', \json_encode($stored_explicit_input, \JSON_THROW_ON_ERROR));
      $value->optimizeInputs();
      $after_optimize = \hash('xxh64', \json_encode($value->getInputs(), \JSON_THROW_ON_ERROR));
      if ($after_optimize !== $before_optimize) {
        $this->context->buildViolation('When using the default static prop source for a component input, you must use the collapsed input syntax.')
          ->atPath(sprintf('inputs.%s', $value->getUuid()))
          ->addViolation();
      }
    }
    catch (MissingComponentInputsException $e) {
      if ($component_source->requiresExplicitInput()) {
        $this->context->buildViolation('The required properties are missing.')
          ->atPath(sprintf('inputs.%s', $e->componentInstanceUuid))
          ->addViolation();
        return;
      }
      else {
        // Fall back to empty input.
        $stored_explicit_input = [];
      }
    }

    assert(is_array($stored_explicit_input));
    $component_violations = $this->translateConstraintPropertyPathsAndRoot(
      ['' => $this->context->getPropertyPath() . '.'],
      $component_source->validateComponentInput(
        inputValues: $stored_explicit_input,
        component_instance_uuid: $value->getUuid(),
        entity: $host_entity,
      ),
      // We need to ensure the validation root context is transferred over.
      $this->context->getRoot()
    );
    if ($component_violations->count() > 0) {
      // @todo Remove the foreach and use ::addAll once
      // https://www.drupal.org/project/drupal/issues/3490588 has been resolved.
      foreach ($component_violations as $violation) {
        $this->context->getViolations()->add($violation);
      }
    }
  }

  /**
   * Validates that the two required key-value pairs are present.
   *
   * @param array{tree?: string, inputs?: string} $raw_component_tree_values
   *
   * @return bool
   *   TRUE when valid, FALSE when not. Indicates whether to validate further.
   */
  private function validateRawStructure(array $raw_component_tree_values): bool {
    $is_valid = TRUE;
    if (!array_key_exists('uuid', $raw_component_tree_values)) {
      $this->context->addViolation('The array must contain a "uuid" key.');
      $is_valid = FALSE;
    }
    if (!array_key_exists('component_id', $raw_component_tree_values)) {
      $this->context->addViolation('The array must contain a "component_id" key.');
      $is_valid = FALSE;
    }
    if (!array_key_exists('inputs', $raw_component_tree_values)) {
      $this->context->addViolation('The array must contain an "inputs" key.');
      $is_valid = FALSE;
    }
    return $is_valid;
  }

}
