<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @internal
 */
final class ThemeRegionExistsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    private readonly ThemeExtensionList $themeExtensionList,
    private readonly ThemeInitializationInterface $themeInitialization,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ThemeExtensionList::class),
      $container->get(ThemeInitializationInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Validator\Exception\UnexpectedTypeException
   *   Thrown when the given constraint is not supported by this validator.
   */
  public function validate(mixed $region, Constraint $constraint): void {
    if (!$constraint instanceof ThemeRegionExistsConstraint) {
      throw new UnexpectedTypeException($constraint, ThemeRegionExistsConstraint::class);
    }

    if ($region === NULL) {
      return;
    }
    elseif (!is_string($region)) {
      throw new UnexpectedValueException($region, 'string');
    }

    // Resolve any dynamic tokens, like %parent, in the specified theme.
    // @phpstan-ignore argument.type
    $theme_name = TypeResolver::resolveDynamicTypeName("[$constraint->theme]", $this->context->getObject());
    try {
      $theme = $this->themeExtensionList->get($theme_name);
    }
    catch (UnknownExtensionException) {
      // @todo Ideally, we'd only validate this if and only if the `theme` is valid. That requires conditional/sequential execution of validation constraints, which Drupal does not currently support.
      // @see https://www.drupal.org/project/drupal/issues/2820364
      return;
    }
    $active_theme = $this->themeInitialization->getActiveTheme($theme);
    $valid_regions = $active_theme->getRegions();

    if (!in_array($region, $valid_regions, TRUE)) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('@region', $region)
        ->setParameter('@theme', $theme_name)
        ->setInvalidValue($region)
        ->addViolation();
    }
  }

}
