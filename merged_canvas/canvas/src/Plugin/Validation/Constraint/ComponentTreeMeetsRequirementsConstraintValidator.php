<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\Config\Schema\Sequence;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ComponentTreeMeetsRequirementsConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use ComponentTreeItemListInstantiatorTrait;

  public function __construct(
    TypedDataManagerInterface $typedDataManager,
  ) {
    $this->setTypedDataManager($typedDataManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(TypedDataManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    assert($constraint instanceof ComponentTreeMeetsRequirementsConstraint);
    if ($value === NULL) {
      return;
    }
    // Regardless of whether we're passed a single array, an array of arrays
    // a component tree item list or a single component tree item, generate a
    // ComponentTreeItemList object, to simplify validation.
    $component_tree_item_list_factory = match (TRUE) {
      // A single content-defined component tree.
      $value instanceof ComponentTreeItemList => static fn ($value): ComponentTreeItemList => $value,
      // A single content-defined component tree item.
      $value instanceof ComponentTreeItem => function ($value): ComponentTreeItemList {
        $list = $this->createDanglingComponentTreeItemList();
        $list->setValue([$value->toArray()]);
        return $list;
      },
      // A multi-value config-defined component tree.
      is_array($value) && $this->context->getObject() instanceof Sequence => function ($value): ComponentTreeItemList {
        $list = $this->createDanglingComponentTreeItemList();
        $list->setValue($value);
        return $list;
      },
      // A single config-defined component tree.
      is_array($value) => function ($value): ComponentTreeItemList {
        $list = $this->createDanglingComponentTreeItemList();
        $list->setValue([$value]);
        return $list;
      },
      default => throw new \UnexpectedValueException(sprintf('The value must be a ComponentTreeItem object, an array representing a single component tree, found %s.', gettype($value)))
    };
    $component_tree_item_list = $component_tree_item_list_factory($value);

    // Perform the necessary detections to check against what the constraint
    // options specify.
    $detected_component_ids = \array_unique(\array_filter(\array_column($component_tree_item_list->getValue(), 'component_id')));
    sort($detected_component_ids);
    $detected_component_classes = Component::getClasses($detected_component_ids);
    $detected_component_interfaces = [];
    foreach ($detected_component_classes as $fqcn) {
      // @phpstan-ignore arrayUnpacking.nonIterable
      $detected_component_interfaces = [...$detected_component_interfaces, ...class_implements($fqcn)];
    }
    $detected_component_interfaces = \array_unique($detected_component_interfaces);
    $detected_prop_source_prefixes = $component_tree_item_list->getPropSourceTypePrefixList();
    sort($detected_prop_source_prefixes);

    foreach (['tree:component_ids', 'tree:component_interfaces', 'inputs:prop_sources'] as $aspect_to_check) {
      $actual_unique_values = match($aspect_to_check) {
        'inputs:prop_sources' => $detected_prop_source_prefixes,
        'tree:component_ids' => $detected_component_ids,
        'tree:component_interfaces' => $detected_component_interfaces,
      };
      foreach (['absence', 'presence'] as $nested_option) {
        $requirement_values = match($aspect_to_check) {
          'inputs:prop_sources' => $constraint->inputs[$nested_option],
          // Distinguish between the two kinds of restrictions supported by this
          // validation constraint: Component (config entity) IDs and Component
          // (plugin) interfaces.
          // The latter must start with the string `Drupal/` because all Drupal-
          // related interfaces must be somewhere under that namespace. All
          // other strings then must logically be Component (config entity) IDs.
          'tree:component_ids' => $constraint->tree[$nested_option] === NULL ? NULL : array_filter($constraint->tree[$nested_option], fn ($v) => !str_starts_with($v, 'Drupal\\')),
          'tree:component_interfaces' => $constraint->tree[$nested_option] === NULL ? NULL : array_filter($constraint->tree[$nested_option], fn ($v) => str_starts_with($v, 'Drupal\\')),
        };
        if ($requirement_values === NULL) {
          // No requirements for this.
          continue;
        }

        $intersection = array_intersect($actual_unique_values, $requirement_values);
        // When absence is required, the intersection must be empty.
        if ($nested_option === 'absence' && !empty($intersection)) {
          foreach ($intersection as $forbidden_value) {
            $this->context
              ->buildViolation(match($aspect_to_check) {
                'inputs:prop_sources' => $constraint->propSourceTypeAbsenceMessage,
                'tree:component_ids' => $constraint->componentAbsenceMessage,
                'tree:component_interfaces' => $constraint->componentInterfaceAbsenceMessage,
              })
              ->setParameter(
                match($aspect_to_check) {
                  'inputs:prop_sources' => '@prop_source_type_prefix',
                  'tree:component_ids' => '@component_id',
                  'tree:component_interfaces' => '@component_interface',
                },
                $forbidden_value
              )
              ->addViolation();
          }
        }
        // When presence is required, the intersection must equal the values
        // specified in the requirement.
        elseif ($nested_option === 'presence' && $intersection != $requirement_values) {
          $missing_values = array_diff($requirement_values, $intersection);
          foreach ($missing_values as $missing_value) {
            $this->context
              ->buildViolation(match($aspect_to_check) {
                'inputs:prop_sources' => $constraint->propSourceTypePresenceMessage,
                'tree:component_ids' => $constraint->componentPresenceMessage,
                'tree:component_interfaces' => $constraint->componentInterfacePresenceMessage,
              })
              ->setParameter(
                match($aspect_to_check) {
                  'inputs:prop_sources' => '@prop_source_type_prefix',
                  'tree:component_ids' => '@component_id',
                  'tree:component_interfaces' => '@component_interface',
                },
                $missing_value,
              )
              ->addViolation();
          }
        }
      }
    }
  }

}
