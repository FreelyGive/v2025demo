<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\canvas\Entity\VersionedConfigEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates the ValidConfigEntityVersionConstraint constraint.
 */
final class ValidConfigEntityVersionConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  final public function __construct(
    private readonly ConfigManagerInterface $configManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ConfigManagerInterface::class),
    );
  }

  public function validate(mixed $version, Constraint $constraint): void {
    if ($version === NULL) {
      return;
    }
    if (!$constraint instanceof ValidConfigEntityVersionConstraint) {
      throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\ValidConfigEntityVersionConstraint');
    }
    if (!is_string($version)) {
      throw new UnexpectedValueException($version, 'string');
    }

    $resolved_config_name = $constraint->configName;
    // Determine the name of the config entity to load.
    if (str_contains($constraint->configName, '%')) {
      if ($constraint->configPrefix === '') {
        throw new \LogicException('When specifying the configName constraint option using a config schema dynamic type expression, the configPrefix constraint option must also be specified.');
      }
      $object = $this->context->getObject();
      if ($object instanceof TypedDataInterface) {
        // We're dealing with this constraint with Drupal's typed-data based
        // validator.
        $resolved_config_name = $constraint->configPrefix . TypeResolver::resolveExpression(
          $constraint->configName,
          $object,
        );
      }
      else {
        $root = $this->context->getRoot();
        \assert(\is_array($root));
        // We're dealing with this constraint in a basic validation context
        // where no typed-data is available. We only support the %parent
        // modifier here.
        if (\str_contains($constraint->configName, '%key') || \str_contains($constraint->configPrefix, '%type')) {
          throw new \LogicException('When using the ValidConfigEntityVersionConstraintValidator in a basic validation context, the configName constraint option can only use %parent in a dynamic type expression.');
        }
        // Convert the property path into a format NestedArray supports, for
        // example a property path of [0][component_version] corresponds to
        // parents of '0' and 'component_version'.
        $path_parts = \explode('][', \trim($this->context->getPropertyPath(), '[]'));
        $expression = \explode('.', $constraint->configName);
        foreach ($expression as $part) {
          if ($part === '%parent') {
            \array_pop($path_parts);
            continue;
          }
          \array_push($path_parts, $part);
        }
        $resolved_config_name = $constraint->configPrefix . NestedArray::getValue($root, $path_parts);
      }
    }

    // Load it, and ensure it's indeed a versioned config entity.
    $versioned_config_entity = $this->configManager->loadConfigEntityByName($resolved_config_name);
    if ($versioned_config_entity === NULL) {
      // Defer this to ConfigExists validation constraint.
      return;
    }
    if (!$versioned_config_entity instanceof VersionedConfigEntityInterface) {
      throw new InvalidOptionsException('This does point to a config entity, but not a versioned config entity.', ['configName']);
    }

    $available_versions = \array_unique($versioned_config_entity->getVersions());
    if (!in_array($version, $available_versions, TRUE)) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('@version', $version)
        ->setParameter('@entity_type', (string) $versioned_config_entity->getEntityType()->getSingularLabel())
        ->setParameter('@entity_id', (string) $versioned_config_entity->id())
        ->setParameter('@available_versions', implode("', '", $available_versions))
        ->setInvalidValue($version)
        ->addViolation();
    }
  }

}
