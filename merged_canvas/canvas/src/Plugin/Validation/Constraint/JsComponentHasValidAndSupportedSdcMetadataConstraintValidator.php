<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Component\Exception\InvalidComponentException;
use Drupal\Core\Theme\Component\ComponentValidator;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @internal
 */
final class JsComponentHasValidAndSupportedSdcMetadataConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    private readonly ComponentValidator $componentValidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ComponentValidator::class),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Validator\Exception\UnexpectedTypeException
   *   Thrown when the given constraint is not supported by this validator.
   * @throws \Symfony\Component\Validator\Exception\UnexpectedValueException
   *   Thrown when the given value is not supported by this validator.
   */
  public function validate(mixed $data, Constraint $constraint): void {
    if (!$constraint instanceof JsComponentHasValidAndSupportedSdcMetadataConstraint) {
      throw new UnexpectedTypeException($constraint, JsComponentHasValidAndSupportedSdcMetadataConstraint::class);
    }

    if (!$data instanceof JavaScriptComponent) {
      throw new UnexpectedValueException($data, JavaScriptComponent::class);
    }

    $equivalent_sdc_definition = $data->toSdcDefinition();
    try {
      $result = $this->componentValidator->validateDefinition($equivalent_sdc_definition, TRUE);
      assert($result === TRUE);
    }
    catch (InvalidComponentException $e) {
      $this->context->addViolation($e->getMessage());
      return;
    }

    // The JavaScriptComponent has *valid* SDC metadata, but does it also meet
    // Canvas's additional requirements? Only then is it supported by Canvas.
    // @see \Drupal\canvas\ComponentMetadataRequirementsChecker::check()
    try {
      JsComponent::createConfigEntity($data);
    }
    catch (ComponentDoesNotMeetRequirementsException $e) {
      foreach ($e->getMessages() as $message) {
        $this->context->addViolation($message);
      }
    }
  }

}
