<?php

declare(strict_types=1);

namespace Drupal\canvas\EntityHandlers;

use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\StagedConfigUpdate;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class StagedConfigUpdateStorage extends ConfigEntityStorage {

  private AutoSaveManager $autoSaveManager;

  private ConfigActionManager $configActionManager;

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->autoSaveManager = $container->get(AutoSaveManager::class);
    $instance->configActionManager = $container->get('plugin.manager.config_action');
    return $instance;
  }

  public function resetCache(?array $ids = NULL): void {
  }

  /**
   * {@inheritdoc}
   *
   * @param string[] $ids
   *
   * @return array<string, \Drupal\canvas\Entity\StagedConfigUpdate|null>
   */
  public function loadMultiple(?array $ids = NULL): array {
    if ($ids === NULL) {
      return [];
    }
    $return = [];
    foreach ($ids as $id) {
      $return[$id] = $this->load($id);
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\canvas\Entity\StagedConfigUpdate|null
   */
  public function load($id) {
    assert(is_string($id));
    $stub = StagedConfigUpdate::createFromClientSide([
      'id' => $id,
      'label' => '',
      'target' => '',
      'actions' => [],
    ]);
    $auto_save_entity = $this->autoSaveManager->getAutoSaveEntity($stub);
    if ($auto_save_entity->entity === NULL) {
      return NULL;
    }
    assert($auto_save_entity->entity instanceof StagedConfigUpdate);
    return $auto_save_entity->entity->enforceIsNew(FALSE);
  }

  public function loadUnchanged($id) {
    return $this->load($id);
  }

  public function loadByProperties(array $values = []): array {
    throw new \LogicException('Cannot query staged config updates to load by properties.');
  }

  public function delete(array $entities): void {
    foreach ($entities as $entity) {
      $this->autoSaveManager->delete($entity);
    }
  }

  public function save(EntityInterface $entity): int {
    assert($entity instanceof StagedConfigUpdate);
    $entity->enforceIsNew(FALSE);
    $entity->setOriginalId($entity->id());
    $return = SAVED_NEW;

    if ($entity->status() === TRUE) {
      foreach ($entity->getActions() as $action) {
        $this->configActionManager->applyAction($action['name'], $entity->getTarget(), $action['input']);
      }

      return SAVED_UPDATED;
    }

    $existing = $this->autoSaveManager->getAutoSaveEntity($entity);
    if ($existing->entity instanceof StagedConfigUpdate) {
      $entity->updateFromClientSide($entity->normalizeForClientSide()->values);
      $return = SAVED_UPDATED;
    }

    $this->autoSaveManager->saveEntity($entity);
    return $return;
  }

  public function restore(EntityInterface $entity): void {
  }

  public function hasData(): bool {
    return FALSE;
  }

  public function getQuery($conjunction = 'AND') {
    throw new \LogicException('Cannot query staged config updates.');
  }

  public function getAggregateQuery($conjunction = 'AND') {
    throw new \LogicException('Cannot query staged config updates.');
  }

}
