<?php

namespace Drupal\canvas\Access;

use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;

/**
 * Checks access to the Canvas UI: requires >=1 component tree to be editable.
 *
 * Ignores per-entity field access control; relies on 'edit' access to an Canvas
 * field.
 *
 * @see \Drupal\canvas\Access\ComponentTreeEditAccessCheck
 *
 * @internal
 */
class CanvasUiAccessCheck implements AccessInterface {

  public function __construct(
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function access(AccountInterface $account): AccessResult {
    $access = AccessResult::neutral('Requires >=1 content entity type with an Canvas field that can be created or edited.');

    // Early access return if the account has permissions for content templates
    // or code components.
    // The permission flags on Canvas controller will show the proper
    // functionalities client-side, and there are proper access checks for each
    // operation the user attempts.
    // @see \Drupal\canvas\Controller\CanvasController
    $content_templates_access = AccessResult::allowedIfHasPermission($account, ContentTemplate::ADMIN_PERMISSION);
    if ($content_templates_access->isAllowed()) {
      return $content_templates_access;
    }
    $code_components_access = AccessResult::allowedIfHasPermission($account, JavaScriptComponent::ADMIN_PERMISSION);
    if ($code_components_access->isAllowed()) {
      return $code_components_access;
    }

    $field_map = $this->entityFieldManager->getFieldMapByFieldType(ComponentTreeItem::PLUGIN_ID);
    foreach ($field_map as $entity_type_id => $detail) {
      $access_control_handler = $this->entityTypeManager->getAccessControlHandler($entity_type_id);

      $field_names = \array_keys($detail);
      // This assumes one component tree field per bundle/entity.
      // If this assumption is willing to change, will need to be updated in
      // https://www.drupal.org/i/3526189.
      foreach ($field_names as $field_name) {
        $bundles = $detail[$field_name]['bundles'];
        foreach ($bundles as $bundle) {
          $entity_create_access = $access_control_handler->createAccess($bundle, $account, return_as_object: TRUE);

          // Create a dummy entity; needed for entity `update` access checking.
          $dummy = $this->entityTypeManager->getStorage($entity_type_id)->create([
            $this->entityTypeManager->getDefinition($entity_type_id)->getKey('bundle') => $bundle,
          ]);
          assert($dummy instanceof FieldableEntityInterface);

          $entity_update_access = $dummy->access('update', $account, TRUE);
          $canvas_field = $dummy->get($field_name);
          assert($canvas_field instanceof ComponentTreeItemList);
          $canvas_field_edit_access = $canvas_field->access('edit', $account, TRUE);

          // Grant access if the current user can:
          // 1. create such a content entity (and set the Canvas field)
          $access = $access->orIf($entity_create_access->andIf($canvas_field_edit_access));
          // 2. edit such a content entity (and update the Canvas field)
          $access = $access->orIf($entity_update_access->andIf($canvas_field_edit_access));
          // If we have access to edit a single Canvas-field in a single bundle,
          // or code components, we must grant access to Canvas and can avoid extra
          // checks.
          if ($access->isAllowed()) {
            return $access;
          }
        }
      }
    }
    return $access;
  }

}
