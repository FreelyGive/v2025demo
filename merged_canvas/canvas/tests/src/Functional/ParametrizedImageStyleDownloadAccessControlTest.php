<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\canvas\Entity\ParametrizedImageStyle;
use Drupal\canvas\Routing\ParametrizedImageStyleConverter;
use Drupal\image\Entity\ImageStyle;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\image\Functional\ImageStyleDownloadAccessControlTest;

/**
 * @group canvas
 * @covers \Drupal\canvas\Routing\ParametrizedImageStyleConverter
 * @covers \Drupal\canvas\Entity\ParametrizedImageStyle
 */
class ParametrizedImageStyleDownloadAccessControlTest extends ImageStyleDownloadAccessControlTest {

  use ContribStrictConfigSchemaTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testParametrized(): void {
    $this->fileSystem->copy(\Drupal::root() . '/core/tests/fixtures/files/image-1.png', 'public://cat.png');

    $parametrized_image_style_url = ParametrizedImageStyle::load('canvas_parametrized_width')?->buildUrlTemplate('public://cat.png');
    \assert(\is_string($parametrized_image_style_url));
    $this->drupalGet($parametrized_image_style_url);
    $this->assertSession()->statusCodeEquals(404);

    // Invalid values for {width}.
    $invalid = [0, 50, 500];
    self::assertCount(0, \array_intersect($invalid, ParametrizedImageStyleConverter::ALLOWED_WIDTHS));
    foreach ($invalid as $width) {
      $this->drupalGet(str_replace('{width}', (string) $width, $parametrized_image_style_url));
      $this->assertSession()->statusCodeEquals(404);
      $this->assertFileDoesNotExist("public://styles/canvas_parametrized_width--$width/public/cat.png.webp");
    }

    // Allowed values for {width}.
    $allowed = [640, 750, 828, 1080, 1200, 1920, 2048, 3840];
    self::assertEquals($allowed, \array_intersect($allowed, ParametrizedImageStyleConverter::ALLOWED_WIDTHS));
    foreach ($allowed as $width) {
      $this->assertFileDoesNotExist("public://styles/canvas_parametrized_width--$width/public/cat.png.webp");
      $this->drupalGet(str_replace('{width}', (string) $width, $parametrized_image_style_url));
      $this->assertSession()->statusCodeEquals(200);
      $this->assertFileExists("public://styles/canvas_parametrized_width--$width/public/cat.png.webp");
    }

    // Even the regular flush works (when the underlying ImageStyle config
    // entity is modified) thanks to `hook_image_style_flush()`.
    // @see \Drupal\canvas\Hook\ImageStyleHooks::imageStyleFlush()
    $this->assertFileExists('public://styles/canvas_parametrized_width--640/public/cat.png.webp');
    ImageStyle::load('canvas_parametrized_width')?->flush();
    $this->assertFileDoesNotExist('public://styles/canvas_parametrized_width--640/public/cat.png.webp');
  }

}
