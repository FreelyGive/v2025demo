<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\canvas\Entity\ParametrizedImageStyle;
use Drupal\image\Entity\ImageStyle;

final class ImageStyleHooks {

  /**
   * Implements hook_image_style_flush().
   */
  #[Hook('image_style_flush')]
  public function imageStyleFlush(ImageStyle $style, ?string $path = NULL): void {
    // Avoid recursion.
    if ($style instanceof ParametrizedImageStyle) {
      return;
    }
    if ($style->id() === 'canvas_parametrized_width') {
      ParametrizedImageStyle::load('canvas_parametrized_width')?->flush($path);
    }
  }

}
