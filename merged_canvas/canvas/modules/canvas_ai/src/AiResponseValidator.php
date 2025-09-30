<?php

namespace Drupal\canvas_ai;

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\canvas\Validation\ConstraintPropertyPathTranslatorTrait;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Validation\BasicRecursiveValidatorFactory;

/**
 * Service for validating AI-generated component structures.
 */
class AiResponseValidator {

  use ComponentTreeItemListInstantiatorTrait;
  use ConstraintPropertyPathTranslatorTrait;

  /**
   * Constructs a new AiResponseValidator.
   *
   * @param \Drupal\Core\Validation\BasicRecursiveValidatorFactory $validatorFactory
   *   The validator factory.
   * @param \Drupal\Component\Uuid\UuidInterface $uuidService
   *   The UUID service.
   */
  public function __construct(
    protected readonly BasicRecursiveValidatorFactory $validatorFactory,
    protected readonly UuidInterface $uuidService,
  ) {
  }

  /**
   * Validates the component structure.
   *
   * @param array $componentGroups
   *   The component groups to validate.
   *
   * @throws \Drupal\canvas\Exception\ConstraintViolationException
   *   When validation fails.
   */
  public function validateComponentStructure(array $componentGroups): void {
    // Create a mapping of components to their original paths.
    $pathMapping = [];

    // Convert YAML structure to Canvas ComponentTreeItem format.
    $componentTreeData = $this->convertToComponentTreeData($componentGroups, NULL, NULL, 'components', $pathMapping);

    $componentTreeItemList = $this->createDanglingComponentTreeItemList();
    $componentTreeItemList->setValue($componentTreeData);
    $violations = $componentTreeItemList->validate();

    if ($violations->count() > 0) {
      throw new ConstraintViolationException(
        $this->translateConstraintPropertyPathsAndRoot(
          $this->buildPathTranslationMap($componentTreeData, $pathMapping),
          $violations,
          ''
        ),
        'Component validation errors'
      );
    }
  }

  /**
   * Converts component groups to component tree data.
   *
   * @param array $componentGroups
   *   The component groups to convert.
   * @param string|null $parentUuid
   *   The parent UUID, if any.
   * @param string|null $slotName
   *   The slot name, if any.
   * @param string $pathPrefix
   *   The path prefix for the current level.
   * @param array &$pathMapping
   *   Reference to path mapping array.
   *
   * @return array
   *   The converted component tree data.
   */
  private function convertToComponentTreeData(
    array $componentGroups,
    ?string $parentUuid = NULL,
    ?string $slotName = NULL,
    string $pathPrefix = 'components',
    array &$pathMapping = [],
  ): array {
    $componentTreeData = [];
    foreach ($componentGroups as $groupIndex => $componentGroup) {
      foreach ($componentGroup as $componentId => $componentData) {
        $componentUuid = $this->uuidService->generate();

        $componentPath = sprintf('%s.%d.[%s]', $pathPrefix, $groupIndex, $componentId);
        $pathMapping[$componentUuid] = $componentPath;

        // Create a temp version if the component does not exist to allow
        // validation to proceed. The constraints will flag invalid components
        // later.
        $component = Component::load($componentId);
        $componentVersion = $component ? $component->getActiveVersion() : "temp-version-$componentUuid";
        if ($component instanceof Component && $component->get('source') === 'block' && !empty($componentData['props']['block_uuid'])) {
          $componentVersion = Component::load($componentId)?->getActiveVersion() ?? "temp-version-$componentUuid";
          $inputs = $componentData['props'] ?? [];
        }
        elseif ($component instanceof Component && !empty($componentData['props'])) {
          $source = $component->getComponentSource();
          $clientNormalized = $component->normalizeForClientSide()->values;
          $sources = $clientNormalized['propSources'];
          $clientModel['source'] = $sources;
          $clientModel['resolved'] = $componentData['props'];
          $inputs = $source->clientModelToInput($componentUuid, $component, $clientModel, NULL);
        }
        else {
          $inputs = [];
        }

        $componentTreeItem = [
          'uuid' => $componentUuid,
          'component_id' => $componentId,
          'component_version' => $componentVersion,
          'inputs' => $inputs,
        ];
        if ($parentUuid !== NULL) {
          $componentTreeItem['parent_uuid'] = $parentUuid;
          $componentTreeItem['slot'] = $slotName;
        }

        $componentTreeData[] = $componentTreeItem;

        // Process slots recursively.
        if (isset($componentData['slots']) && is_array($componentData['slots'])) {
          foreach ($componentData['slots'] as $slot => $slotComponentGroups) {
            $slotPath = sprintf('%s.slots.%s', $componentPath, $slot);
            $componentTreeData = array_merge(
              $componentTreeData,
              $this->convertToComponentTreeData(
                $slotComponentGroups,
                $componentUuid,
                $slot,
                $slotPath,
                $pathMapping
              )
            );
          }
        }
      }
    }
    return $componentTreeData;
  }

  /**
   * Builds the path translation map.
   *
   * @param array $componentTreeData
   *   The component tree data.
   * @param array $pathMapping
   *   The path mapping array.
   *
   * @return array
   *   The path translation map.
   */
  private function buildPathTranslationMap(array $componentTreeData, array $pathMapping): array {
    $pathMap = [];

    // Map field-level validation paths from ComponentTreeItemList->validate().
    foreach ($componentTreeData as $index => $component) {
      $uuid = $component['uuid'];
      if (isset($pathMapping[$uuid])) {
        $originalPath = $pathMapping[$uuid];

        // Map component field paths from field-level validation.
        // The actual violation paths are just numeric indices.
        $pathMap["{$index}.component_id"] = $originalPath;
        $pathMap["{$index}.uuid"] = $originalPath;
        $pathMap["{$index}.component_version"] = $originalPath;
        $pathMap["{$index}.parent_uuid"] = $originalPath;

        // For slot validation errors, point to the parent component.
        $pathMap["{$index}.slot"] = isset($component['parent_uuid'])
          ? $pathMapping[$component['parent_uuid']] ?? ''
          : $originalPath;

        // Map input validation paths from field-level validation.
        $pathMap["{$index}.inputs.{$uuid}."] = $originalPath . '.props.';
      }
    }

    return $pathMap;
  }

}
