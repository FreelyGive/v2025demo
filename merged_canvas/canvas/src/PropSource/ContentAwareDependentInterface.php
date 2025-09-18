<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Provides an interface for a prop source that has dependencies.
 *
 * @internal
 * @phpstan-import-type ConfigDependenciesArray from \Drupal\canvas\Entity\VersionedConfigEntityInterface
 */
interface ContentAwareDependentInterface {

  /**
   * Calculates dependencies for the prop source.
   *
   * Dependencies are used to determine configuration synchronization, or
   * determine if they are being used.
   *
   * When the optional $root is provided, content dependencies can be
   * resolved too. This is optional though, because it is also possible to
   * calculate dependencies solely for the content entity type + bundle + field
   * structure expected by a DynamicPropSource.
   *
   * For example:
   * - a `ContentTemplate` config entity on its own has dependencies on specific
   * fields being present (`core.base_field_override.*` and `field.config.*`)
   * - a content entity using a `ContentTemplate` config entity would result in
   * a host entity being passed, and would depend NOT on the fields, but on
   * the data in the host entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface|\Drupal\Core\Field\FieldItemListInterface|null $root
   *   The (optional) root structured data.
   *
   * @return array
   *   An array of dependencies grouped by type (config, content, module,
   *   theme). For example:
   *   @code
   *   [
   *     'config' => ['user.role.anonymous', 'user.role.authenticated'],
   *     'content' => ['node:article:f0a189e6-55fb-47fb-8005-5bef81c44d6d'],
   *     'module' => ['node', 'user'],
   *     'theme' => ['claro'],
   *   ];
   *   @endcode
   *
   * @see \Drupal\Component\Plugin\DependentPluginInterface
   * @see \Drupal\Core\Config\Entity\ConfigDependencyManager
   * @see \Drupal\Core\Entity\EntityInterface::getConfigDependencyName()
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $root = NULL): array;

}
