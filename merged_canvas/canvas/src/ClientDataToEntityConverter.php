<?php

namespace Drupal\canvas;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\NestedArray;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\Core\Render\Element;
use Drupal\canvas\Form\ClientFormSubmissionHelper;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Form\FormCacheInterface;
use Drupal\canvas\Controller\EntityFormTrait;
use Drupal\canvas\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\canvas\Controller\ClientServerConversionTrait;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\canvas\Storage\ComponentTreeLoader;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use GuzzleHttp\Psr7\Query;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\ConstraintViolation;

class ClientDataToEntityConverter {

  use ClientServerConversionTrait;
  use EntityFormTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: FormBuilderInterface::class)]
    private readonly FormBuilderInterface & FormCacheInterface $formBuilder,
    private readonly CsrfTokenGenerator $csrfTokenGenerator,
    private readonly ComponentTreeLoader $componentTreeLoader,
    private readonly AutoSaveManager $autoSaveManager,
  ) {}

  /**
   * @todo remove the validate flag in https://www.drupal.org/i/3505018.
   */
  public function convert(array $client_data, FieldableEntityInterface $entity, bool $validate = TRUE): void {
    $expected_keys = ['layout', 'model', 'entity_form_fields'];
    if (!empty(array_diff_key($client_data, array_flip($expected_keys)))) {
      throw new \LogicException();
    }
    ['layout' => $layout, 'model' => $model, 'entity_form_fields' => $entity_form_fields] = $client_data;

    $item_list = $this->componentTreeLoader->load($entity);
    try {
      assert(count(array_intersect(['nodeType', 'id', 'name', 'components'], array_keys($layout))) === 4);
      assert($layout['nodeType'] === 'region');
      assert($layout['id'] === 'content');
      assert(is_array($layout['components']));
      $item_list->setValue(self::convertClientToServer($layout['components'], $model, $entity, $validate));
    }
    catch (ConstraintViolationException $e) {
      // @todo Remove iterator_to_array() after https://www.drupal.org/project/drupal/issues/3497677
      throw new ConstraintViolationException(new EntityConstraintViolationList($entity, iterator_to_array($e->getConstraintViolationList())));
    }

    // The current user may not have access any other fields on the entity or
    // this function may have been called to only update the layout.
    $form_validation = new EntityConstraintViolationList($entity);
    if (\count($entity_form_fields) > 0) {
      try {
        $this->setEntityFields($entity, $entity_form_fields);
        $this->autoSaveManager->saveEntityFormViolations($entity);
      }
      catch (ConstraintViolationException $e) {
        if (!$validate) {
          // @todo Remove this in https://drupal.org/i/3505018
          $this->autoSaveManager->saveEntityFormViolations($entity, $e->getConstraintViolationList());
        }
        $form_validation->addAll($e->getConstraintViolationList());
      }
    }

    // Validate the updated entity:
    // - at minimum the component tree field has been updated based on `layout`
    //   and `model`
    // - perhaps also other fields, if `entity_form_fields` was not empty
    $updated_entity_violations = $entity->validate();
    $updated_entity_violations->addAll($form_validation);
    // Validation happens using the server-side representation, but the
    // error message should use the client-side representation received in
    // the request body.
    // @see ::convertClientToServer()
    if ($updated_entity_violations->count() && $validate) {
      $field_name = $item_list->getFieldDefinition()->getName();
      // @todo Remove iterator_to_array() after https://www.drupal.org/project/drupal/issues/3497677
      throw (new ConstraintViolationException(new EntityConstraintViolationList($entity, iterator_to_array($updated_entity_violations))))->renamePropertyPaths([
        "$field_name.0.tree[" . ComponentTreeItemList::ROOT_UUID . "]" => 'layout.children',
        "$field_name.0.tree" => 'layout',
        "$field_name.0.inputs" => 'model',
      ]);
    }
  }

  /**
   * Checks whether the given field should be PATCHed.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $original_field
   *   The original (stored) value for the field.
   * @param \Drupal\Core\Field\FieldItemListInterface $received_field
   *   The received value for the field.
   *
   * @return bool
   *   Whether the field should be PATCHed or not.
   *
   * @throws \Drupal\Core\Access\AccessException
   *   Thrown when the user sending the request is not allowed to update the
   *   field. Only thrown when the user could not abuse this information to
   *   determine the stored value.
   *
   * @see \Drupal\jsonapi\Controller\EntityResource::checkPatchFieldAccess
   */
  private function checkPatchFieldAccess(FieldItemListInterface $original_field, FieldItemListInterface $received_field): bool {
    // If the user is allowed to edit the field, it is always safe to set the
    // received value. We may be setting an unchanged value, but that is ok.
    $field_edit_access = $original_field->access('edit', NULL, TRUE);
    if ($field_edit_access->isAllowed()) {
      return TRUE;
    }

    // The user might not have access to edit the field, but still needs to
    // submit the current field value as part of the PATCH request. For
    // example, the entity keys required by denormalizers. Therefore, if the
    // received value equals the stored value, return FALSE without throwing an
    // exception. But only for fields that the user has access to view, because
    // the user has no legitimate way of knowing the current value of fields
    // that they are not allowed to view, and we must not make the presence or
    // absence of a 403 response a way to find that out.
    if ($original_field->access('view') && $original_field->equals($received_field)) {
      return FALSE;
    }

    // It's helpful and safe to let the user know when they are not allowed to
    // update a field.
    $field_name = $received_field->getName();
    throw new AccessException("The current user is not allowed to update the field '$field_name'.");
  }

  private function setEntityFields(FieldableEntityInterface $entity, array $entity_form_fields): array {
    $expect_form_to_update_changed = FALSE;
    if ($entity instanceof EntityChangedInterface && $entity->hasField('changed')) {
      // TRICKY: We call `$form_object->submitForm($form, $form_state);` which
      // unless overridden by the form class will call
      // \Drupal\Core\Entity\ContentEntityForm::submitForm() then
      // \Drupal\Core\Entity\ContentEntityForm::updateChangedTime() then
      // call \Drupal\Core\Entity\EntityChangedInterface::setChangedTime().
      // We do not support the client sending 'changed' as it will be set by
      // the entity form logic. Allowing the client to set 'changed' could lead
      // to inconsistencies in the entity's changed time, as different edits
      // could be done on different clients and some edits may be done outside
      // Drupal Canvas which would use timestamps provided by the server.
      // \Drupal\Core\Entity\EntityChangedInterface::setChangedTime().
      // @see \Drupal\Core\Entity\ContentEntityForm::updateChangedTime()
      unset($entity_form_fields['changed']);
      $expect_form_to_update_changed = TRUE;
    }
    // Create a form state from the received entity fields.
    $form_state = new FormState();
    $form_state->set('entity', $entity);
    // Expand form values from their respective element name, e.g.
    // ['title[0][value]' => 'Node title'] becomes
    // ['title' => ['value' => 'Node title']].
    // @see \Drupal\canvas\Controller\ApiLayoutController::getEntityData
    \parse_str(\http_build_query($entity_form_fields), $entity_form_fields);

    // Form tokens are user session-specific. It may be that a user is
    // publishing an entity for another user. Therefore, we need to ensure that
    // the form_token is for the current user.
    if (\array_key_exists('form_id', $entity_form_fields)) {
      \assert(\is_string($entity_form_fields['form_id']));
      // @see \Drupal\Core\Form\FormBuilder::prepareForm
      $token_value = 'form_token_placeholder_' . Crypt::hashBase64($entity_form_fields['form_id']);
      $entity_form_fields['form_token'] = $this->csrfTokenGenerator->get($token_value);
    }

    // Handle quirks of managed file elements.
    // @todo Remove this when https://www.drupal.org/project/drupal/issues/3498054 is fixed.
    $file_fields = \array_keys(\array_filter(
      $entity->getFields(),
      static fn (FieldItemListInterface $fieldItemList): bool => \is_a($fieldItemList->getItemDefinition()->getClass(), FileItem::class, TRUE)
    ));
    foreach (\array_intersect_key($entity_form_fields, \array_flip($file_fields)) as $field_name => $values) {
      if (!\is_array($values)) {
        continue;
      }
      foreach ($values as $delta => $value) {
        // @see \Drupal\file\Element\ManagedFile::valueCallback
        if (\array_key_exists('fids', $value) && \is_array($value['fids'])) {
          $entity_form_fields[$field_name][$delta]['fids'] = \implode(' ', $value['fids']);
        }
      }
    }

    $form_object = $this->entityTypeManager->getFormObject($entity->getEntityTypeId(), 'default');
    $form_object->setEntity($entity);
    // Flag this as a programmatic build of the entity form - but do not flag
    // the form as submitted, as we don't want to execute submit handlers such
    // as ::save that would save the entity.
    $form_state = ClientFormSubmissionHelper::prepareProgrammedFormStateForFormObject($form_state, $form_object)
      // With the values provided from the front-end.
      ->setUserInput($entity_form_fields);
    $ajax_form_build_id = $ajax_submitted_form = NULL;
    $was_programmed = TRUE;
    $was_bypassing_programmed_access_checks = FALSE;
    $was_processing_input = TRUE;
    if (\array_key_exists('form_build_id', $entity_form_fields) && \is_string($entity_form_fields['form_build_id'])) {
      // If an AJAX form submission has modified the form state, and we've
      // recorded the form_build_id in entity form fields, load the cached form
      // state and form. Widgets like Media Library keep track of the selected
      // items in form state so we need to make sure we're using the updated
      // form state.
      $ajax_submitted_form = $this->formBuilder->getCache($entity_form_fields['form_build_id'], $form_state);
      // We've retrieved a form state from the cache, but it might now no longer
      // be programmed - despite us flagging it as so above. When we call
      // ::buildForm below, it will attempt to load the form state from the
      // cache. In doing so, it will load the version that might not be flagged
      // as programmed. We have to ensure that our call to ::buildForm doesn't
      // reload the cached form-state with this non-programmed status. When a
      // non-programmed form submission occurs, the form builder looks for a
      // triggering element. This involves looking for the 'op' form state value
      // and if that is not present it falls back to the first button on the
      // form. If the first button on the form has #limit_validation_errors set,
      // this will prevent validation callbacks being executed for the whole
      // form. Some widgets make use of validate callbacks to update the
      // submitted form values, so if these aren't executed - the form values
      // can be in an invalid state. We prevent this occurring by rewriting the
      // form state cache with the programmed flag set before we call
      // ::buildForm. When we're finished submitting the form to build the
      // entity, we will put back the cache entry the way it was.
      $ajax_form_build_id = $entity_form_fields['form_build_id'];
      if (!$form_state->isProgrammed()) {
        // Make note of the cached values so we can reinstate them after
        // submitting the form.
        $was_programmed = $form_state->isProgrammed();
        $was_bypassing_programmed_access_checks = $form_state->isBypassingProgrammedAccessChecks();
        $was_processing_input = $form_state->isProcessingInput();
        // A cached un-programmed form state exists, we have to prevent the form
        // builder from loading the cached form state as it will not be a
        // programmatic submission.
        // @see \Drupal\Core\Form\FormBuilder::buildForm
        // @see \Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase::validateElement
        $form_state->setProcessInput()
          ->setProgrammed()
          ->setProgrammedBypassAccessCheck(FALSE);
        // Update the form state cache so that the Form Builder picks up the
        // programmed version during ::buildForm.
        $this->formBuilder->setCache($ajax_form_build_id, $ajax_submitted_form, $form_state);
      }
    }

    // 'Peek' at the form to work out any form fields that are booleans or buttons.
    $peek_form_object = $this->entityTypeManager->getFormObject($entity->getEntityTypeId(), 'default');
    $peek_form_state = $this->buildFormState($peek_form_object, $entity, 'default')
      // Don't fetch any form values from the request
      ->setUserInput([])
      // Don't process any input or interfere with form caches.
      ->setProcessInput(FALSE)
      // Don't perform any submission logic.
      ->setProgrammed(FALSE);
    $peek_form = $this->formBuilder->buildForm($peek_form_object, $peek_form_state);

    $buttons = array_merge(self::spotElementsByType($peek_form, 'button'), self::spotElementsByType($peek_form, 'submit'));
    $entity_form_fields = array_diff_key($entity_form_fields, \array_flip($buttons));
    // Checkboxes are unique in that the browser doesn't submit a value when the
    // field is unchecked. We need to remove these from the field values when
    // that is the case.
    $checkboxes_parents = ClientFormSubmissionHelper::spotCheckboxesParents($peek_form);
    $empty_checkboxes = \array_filter($checkboxes_parents, static fn (array $parents) => NestedArray::getValue($entity_form_fields, $parents) === '0');
    foreach ($empty_checkboxes as $parents) {
      $value = NestedArray::getValue($entity_form_fields, $parents);
      // This covers NULL, FALSE, 0 and '0' by design.
      if (empty($value)) {
        // Unchecked checkboxes are expected to be set with value NULL. For a
        // normal form submission, this is done for us by the Form Builder. But
        // for a programmatic form submission, this needs to be done manually.
        // @see \Drupal\Core\Form\FormBuilder::handleInputElement
        NestedArray::setValue($entity_form_fields, $parents, NULL);
      }
    }

    // Update the form values with unchecked checkboxes set to NULL and any
    // buttons removed.
    $form_state->setUserInput($entity_form_fields);

    $form = $this->formBuilder->buildForm($form_object, $form_state);
    $violations_list = new EntityConstraintViolationList($entity);
    $errors = $form_state->getErrors();
    $invalid_fields = [];
    if (\count($errors) > 0) {
      foreach ($errors as $element_path => $error) {
        $parents = \explode('][', $element_path);
        // Reverse the property path to element path change made in
        // ContentEntityForm.
        // @see \Drupal\Core\Entity\ContentEntityForm::flagViolations
        $property_path = \implode('.', $parents);
        $invalid_value = NestedArray::getValue($entity_form_fields, $parents);
        $violations_list->add(new ConstraintViolation(
          // Some errors may contain markup from the user of % placeholders in
          // TranslatableMarkup. We just want the plain text version.
          PlainTextOutput::renderFromHtml((string) $error),
          NULL,
          [],
          NULL,
          $property_path,
          $invalid_value,
        ));
        $field_name = \reset($parents);
        $invalid_fields[$property_path] = $field_name;
      }
    }
    // Now trigger the form level submit handler.
    $form_object->submitForm($form, $form_state);
    if ($ajax_form_build_id !== NULL && $ajax_submitted_form !== NULL) {
      // This conversion submitted the form and didn't trigger a form rebuild.
      // That means that the form cache entry will be deleted. The AJAX form in
      // the page however still has a hidden form field pointing to the old form
      // build ID and corresponding form cache entry. This means any further
      // AJAX interactions in the page data form will send this build ID but the
      // form cache won't find the corresponding entry because the building and
      // submission we performed above has removed the form cache. As we're only
      // making use of form submissions to utilize widgets - we don't want to
      // clear the form cache for the form_build_id the actual form in the
      // browser is making use of. So we make sure to re-instate the old form
      // and form state cache entries.
      // @see \Drupal\Core\Form\FormBuilder::processForm
      $form_state->setProgrammedBypassAccessCheck($was_bypassing_programmed_access_checks)
        ->setProcessInput($was_processing_input)
        ->setProgrammed($was_programmed);
      $this->formBuilder->setCache($ajax_form_build_id, $ajax_submitted_form, $form_state);
    }
    // And retrieve the updated entity.
    $updated_entity = $form_object->getEntity();
    \assert($updated_entity instanceof FieldableEntityInterface);
    $fields_to_update = \array_intersect_key(
      $updated_entity->getFields(),
      $entity_form_fields + ($expect_form_to_update_changed ? ['changed' => NULL] : [])
    );
    foreach ($fields_to_update as $name => $items) {
      // For any form elements that yielded validation errors, revert back to
      // the value from the original entity. We do this on a per-delta basis to
      // ensure new valid deltas or any valid changes to existing deltas are
      // retained.
      \assert($items instanceof FieldItemListInterface);
      $new_value = $items->getValue();
      if (!\is_string($name) || !$entity->hasField($name) || \in_array($name, $invalid_fields, TRUE)) {
        $invalid_property_paths = \array_keys(\array_filter($invalid_fields, static fn (string $field_name) => $field_name === $name));
        $original_value = $entity->get($name);
        foreach ($invalid_property_paths as $invalid_property_path) {
          [, $delta] = \explode('.', $invalid_property_path);
          if (\is_numeric($delta)) {
            $new_value[$delta] = $original_value->get($delta)?->getValue();
          }
        }
      }
      $entity->set($name, $new_value);
    }

    assert(!is_null($entity->id()));
    $original_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
    assert($original_entity instanceof FieldableEntityInterface);
    // Filter out form_build_id, form_id and form_token.
    $entity_form_fields = array_filter($entity_form_fields, static fn (string|int $key): bool => is_string($key) && $entity->hasField($key), ARRAY_FILTER_USE_KEY);
    // Copied from \Drupal\jsonapi\Controller\EntityResource::updateEntityField().
    foreach ($entity_form_fields as $field_name => $field_value) {
      \assert(\is_string($field_name));
      try {
        $original_field = $original_entity->get($field_name);
        // The field value on `$entity` will have been set in the call to
        // \Drupal\Core\Entity\Display\EntityFormDisplayInterface::extractFormValues()
        // above. `checkPatchFieldAccess()` will not
        // return a violation if the user does not have 'edit' access but the
        // user has 'view' access and  the received value equals the stored
        // value.
        if (!$this->checkPatchFieldAccess($original_field, $entity->get($field_name))) {
          $entity->set($field_name, $original_field->getValue());
        }
      }
      catch (\Exception $e) {
        $violations_list->add(new ConstraintViolation($e->getMessage(), $e->getMessage(), [], $field_value, "entity_form_fields.$field_name", $field_value));
      }
    }
    if ($violations_list->count()) {
      throw new ConstraintViolationException($violations_list);
    }
    // Filter out form values that are not accessible to the client.
    $values = self::filterFormValues($form_state->getValues(), $form, $original_entity);
    // Collapse form values into the respective element name, e.g.
    // ['title' => ['value' => 'Node title']] becomes
    // ['title[0][value]' => 'Node title'. This keeps the data sent in the same
    // shape as the 'name' attributes on each of the form elements built by the
    // form element and avoids needing to smooth out the idiosyncrasies of each
    // widget's structure.
    // @see \Drupal\canvas\Controller\EntityFormController::form
    $values = Query::parse(\http_build_query(\array_intersect_key($values, $entity->toArray())));
    if ($ajax_form_build_id !== NULL) {
      // Update the form build ID.
      $values['form_build_id'] = $ajax_form_build_id;
    }
    if (\array_key_exists('#form_id', $form)) {
      // Update the form ID.
      $values['form_id'] = $form['#form_id'];
    }
    return $values;
  }

  private static function spotElementsByType(array $form, string $type): array {
    $elements = [];
    foreach (Element::children($form) as $child) {
      $element = $form[$child];
      $elements = \array_merge($elements, self::spotElementsByType($element, $type));

      if (($element['#type'] ?? NULL) === $type && \array_key_exists('#name', $element)) {
        $elements[] = $element['#name'];
      }
    }
    return $elements;
  }

}
