<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Defines a constraint to validate a configuration entity has a given version.
 *
 * @see \Drupal\canvas\Entity\VersionedConfigEntityInterface::getVersions()
 */
#[Constraint(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Valid config entity version', [], ['context' => 'Validation']),
)]
final class ValidConfigEntityVersionConstraint extends SymfonyConstraint {

  public const string PLUGIN_ID = 'ValidConfigEntityVersion';

  public string $message = "'@version' is not a version that exists on @entity_type config entity '@entity_id'. Available versions: '@available_versions'.";

  /**
   * Optional prefix, to be specified when this contains a config entity ID.
   *
   * Every config entity type can have multiple instances, all with unique IDs
   * but the same config prefix. When config refers to a config entity,
   * typically only the ID is stored, not the prefix.
   *
   * @see \Drupal\Core\Config\Plugin\Validation\Constraint\ConfigExistsConstraint::$prefix
   */
  public string $configPrefix = '';

  /**
   * Config object containing a sequence whose keys must be matched.
   */
  public string $configName;

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): string {
    return 'configName';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return [
      'configName',
    ];
  }

}
