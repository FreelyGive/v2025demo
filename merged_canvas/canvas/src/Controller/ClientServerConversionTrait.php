<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Validation\BasicRecursiveValidatorFactory;
use Drupal\canvas\ComponentSource\ComponentSourceWithSwitchCasesInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\EntityConstraintViolationList;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraint;
use Drupal\canvas\Validation\ConstraintPropertyPathTranslatorTrait;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 * @phpstan-import-type ComponentTreeItemListArray from \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList
 * @phpstan-import-type ComponentClientStructureArray from \Drupal\canvas\Controller\ApiLayoutController
 * @phpstan-import-type RegionClientStructureArray from \Drupal\canvas\Controller\ApiLayoutController
 * @phpstan-import-type LayoutClientStructureArray from \Drupal\canvas\Controller\ApiLayoutController
 */
trait ClientServerConversionTrait {

  use ConstraintPropertyPathTranslatorTrait;
  use ComponentTreeItemListInstantiatorTrait;

  /**
   * @todo Refactor/remove in https://www.drupal.org/i/3521002
   * @param LayoutClientStructureArray $layout
   * @phpstan-return ComponentTreeItemListArray
   * @throws \Drupal\canvas\Exception\ConstraintViolationException
   *
   * @todo remove the validate flag in https://www.drupal.org/i/3505018.
   */
  protected static function clientToServerTree(array $layout, array $model, ?FieldableEntityInterface $entity, bool $validate = TRUE): array {
    // Transform client-side representation to server-side representation.
    $items = [];
    foreach ($layout as $component) {
      // @todo In https://www.drupal.org/project/canvas/issues/3525746, generate `switch` + `case` client-side node types for the Personalization ComponentSource's components — this requires synchronous changes on the client side.
      // @see https://www.drupal.org/project/canvas/issues/3525746#comment-16121437
      // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem::getClientSideRepresentation()
      // @see \Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList::getClientSideRepresentation()
      assert(in_array($component['nodeType'], ['component', ComponentSourceWithSwitchCasesInterface::SWITCH, ComponentSourceWithSwitchCasesInterface::CASE]));
      $items = \array_merge($items, self::doClientComponentToServerTree($component, $model, ComponentTreeItemList::ROOT_UUID, NULL));
    }
    if ($validate) {
      // Validate the items represent a valid tree.
      /** @var \Symfony\Component\Validator\Validator\RecursiveValidator $validator */
      $validator = \Drupal::service(BasicRecursiveValidatorFactory::class)->createValidator();
      $violations = $validator->validate($items, new ComponentTreeStructureConstraint(['basePropertyPath' => 'layout.children']));
      if ($violations->count() > 0) {
        throw new ConstraintViolationException($violations);
      }
    }
    return self::clientModelToInput($items, $entity, $validate);
  }

  /**
   * @param LayoutClientStructureArray $layout
   * @phpstan-return ComponentTreeItemListArray
   */
  private static function doClientSlotToServerTree(array $layout, array $model, string $parent_uuid): array {
    assert(isset($layout['nodeType']));

    // Regions have no name.
    $name = $layout['nodeType'] === 'slot' ? $layout['name'] : NULL;

    $items = [];
    foreach ($layout['components'] as $component) {
      $items = \array_merge($items, self::doClientComponentToServerTree($component, $model, $parent_uuid, $name));
    }

    return $items;
  }

  /**
   * @phpstan-param ComponentClientStructureArray $layout
   * @phpstan-return ComponentTreeItemListArray
   */
  private static function doClientComponentToServerTree(array $layout, array $model, string $parent_uuid, ?string $parent_slot): array {
    \assert(\array_key_exists('nodeType', $layout));
    assert(in_array($layout['nodeType'], ['component', ComponentSourceWithSwitchCasesInterface::SWITCH, ComponentSourceWithSwitchCasesInterface::CASE]));

    $uuid = $layout['uuid'] ?? NULL;
    $component_id = $layout['type'] ?? NULL;
    $version = NULL;
    // `type` SHOULD be of the form `<Component config entity ID>@<version>`.
    // @see \Drupal\canvas\Entity\VersionedConfigEntityInterface::getVersions()
    if ($component_id !== NULL && str_contains($component_id, '@')) {
      [$component_id, $version] = explode('@', $component_id, 2);
    }
    $component = [
      'uuid' => $layout['uuid'] ?? NULL,
      'component_id' => $component_id,
      'component_version' => $version,
      'inputs' => [],
    ];
    $name = $layout['name'] ?? NULL;
    if ($name !== NULL) {
      $component['label'] = $name;
    }
    if ($uuid !== NULL) {
      $component['inputs'] = $model[$uuid] ?? [];
    }

    if ($parent_slot !== NULL) {
      $component['slot'] = $parent_slot;
      $component['parent_uuid'] = $parent_uuid;
    }
    $items = [$component];

    foreach ($layout['slots'] as $slot) {
      $items = \array_merge($items, self::doClientSlotToServerTree($slot, $model, $layout['uuid']));
    }

    return $items;
  }

  /**
   * @phpcs:ignore
   * @return ComponentTreeItemListArray
   * @throws \Drupal\canvas\Exception\ConstraintViolationException
   */
  private static function clientModelToInput(array $items, ?FieldableEntityInterface $entity, bool $validate = TRUE): array {
    $component_ids = \array_column($items, 'component_id');
    $components = Component::loadMultiple($component_ids);

    $violation_list = NULL;
    if ($validate) {
      $violation_list = $entity ? new EntityConstraintViolationList($entity) : new ConstraintViolationList();
    }
    foreach ($items as $delta => ['uuid' => $uuid, 'component_id' => $component_id, 'inputs' => $inputs, 'component_version' => $version]) {
      $component = $components[$component_id] ?? NULL;
      // If validation is requested, this has already been validated in
      // ::clientToServerTree
      // @see \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraint
      if (!$validate && !$component) {
        continue;
      }
      assert($component instanceof ComponentInterface);
      $component->loadVersion($version);
      $source = $component->getComponentSource();
      // First we transform the incoming client model into input values using
      // the source plugin.
      $items[$delta]['inputs'] = $source->clientModelToInput($uuid, $component, $inputs, $entity, $violation_list);
      if ($violation_list !== NULL) {
        // Then we ensure the input values are valid using the source plugin.
        $component_violations = self::translateConstraintPropertyPathsAndRoot(
          ['inputs.' => 'model.'],
          $source->validateComponentInput($items[$delta]['inputs'], $uuid, $entity)
        );
        if ($component_violations->count() > 0) {
          // @todo Remove the foreach and use ::addAll once
          // https://www.drupal.org/project/drupal/issues/3490588 has been resolved.
          foreach ($component_violations as $violation) {
            $violation_list->add($violation);
          }
        }
      }
    }
    if ($violation_list !== NULL && $violation_list->count()) {
      throw new ConstraintViolationException($violation_list);
    }
    return $items;
  }

  /**
   * @param LayoutClientStructureArray $layout
   * @phpstan-return ComponentTreeItemListArray
   * @throws \Drupal\canvas\Exception\ConstraintViolationException
   *
   * @todo remove the validate flag in https://www.drupal.org/i/3505018.
   */
  protected static function convertClientToServer(array $layout, array $model, ?FieldableEntityInterface $entity = NULL, bool $validate = TRUE): array {
    // Denormalize the `layout` the client sent into a value that the server-
    // side ComponentTreeStructure expects, abort early if it is invalid.
    // (This is the value for the `tree` field prop on the Canvas field type.)
    // @see \Drupal\canvas\Plugin\DataType\ComponentTreeStructure
    // @see \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator
    try {
      return self::clientToServerTree($layout, $model, $entity, $validate);
    }
    catch (ConstraintViolationException $e) {
      throw $e->renamePropertyPaths(["[" . ComponentTreeItemList::ROOT_UUID . "]" => 'layout.children']);
    }
  }

}
