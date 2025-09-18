<?php

declare(strict_types = 1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\DataType\ConfigEntityAdapter;
use Drupal\canvas\Entity\Component;

/**
 * Some Drupal Canvas constraint validators need a Component config entity.
 *
 * @see \Drupal\ckeditor5\Plugin\Validation\Constraint\TextEditorObjectDependentValidatorTrait
 * @todo Remove this trait after https://www.drupal.org/project/drupal/issues/3427106 lands.
 *
 * @internal
 */
trait ComponentConfigEntityDependentValidatorTrait {

  /**
   * Creates a Component config entity from the execution context.
   *
   * @return \Drupal\canvas\Entity\Component
   *   A Component config entity object.
   */
  private function createComponentConfigEntityFromContext(): Component {
    $root = $this->context->getRoot();
    if ($root->getDataDefinition()->getDataType() === 'entity:component') {
      assert($root instanceof ConfigEntityAdapter);
      $component = $root->getEntity();
      assert($component instanceof Component);
      return $component;
    }
    assert($root->getDataDefinition()->getDataType() === 'canvas.component.*' || $root->getDataDefinition()->getDataType() === 'config_entity_version:component');
    return Component::create($root->toArray());
  }

}
