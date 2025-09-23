<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Defines a trait containing common methods for entity form controllers.
 */
trait EntityFormTrait {

  /**
   * Builds the form state.
   *
   * @param \Drupal\Core\Entity\EntityFormInterface $form
   *   Form object.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity.
   * @param string $entity_form_mode
   *   Form mode ID.
   *
   * @return \Drupal\Core\Form\FormState
   *   Form state.
   */
  protected function buildFormState(EntityFormInterface $form, FieldableEntityInterface $entity, string $entity_form_mode): FormStateInterface {
    $form->setEntity($entity);
    assert($form instanceof ContentEntityForm);
    // `EntityFormDisplay::collectRenderDisplay()` assumes that if the form mode is 'default'
    // then $default_fallback should be TRUE. Otherwise, it will have a warning
    // for an undefined variable.
    // For all other form modes, the default fallback should be FALSE because the
    // client is requesting a specific form mode it expects to be available.
    $default_fallback = $entity_form_mode === 'default';
    $form_display = EntityFormDisplay::collectRenderDisplay($entity, $entity_form_mode, $default_fallback);
    // TRICKY: If a form display is returned with the EntityDisplayBase::CUSTOM_MODE then
    // `EntityFormDisplay::collectRenderDisplay()` was not able to find a form
    // display for the requested form mode and created runtime form display that
    // is not saved in config. For our purpose we don't want this functionality.
    // Since the client is specifically requesting a form mode it should be considered
    // an error if that form mode is not found.
    // We can't simply check `$form_display->getMode() !== $entity_form_mode`
    // because the requested form mode could have altered by a hook and in that case we
    // should respect that change.
    // @see hook_ENTITY_TYPE_form_mode_alter()
    // @see hook_entity_form_mode_alter()
    if (!$form_display || $form_display->getMode() === EntityDisplayBase::CUSTOM_MODE) {
      throw new \UnexpectedValueException(sprintf('The "%s" form display was not found', $entity_form_mode));
    }
    assert($form_display instanceof EntityFormDisplay);
    $form_state = new FormState();
    $form->setFormDisplay($form_display, $form_state);
    return $form_state;
  }

  /**
   * Filters form values to meaningful ones (that affect the edited entity).
   *
   * TRICKY: there are many modules that alter the node edit form but do not
   * have an entity field for the storage of the value. For example core's
   * "Create new revision" checkbox.
   *
   * @return array
   */
  protected static function filterFormValues(array $values, array $form, ?FieldableEntityInterface $entity): array {
    $is_root_call = $entity !== NULL;

    // Filter empty field items in multiple-cardinality field widgets: as many
    // single widget form elements are generated as the cardinality, but not
    // every field type's validation logic is robust enough to handle that.
    // This MUST match massaging + extracting of widget values to not trigger
    // such inappropriate validation errors.
    // @see \Drupal\Core\Field\WidgetBase::extractFormValues()
    // @see \Drupal\datetime\Plugin\Validation\Constraint\DateTimeFormatConstraintValidator::validate()
    if ($is_root_call) {
      foreach (Element::children($form) as $field_name) {
        if ($entity->hasField($field_name)) {
          $field = $entity->get($field_name);
          $original_count = $field->count();
          $non_empty_count = $field->filterEmptyItems()->count();
          if ($original_count !== $non_empty_count) {
            assert($original_count > $non_empty_count);
            for ($empty_delta = $non_empty_count; $empty_delta < $original_count; $empty_delta++) {
              NestedArray::unsetValue($values, $form[$field_name]['widget'][$empty_delta]['#parents']);
            }
          }
        }
      }
    }

    // Recursively filter away certain form values based on form item metadata:
    // 1. those for inaccessible form items (#access)
    // 2. those for unchecked checkboxes, because browsers treat these special
    foreach (Element::children($form) as $child) {
      $element = $form[$child];
      $values = self::filterFormValues($values, $element, NULL);

      if (isset($element['#access']) && $element['#access'] === FALSE) {
        NestedArray::unsetValue($values, $element['#parents']);
      }
      // Filter out unchecked checkboxes - the browser doesn't submit a value
      // when the field is unchecked. We need to remove these from the field
      // values when that is the case.
      // @see \Drupal\Core\Render\Element\Checkboxes::getCheckedCheckboxes()
      if (($element['#type'] ?? NULL) === 'checkbox' && empty($element['#default_value']) && empty($element['#value'])) {
        NestedArray::unsetValue($values, $element['#parents']);
      }
    }

    // Recursively filter away certain form values based on form item name:
    // 1. those for tracking the active vertical tab
    // 2. those that are internal to Drupal's Form API infrastructure
    if ($is_root_call) {
      // @see \Drupal\Core\Render\Element\VerticalTabs::processVerticalTabs()
      $active_tab_elements = \array_filter(\array_keys($values), static fn (string|int $key): bool => is_string($key) && \str_ends_with($key, '__active_tab'));
      // @see \Drupal\Core\Form\FormBuilder
      $values = array_diff_key($values, \array_flip(\array_merge($active_tab_elements, [
        'form_build_id',
        'form_token',
        'submit',
        'form_id',
      ])));
    }

    return $values;
  }

}
