<?php

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;

/**
 * Validates values for SDC required properties.
 */
final class NotNullValueForEveryRequiredSdcPropConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected readonly ComponentPluginManager $componentPluginManager,
  ) {}

  /**
   * Creates an instance of the constraint validator.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new instance of the constraint validator.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ComponentPluginManager::class)
    );
  }

  /**
   * Validates that all required SDC properties have non-null values.
   *
   * @param mixed $mapping
   *   The mapping array to validate.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint to validate against.
   */
  public function validate(mixed $mapping, Constraint $constraint): void {
    if (!$constraint instanceof NotNullValueForEveryRequiredSdcPropConstraint) {
      throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\NotNullValueForEveryRequiredSdcPropConstraint');
    }

    if (!is_array($mapping)) {
      throw new UnexpectedValueException($mapping, 'mapping');
    }

    // Resolve any dynamic tokens, like %parent, in the SDC plugin ID.
    // @phpstan-ignore argument.type
    $sdc_plugin_id = TypeResolver::resolveDynamicTypeName("[$constraint->sdcPluginId]", $this->context->getObject());
    try {
      $sdc = $this->componentPluginManager->find($sdc_plugin_id);
    }
    catch (ComponentNotFoundException) {
      // @todo Ideally, we'd only validate this if and only if the `component` is valid. That requires conditional/sequential execution of validation constraints, which Drupal does not currently support.
      // @see https://www.drupal.org/project/drupal/issues/2820364
      return;
    }

    $component_schema = $sdc->metadata->schema ?? [];
    $required_props = $component_schema['required'] ?? [];

    foreach ($required_props as $required_key) {
      if (!array_key_exists($required_key, $mapping) || $mapping[$required_key]['default_value'] === NULL) {
        $this->context->buildViolation($constraint->message)
          // `title` is guaranteed to exist.
          // @see \Drupal\canvas\Plugin\ComponentPluginManager::componentMeetsRequirements()
          ->setParameter('%prop_title', $component_schema['properties'][$required_key]['title'])
          ->setParameter('%prop_machine_name', $required_key)
          ->addViolation();
      }
    }
  }

}
