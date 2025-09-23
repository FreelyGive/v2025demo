<?php

declare(strict_types=1);

namespace Drupal\canvas\EntityHandlers;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\ComponentIncompatibilityReasonRepository;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines storage handler for JavascriptComponents.
 */
final class JavascriptComponentStorage extends CanvasAssetStorage {

  private ConfigInstallerInterface $configInstaller;
  private EntityTypeManagerInterface $entityTypeManager;
  private ComponentIncompatibilityReasonRepository $componentIncompatibilityReasonRepository;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    $instance = parent::createInstance($container, $entity_type);
    $instance->configInstaller = $container->get(ConfigInstallerInterface::class);
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    $instance->componentIncompatibilityReasonRepository = $container->get(ComponentIncompatibilityReasonRepository::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update): void {
    parent::doPostSave($entity, $update);
    \assert($entity instanceof JavascriptComponent);
    // @todo Fix upstream core bug in Recipes: it inconsistently claims to be
    // syncing when installing modules, but not when installing configuration.
    // Even though it is listed under `import`, and that should hence match the
    // behavior of the /admin/config/development/configuration/single/import UI.
    if (in_array('installRecipeConfig', array_column(debug_backtrace(), 'function'), TRUE)) {
      // Assert the bug is still present. This will start failing as soon as the
      // upstream bug is fixed.
      assert(!$this->configInstaller->isSyncing());
      return;
    }
    if ($this->configInstaller->isSyncing()) {
      return;
    }
    $this->createOrUpdateComponentEntity($entity);
  }

  /**
   * Gets the corresponding component entity for the given JS component.
   *
   * @param \Drupal\canvas\Entity\JavaScriptComponent $entity
   *   Javascript component being saved.
   */
  protected function createOrUpdateComponentEntity(JavaScriptComponent $entity): void {
    $storage = $this->entityTypeManager->getStorage(Component::ENTITY_TYPE_ID);
    $component_id = JsComponent::componentIdFromJavascriptComponentId((string) $entity->id());
    $component = $storage->load($component_id);
    if ($component instanceof Component) {
      try {
        $component = JsComponent::updateConfigEntity($entity, $component);
        $this->componentIncompatibilityReasonRepository->removeReason(JsComponent::SOURCE_PLUGIN_ID, $component_id);
      }
      catch (ComponentDoesNotMeetRequirementsException $e) {
        $this->handleComponentDoesNotMeetRequirementsException($component_id, $e);
        $component->disable();
      }
      $component->save();
      return;
    }

    // Before exposing a JavaScriptComponent as an Canvas Component for the first
    // time it must be flagged as being added to Canvas's component library.
    if ($entity->status() === FALSE) {
      return;
    }
    try {
      $component = JsComponent::createConfigEntity($entity);
      $component->save();
    }
    catch (ComponentDoesNotMeetRequirementsException $e) {
      $this->handleComponentDoesNotMeetRequirementsException($component_id, $e);
    }
  }

  private function handleComponentDoesNotMeetRequirementsException(string $component_id, ComponentDoesNotMeetRequirementsException $e): void {
    $this->componentIncompatibilityReasonRepository->storeReasons(JsComponent::SOURCE_PLUGIN_ID, $component_id, $e->getMessages());
  }

}
