<?php

declare(strict_types=1);

namespace Drupal\canvas\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * @internal
 */
final class ClientFormSubmissionHelper {

  public static function spotCheckboxesParents(array $form): array {
    $checkboxes = [];
    foreach (Element::children($form) as $child) {
      $element = $form[$child];
      $checkboxes = \array_merge($checkboxes, self::spotCheckboxesParents($element));

      if (($element['#type'] ?? NULL) === 'checkbox' && \array_key_exists('#parents', $element)) {
        $checkboxes[] = $element['#parents'];
      }
    }

    return $checkboxes;
  }

  public static function prepareProgrammedFormStateForFormObject(FormStateInterface $form_state, FormInterface $form_object): FormStateInterface {
    // Flag this as a programmatic build of the form.
    return $form_state
      // Set form object
      ->setFormObject($form_object)
      // Flag that we want to process input.
      ->setProcessInput()
      // But that the build is programmed (which bypasses caches etc).
      ->setProgrammed()
      // But access checks should still be accounted for.
      ->setProgrammedBypassAccessCheck(FALSE);
  }

}
