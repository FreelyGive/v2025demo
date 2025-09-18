<?php

declare(strict_types=1);

namespace Drupal\canvas\EntityHandlers;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Access\CanvasUiAccessCheck;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContentCreatorVisibleCanvasConfigEntityAccessControlHandler extends CanvasConfigEntityAccessControlHandler {

  protected $viewLabelOperation = TRUE;

  final public function __construct(
    EntityTypeInterface $entity_type,
    ConfigManagerInterface $configManager,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly CanvasUiAccessCheck $canvasUiAccessCheck,
  ) {
    parent::__construct($entity_type, $configManager, $entityTypeManager);
  }

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get(ConfigManagerInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(CanvasUiAccessCheck::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof ConfigEntityInterface);
    return match($operation) {
      // We allow viewing the label of these entities if the user has access to Canvas to allow them
      // to be published.
      'view label' => $this->canvasUiAccessCheck->access($account)->addCacheableDependency($entity),
      // We allow viewing these entities if the user has access to Canvas, and their
      // status is enabled.
      'view' => $this->canvasUiAccessCheck->access($account)->andIf(AccessResult::allowedIf($entity->status())
        ->addCacheableDependency($entity)),
      default => parent::checkAccess($entity, $operation, $account),
    };
  }

}
