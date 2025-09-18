<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\node\Form\NodeRevisionRevertForm;

/**
 * @file
 * Hook implementations for Canvas's auto-save functionality.
 *
 * @see \Drupal\canvas\AutoSave\AutoSaveManager
 */
class AutoSaveHooks {

  public function __construct(
    private readonly AutoSaveManager $autoSaveManager,
    private readonly RouteMatchInterface $currentRouteMatch,
  ) {
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->autoSaveManager->delete($entity);
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (str_ends_with($form_id, '_revision_revert') || str_ends_with($form_id, '_revision_revert_confirm')) {
      $entity = NULL;
      // NodeRevisionRevertForm doesn't implement EntityFormInterface.
      if ($form_state->getFormObject() instanceof NodeRevisionRevertForm) {
        $entity = $this->currentRouteMatch->getParameter('node_revision');
      }
      if ($form_state->getFormObject() instanceof EntityFormInterface) {
        $entity = $form_state->getFormObject()->getEntity();
      }
      assert($entity instanceof RevisionableInterface);
      if (!$this->autoSaveManager->getAutoSaveEntity($entity)->isEmpty()) {
        if (!empty($form['actions']['submit']['#submit'])) {
          $form['actions']['submit']['#submit'][] = [self::class, 'revisionRevertSubmit'];
        }
        else {
          $form['#submit'][] = [self::class, 'revisionRevertSubmit'];
        }

        $form['canvas_auto_save_warning'] = [
          '#theme' => 'status_messages',
          '#message_list' => [
            'warning' => [
              new TranslatableMarkup('This page has unpublished changed in Drupal Canvas. Reverting to this revision will delete the auto-saved changes.'),
            ],
          ],
          '#status_headings' => [
            'warning' => new TranslatableMarkup('Warning'),
          ],
          '#weight' => -10,
        ];
      }
    }
  }

  /**
   * Submit handler for the revision revert form.
   *
   * Deletes the auto-saved version of the entity when reverting a revision.
   */
  public static function revisionRevertSubmit(array &$form, FormStateInterface $form_state): void {
    $entity = NULL;
    if ($form_state->getFormObject() instanceof NodeRevisionRevertForm) {
      $entity = \Drupal::routeMatch()->getParameter('node_revision');
    }
    if ($form_state->getFormObject() instanceof EntityFormInterface) {
      $entity = $form_state->getFormObject()->getEntity();
    }
    if ($entity instanceof EntityInterface) {
      // Delete the auto-saved version of the entity.
      \Drupal::service(AutoSaveManager::class)->delete($entity);
    }
  }

}
