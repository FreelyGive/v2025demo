<?php

namespace Drupal\canvas\Routing;

use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\canvas\Entity\ParametrizedImageStyle;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\Routing\Route;

/**
 * @see \Drupal\canvas\Entity\ParametrizedImageStyle::buildUrlTemplate()
 */
final class ParametrizedImageStyleConverter implements ParamConverterInterface {

  // @todo Read this from third-party settings - https://drupal.org/i/3533563
  // @see config/install/image.style.canvas_parametrized_width.yml
  // @todo Fix hardcoding these widths in https://www.drupal.org/i/3533563.
  public const array ALLOWED_WIDTHS = [16, 32, 48, 64, 96, 128, 256, 384, 640, 750, 828, 1080, 1200, 1920, 2048, 3840];

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $style_prefix = 'canvas_parametrized_';
    if (!str_starts_with($value, $style_prefix)) {
      return ImageStyle::load($value);
    }

    $parts = \explode('--', $value, 2);
    if (\count($parts) !== 2 || !\is_numeric($parts[1])) {
      // We can't convert this.
      return NULL;
    }
    $width = $parts[1];

    $parametrized_image_style = ParametrizedImageStyle::loadWithParameters(
      'canvas_parametrized_width',
      // @see \Drupal\canvas\Entity\ParametrizedImageStyle::$buildingTemplate()
      // @see \Drupal\canvas\Entity\ParametrizedImageStyle::buildUri()
      [
        'width' => (string) $width,
      ]
    );

    if ($parametrized_image_style === NULL) {
      // We can't convert this.
      return NULL;
    }

    // @todo Read this from third-party settings - https://drupal.org/i/3533563
    // @see config/install/image.style.canvas_parametrized_width.yml
    if (!in_array((int) $width, self::ALLOWED_WIDTHS, TRUE)) {
      // Return a 404 (Page Not Found) rather than a 403 (Access Denied) as the
      // image token is for DDoS protection rather than access checking. 404s
      // are more likely to be cached (e.g. at a proxy) which enhances
      // protection from DDoS.
      throw new CacheableNotFoundHttpException($parametrized_image_style, "$width is not an allowed width.");
    }

    $parametrized_image_style->addImageEffect([
      'id' => 'image_scale',
      'weight' => 0,
      'data' => [
        'width' => $width,
      ],
    ]);
    return $parametrized_image_style;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return isset($definition['type']) && $definition['type'] == 'image_style_parametrized';
  }

}
