<?php

declare(strict_types=1);

namespace Drupal\canvas\EntityHandlers;

use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\Audit\RevisionAuditEnum;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent;
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

final class VisibleWhenDisabledCanvasConfigEntityAccessControlHandler extends CanvasConfigEntityAccessControlHandler {

  public function __construct(
    EntityTypeInterface $entity_type,
    ConfigManagerInterface $configManager,
    EntityTypeManagerInterface $entityTypeManager,
    private readonly CanvasUiAccessCheck $canvasUiAccessCheck,
    private readonly ComponentAudit $componentAudit,
  ) {
    parent::__construct($entity_type, $configManager, $entityTypeManager);
  }

  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get(ConfigManagerInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(CanvasUiAccessCheck::class),
      $container->get(ComponentAudit::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof ConfigEntityInterface);

    // We always allow viewing these entities if the user has access to Canvas,
    // even if disabled.
    if ($operation === 'view') {
      return $this->canvasUiAccessCheck->access($account);
    }

    // For all other operations, use the parent implementation.
    $parent_result = parent::checkAccess($entity, $operation, $account);

    if ($operation === 'delete'
        && $entity instanceof JavaScriptComponent
        && $component = Component::load(JsComponent::componentIdFromJavascriptComponentId($entity->id()))
    ) {
      assert($component instanceof Component);
      // TRICKY: inspect usage last for 2 reasons:
      // 1. This avoids overwriting the "config dependencies" reason to not
      //    allow access set by the parent implementation.
      // 2. This avoids calling the more expensive ComponentAudit service when
      //    there is no need.
      if (!$parent_result->isAllowed()) {
        return $parent_result;
      }
      // *First* check usages in auto-save, because that tends to require far
      // less I/O.
      return $parent_result->orIf(AccessResult::forbiddenIf(
        $this->componentAudit->hasUsages($component, RevisionAuditEnum::AutoSave),
        'This code component is in use in a Canvas auto-save and cannot be deleted.'
      ))->orIf(AccessResult::forbiddenIf(
        $this->componentAudit->hasUsages($component, RevisionAuditEnum::Default),
        'This code component is in use in a default revision and cannot be deleted.'
      ));
    }

    return $parent_result;
  }

}
