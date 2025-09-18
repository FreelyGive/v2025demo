<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase;
use Drupal\canvas\PropExpressions\Component\ComponentPropExpression;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Enabled configurable plugin settings validator.
 *
 * @internal
 * @todo Extract a base class out of ThemeRegionKeysConstraintValidator and make both this and that one use it. Better yet: move the unique logic into the constraint class, similar to `\Drupal\Core\Validation\Plugin\Validation\Constraint\ValidKeysConstraint::getAllowedKeys()`.
 */
final class SdcPropKeysConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected readonly ComponentPluginManager $componentPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ComponentPluginManager::class)
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Validator\Exception\UnexpectedTypeException
   *   Thrown when the given constraint is not supported by this validator.
   */
  public function validate(mixed $mapping, Constraint $constraint): void {
    if (!$constraint instanceof SdcPropKeysConstraint) {
      throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\SdcPropKeysConstraint');
    }

    if (!is_array($mapping)) {
      throw new UnexpectedValueException($mapping, 'mapping');
    }

    // Resolve any dynamic tokens, like %parent, in the SDC plugin ID.
    // @phpstan-ignore argument.type
    $sdc_plugin_id = TypeResolver::resolveDynamicTypeName("[$constraint->sdcPluginId]", $this->context->getObject());
    assert(!str_contains($sdc_plugin_id, '%'));
    try {
      $sdc = $this->componentPluginManager->find($sdc_plugin_id);
    }
    catch (ComponentNotFoundException) {
      // @todo Ideally, we'd only validate this if and only if the `component` is valid. That requires conditional/sequential execution of validation constraints, which Drupal does not currently support.
      // @see https://www.drupal.org/project/drupal/issues/2820364
      return;
    }

    // Fetch the props defined in the SDC's metadata.
    $prop_shapes = GeneratedFieldExplicitInputUxComponentSourceBase::getComponentInputsForMetadata($sdc->getPluginId(), $sdc->metadata);
    $expected_keys = array_map(
      fn (string $component_prop_expression) => ComponentPropExpression::fromString($component_prop_expression)->propName,
      array_keys($prop_shapes)
    );

    foreach ($expected_keys as $expected_key) {
      if (!array_key_exists($expected_key, $mapping)) {
        $this->context->buildViolation($constraint->message)
          // `title` is guaranteed to exist.
          // @see \Drupal\canvas\Plugin\ComponentPluginManager::componentMeetsRequirements()
          // @phpstan-ignore-next-line
          ->setParameter('%prop_title', $sdc->metadata->schema['properties'][$expected_key]['title'])
          ->setParameter('%prop_machine_name', $expected_key)
          ->addViolation();
      }
    }

    $extraneous_keys = array_diff(array_keys($mapping), $expected_keys);
    foreach ($extraneous_keys as $extra_key) {
      $this->context->buildViolation($constraint->extraneousMessage)
        ->setParameter('%prop_machine_name', $extra_key)
        ->addViolation();
    }
  }

}
