<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates a component tree item.
 */
#[Constraint(
  id: 'ValidComponentTreeItem',
  label: new TranslatableMarkup('Validates a component tree', [], ['context' => 'Validation']),
  type: [
    // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem
    'field_item:component_tree',
    // @see `type: canvas.component_tree_node`
    'canvas.component_tree_node',
  ],
)]
final class ValidComponentTreeItemConstraint extends SymfonyConstraint {
}
