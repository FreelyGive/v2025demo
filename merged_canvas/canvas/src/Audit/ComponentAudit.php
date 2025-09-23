<?php

declare(strict_types=1);

namespace Drupal\canvas\Audit;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityDependency;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;

/**
 * @todo Improve in https://www.drupal.org/project/canvas/issues/3522953.
 */
final class ComponentAudit {

  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  public function getContentRevisionIdsUsingComponentIds(array $component_ids, array $version_ids = []): array {
    $field_map = $this->entityFieldManager->getFieldMapByFieldType(ComponentTreeItem::PLUGIN_ID);
    $dependencies = [];
    foreach ($field_map as $entity_type_id => $detail) {
      $field_names = \array_keys($detail);
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $query = $storage
        ->getQuery()
        ->sort((string) $entity_type->getKey('id'))
        ->accessCheck(FALSE);
      if ($entity_type->isRevisionable()) {
        $query->allRevisions();
      }
      $or_group = $query->orConditionGroup();
      foreach ($field_names as $field_name) {
        if ($version_ids) {
          $and_group = $query->andConditionGroup();
          $and_group->condition(\sprintf('%s.component_id', $field_name), $component_ids, 'IN');
          $and_group->condition(\sprintf('%s.component_version', $field_name), $version_ids, 'IN');
          $or_group->condition($and_group);
          continue;
        }
        $or_group->condition(\sprintf('%s.component_id', $field_name), $component_ids, 'IN');
      }
      $query->condition($or_group);
      $ids = $query->execute();
      $dependencies[$entity_type_id] = $ids;
    }
    ksort($dependencies);
    return $dependencies;
  }

  /**
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   */
  public function getContentRevisionsUsingComponent(ComponentInterface $component, array $version_ids = []): array {
    $entity_ids = $this->getContentRevisionIdsUsingComponentIds([$component->id()], $version_ids);
    $dependencies = [];
    foreach ($entity_ids as $entity_type_id => $ids) {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if ($ids !== NULL && \count($ids) > 0) {
        if ($entity_type->isRevisionable()) {
          \assert($storage instanceof RevisionableStorageInterface);
          $dependencies = \array_merge($dependencies, $storage->loadMultipleRevisions(\array_keys($ids)));
          continue;
        }
        $dependencies = \array_merge($dependencies, $storage->loadMultiple($ids));
      }
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface[] */
    return $dependencies;
  }

  /**
   * @return array<\Drupal\Core\Config\Entity\ConfigEntityInterface>
   */
  public function getConfigEntityDependenciesUsingComponent(ComponentInterface $component, string $config_entity_type_id): array {
    $config_entity_definition = $this->entityTypeManager->getDefinition($config_entity_type_id);
    assert($config_entity_definition instanceof ConfigEntityTypeInterface);
    $config_prefix = $config_entity_definition->getConfigPrefix() . '.';
    $dependents = $this->configManager->getConfigDependencyManager()->getDependentEntities('config', $component->getConfigDependencyName());
    $dependents = array_filter($dependents, fn(ConfigEntityDependency $dependency) => str_starts_with($dependency->getConfigDependencyName(), $config_prefix));
    $dependencies = array_map(fn(ConfigEntityDependency $dependency): ?EntityInterface => $this->entityTypeManager->getStorage($config_entity_type_id)->load(str_replace($config_prefix, '', $dependency->getConfigDependencyName())), $dependents);
    assert(Inspector::assertAllObjects($dependencies, ConfigEntityInterface::class));
    return $dependencies;
  }

  public function getConfigEntityUsageCount(ComponentInterface $component): int {
    // @todo Add static caching in https://www.drupal.org/i/3522953 — config cannot change mid-request
    return count($this->configManager->getConfigDependencyManager()->getDependentEntities('config', $component->getConfigDependencyName()));
  }

  public function hasUsages(ComponentInterface $component): bool {
    // @todo Field config default values
    // @todo Base field definition default values
    // @todo What if there are asymmetric content translations, or the translated
    //   config provide different defaults? Verify and test in
    //   https://www.drupal.org/i/3522198
    $entity_types = \array_keys(\array_filter($this->entityTypeManager->getDefinitions(), static fn (EntityTypeInterface $type): bool => $type instanceof ConfigEntityTypeInterface && $type->entityClassImplements(ComponentTreeEntityInterface::class)));
    \assert(\count($entity_types) > 0);
    // Check config entities first as the calculation is less expensive.
    foreach ($entity_types as $entity_type_id) {
      $usages = $this->getConfigEntityDependenciesUsingComponent($component, $entity_type_id);
      if (\count($usages) > 0) {
        return TRUE;
      }
    }
    $usages = $this->getContentRevisionsUsingComponent($component);
    return \count($usages) > 0;
  }

}
