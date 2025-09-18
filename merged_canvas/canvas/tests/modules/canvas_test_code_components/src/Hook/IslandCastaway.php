<?php

declare(strict_types=1);

namespace Drupal\canvas_test_code_components\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\canvas\Element\AstroIsland;

/**
 * Defines test hooks.
 */
final class IslandCastaway {

  public const string WILSON = 'Wilson';

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public static function elementInfoAlter(array &$info): void {
    if (\array_key_exists(AstroIsland::PLUGIN_ID, $info)) {
      $info[AstroIsland::PLUGIN_ID]['#pre_render'][] = [self::class, 'checkForWilson'];
    }
  }

  #[TrustedCallback]
  public static function checkForWilson(array $element): array {
    $name = NestedArray::getValue($element, ['#props', 'name']);
    if ($name === self::WILSON) {
      throw new \Error(\sprintf('%s is a ball, not a person', self::WILSON));
    }
    return $element;
  }

}
