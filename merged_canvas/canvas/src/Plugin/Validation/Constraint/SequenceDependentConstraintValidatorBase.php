<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Plugin\Validation\Constraint\ConfigExistsConstraint;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\Schema\Sequence;
use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a SequenceDependentConstraintBase-subclassing constraint.
 */
abstract class SequenceDependentConstraintValidatorBase extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a SequenceDependentConstraintValidatorBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager service.
   */
  final public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TypedConfigManagerInterface $typedConfigManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ConfigFactoryInterface::class),
      $container->get(TypedConfigManagerInterface::class),
    );
  }

  protected function getTargetConfigObject(SequenceDependentConstraintBase $constraint): Mapping {
    assert(!is_null($constraint->configName));
    assert($this->context->getObject() instanceof TypedDataInterface);
    $resolved_config_name = TypeResolver::resolveExpression(
      $constraint->configName,
      $this->context->getObject(),
    );
    // This implies the existence of that other config, so reuse the message
    // from the ConfigExistsConstraint when that is not the case.
    if (!in_array($constraint->configPrefix . $resolved_config_name, $this->configFactory->listAll($constraint->configPrefix), TRUE)) {
      $config_exists_constraint = new ConfigExistsConstraint();
      $this->context->addViolation($config_exists_constraint->message, ['@name' => $constraint->configPrefix . $resolved_config_name]);
    }
    $config_object = $this->typedConfigManager->get($constraint->configPrefix . $resolved_config_name);
    assert($config_object instanceof Mapping);
    return $config_object;
  }

  protected function getSequenceKeys(SequenceDependentConstraintBase $constraint): array {
    $target_config_object = match ($constraint->configName) {
      NULL => $this->context->getRoot(),
      default => $this->getTargetConfigObject($constraint),
    };

    \assert($constraint->propertyPathToSequence !== NULL);
    $target_sequence = $target_config_object->get($constraint->propertyPathToSequence);
    // Verify the target property is indeed a sequence; if not, that's a logical
    // error in the config schema, not in concrete config.
    if (!$target_sequence instanceof Sequence) {
      throw new \LogicException(sprintf(
        "The `%s` config object's `%s` property path was expected to point to a `%s` instance, but got a `%s` instead.",
        $target_config_object->getName(),
        $constraint->propertyPathToSequence,
        Sequence::class,
        $target_sequence::class,
      ));
    }
    return array_keys($target_sequence->getElements());
  }

}
