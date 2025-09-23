<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\field\Entity\FieldConfig;

class CanvasConfigUpdater {

  use ComponentTreeItemListInstantiatorTrait;

  /**
   * Flag determining whether deprecations should be triggered.
   *
   * @var bool
   */
  protected bool $deprecationsEnabled = TRUE;

  /**
   * Stores which deprecations were triggered.
   *
   * @var array
   */
  protected array $triggeredDeprecations = [];

  /**
   * Sets the deprecations enabling status.
   *
   * @param bool $enabled
   *   Whether deprecations should be enabled.
   */
  public function setDeprecationsEnabled(bool $enabled): void {
    $this->deprecationsEnabled = $enabled;
  }

  public function updateJavaScriptComponent(JavaScriptComponent $javaScriptComponent): bool {
    $map = [
      'getSiteData' => [
        'v0.baseUrl',
        'v0.branding',
      ],
      'getPageData' => [
        'v0.breadcrumbs',
        'v0.pageTitle',
      ],
      '@drupal-api-client/json-api-client' => [
        'v0.baseUrl',
        'v0.jsonapiSettings',
      ],
    ];

    $changed = FALSE;
    if ($this->needsDataDependenciesUpdate($javaScriptComponent)) {
      $settings = [];
      $jsCode = $javaScriptComponent->getJs();
      foreach ($map as $var => $neededSetting) {
        if (str_contains($jsCode, $var)) {
          $settings = \array_merge($settings, $neededSetting);
        }
      }
      if (\count($settings) > 0) {
        $current = $javaScriptComponent->get('dataDependencies');
        $current['drupalSettings'] = \array_unique(\array_merge($current['drupalSettings'] ?? [], $settings));
        $javaScriptComponent->set('dataDependencies', $current);
      }
      else {
        $javaScriptComponent->set('dataDependencies', []);
        $changed = TRUE;
      }
    }
    return $changed;
  }

  /**
   * Checks if the code component still misses the 'dataDependencies' property.
   *
   * @return bool
   */
  public function needsDataDependenciesUpdate(JavaScriptComponent $javaScriptComponent): bool {
    if ($javaScriptComponent->get('dataDependencies') !== NULL) {
      return FALSE;
    }

    $deprecations_triggered = &$this->triggeredDeprecations['3533458'][$javaScriptComponent->id()];
    if ($this->deprecationsEnabled && !$deprecations_triggered) {
      $deprecations_triggered = TRUE;
      @trigger_error('JavaScriptComponent config entities without "dataDependencies" property is deprecated in canvas:1.0.0 and will be removed in canvas:1.0.0. See https://www.drupal.org/node/3538276', E_USER_DEPRECATED);
    }
    return TRUE;
  }

  public function updateConfigEntityWithComponentTreeInputs(ComponentTreeEntityInterface|FieldConfig $entity): bool {
    \assert($entity instanceof ConfigEntityInterface);
    if (!$this->needsComponentInputsCollapsed($entity)) {
      return FALSE;
    }
    $tree = self::getComponentTreeForEntity($entity);
    self::optimizeTreeInputs($tree);
    if ($entity instanceof ComponentTreeEntityInterface) {
      $entity->setComponentTree($tree->getValue());
      return TRUE;
    }
    $entity->set('default_value', $tree->getValue());
    return TRUE;
  }

  public function needsComponentInputsCollapsed(ComponentTreeEntityInterface|FieldConfig $entity): bool {
    if ($entity instanceof FieldConfig && $entity->getType() !== ComponentTreeItem::PLUGIN_ID) {
      return FALSE;
    }
    $tree = self::getComponentTreeForEntity($entity);
    $before_hash = self::getInputHash($tree);
    self::optimizeTreeInputs($tree);
    $after_hash = self::getInputHash($tree);
    if ($before_hash === $after_hash) {
      return FALSE;
    }
    $deprecations_triggered = &$this->triggeredDeprecations['3538487'][\sprintf('%s:%s', $entity->getEntityTypeId(), $entity->id())];
    if ($this->deprecationsEnabled && !$deprecations_triggered) {
      $deprecations_triggered = TRUE;
      // phpcs:ignore
      @trigger_error(\sprintf('%s with ID %s has a component tree without collapsed input values - this is deprecated in canvas:1.0.0 and will be removed in canvas:1.0.0. See https://www.drupal.org/node/3539207', $entity->getEntityType()->getLabel(), $entity->id()), E_USER_DEPRECATED);
    }
    return TRUE;
  }

  private static function getComponentTreeForEntity(ComponentTreeEntityInterface|FieldConfig $entity): ComponentTreeItemList {
    if ($entity instanceof ComponentTreeEntityInterface) {
      return $entity->getComponentTree();
    }
    // @phpstan-ignore-next-line PHPStan correctly
    \assert($entity instanceof FieldConfig);
    $field_default_value_tree = self::staticallyCreateDanglingComponentTreeItemList(\Drupal::typedDataManager());
    $field_default_value_tree->setValue($entity->get('default_value') ?? []);
    return $field_default_value_tree;
  }

  private static function getInputHash(ComponentTreeItemList $tree): string {
    // @phpstan-ignore-next-line
    return \implode(':', \array_map(function (ComponentTreeItem $item): string {
      try {
        $inputs = $item->getInputs();
      }
      catch (\UnexpectedValueException | MissingComponentInputsException) {
        $inputs = [];
      }
      return \hash('xxh64', \json_encode($inputs, \JSON_THROW_ON_ERROR));
    }, \iterator_to_array($tree)));

  }

  private static function optimizeTreeInputs(ComponentTreeItemList $tree): void {
    foreach ($tree as $item) {
      \assert($item instanceof ComponentTreeItem);
      $item->optimizeInputs();
    }
  }

}
