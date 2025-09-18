<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class EntityFormController extends ControllerBase {

  use EntityFormTrait;

  public function __construct(
    private readonly AutoSaveManager $autoSaveManager,
    private readonly RequestStack $requestStack,
    protected ThemeHandlerInterface $themeHandler,
  ) {
  }

  public function form(string $entity_type, FieldableEntityInterface $entity, string $entity_form_mode): array {
    // @phpstan-ignore-next-line property.notFound
    if (!$this->themeHandler->themeExists('canvas_stark') || !$this->themeHandler->listInfo()['canvas_stark']->status) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('The canvas_stark theme must be enabled for this form to work.'),
      ];
    }

    // The 'default' value sent to `\Drupal\Core\Entity\EntityTypeManagerInterface::getFormObject`
    // is for 'operation' not form mode.
    $form = $this->entityTypeManager()->getFormObject($entity_type, 'default');
    $form_entity = $entity;
    // The form structure is fetched from Canvas via a GET request. Any subsequent
    // updates to the form via AJAX use Drupal's standard POST request. We only
    // want to fetch the entity from auto-save if we're requesting the original
    // form. If the form is being updated by AJAX, the entity field values in
    // form-state should be used instead.
    if ($this->requestStack->getCurrentRequest()?->getMethod() === 'GET') {
      $autoSave = $this->autoSaveManager->getAutoSaveEntity($entity);
      if (!$autoSave->isEmpty()) {
        \assert($autoSave->entity instanceof FieldableEntityInterface);
        $form_entity = $autoSave->entity;
        // AutoSaveManager::getAutoSaveEntity calls ::create which makes the
        // entity appear new. There are some form widgets that check if the entity
        // is new when constructing their form element. The auto-save entity is
        // never new so we enforce that to avoid issues with form widgets.
        // @see \Drupal\path\Plugin\Field\FieldWidget\PathWidget::formElement
        $form_entity->enforceIsNew(FALSE);
        // We also need to record the loaded revision ID as the auto-save manager
        // does not do this for us and some widgets make use of this information
        // to load a particular revision.
        // @see \Drupal\content_moderation\Plugin\Field\FieldWidget\ModerationStateWidget::formElement
        if ($form_entity instanceof RevisionableInterface) {
          $form_entity->updateLoadedRevisionId();
        }
      }
    }
    $form_state = $this->buildFormState($form, $form_entity, $entity_form_mode);

    return $this->formBuilder()->buildForm($form, $form_state);
  }

}
