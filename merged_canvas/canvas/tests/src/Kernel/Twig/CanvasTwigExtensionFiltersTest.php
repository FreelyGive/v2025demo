<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Twig;

use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\canvas\Extension\CanvasTwigExtension;
use Drupal\canvas\Routing\ParametrizedImageStyleConverter;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;

// cspell:ignore itok

/**
 * Tests Twig filter functionality.
 *
 * @group canvas
 * @covers \Drupal\canvas\Extension\CanvasTwigExtension::toSrcSet
 */
class CanvasTwigExtensionFiltersTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'file',
    'image',
    'user',
    'canvas',
  ];

  /**
   * @var \Drupal\canvas\Extension\CanvasTwigExtension
   */
  private CanvasTwigExtension $canvasTwigExtension;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ImageStyle::create(
      Yaml::parseFile(__DIR__ . '/../../../../config/install/image.style.canvas_parametrized_width.yml')
    )->save();

    // Fixate the private key & hash salt to get predictable `itok`.
    $this->container->get('state')->set('system.private_key', 'dynamic_image_style_private_key');
    $settings_class = new \ReflectionClass(Settings::class);
    $instance_property = $settings_class->getProperty('instance');
    $settings = new Settings([
      'hash_salt' => 'dynamic_image_style_hash_salt',
    ]);
    $instance_property->setValue(NULL, $settings);

    // Mock File entity
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn('public://balloons.png');
    $file->method('id')->willReturn('123');

    // Mock Image
    $image = $this->createMock(ImageInterface::class);
    $image->method('getWidth')->willReturn(640);
    $image->method('getHeight')->willReturn(427);
    $image->method('isValid')->willReturn(TRUE);

    // Configure mocks
    $imageFactory = $this->createMock(ImageFactory::class);
    $imageFactory->method('get')->with('public://balloons.png')->willReturn($image);
    $streamWrapperManager = $this->createMock(StreamWrapperManagerInterface::class);
    $streamWrapperManager->method('isValidUri')->willReturn(TRUE);
    $fileUrlGenerator = $this->container->get('file_url_generator');

    // Create the extension instance
    $this->canvasTwigExtension = new CanvasTwigExtension($streamWrapperManager, $imageFactory, $fileUrlGenerator);

    $test_base_url = 'http://localhost/sites/default/files';
    $this->setSetting('file_public_base_url', $test_base_url);
  }

  /**
   * @covers       CanvasTwigExtension::toSrcSet
   * @dataProvider providerToSrcSet
   */
  public function testToSrcSet(string $src, ?int $intrinsicImageWidth, ?string $expected): void {
    $actual = $this->canvasTwigExtension->toSrcSet($src, $intrinsicImageWidth);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for testToSrcSet.
   */
  public static function providerToSrcSet(): \Generator {
    $actual_width = 640;
    $expect_all_srcset_widths = self::generateExpectedSrcSet(
      array_filter(ParametrizedImageStyleConverter::ALLOWED_WIDTHS, fn($w) => $w <= $actual_width)
    );

    yield 'public stream wrapper image' => [
      'public://balloons.png',
      $actual_width,
      $expect_all_srcset_widths,
    ];

    yield 'public stream wrapper image, no given width — should inspect image to fetch actual width' => [
      'public://balloons.png',
      NULL,
      $expect_all_srcset_widths,
    ];

    yield 'public stream wrapper image, provided width is bigger than actual width' => [
      'public://balloons.png',
      1024,
      $expect_all_srcset_widths,
    ];

    yield 'public stream wrapper image, provided width is smaller than actual width' => [
      'public://balloons.png',
      200,
      self::generateExpectedSrcSet(
        array_filter(ParametrizedImageStyleConverter::ALLOWED_WIDTHS, fn($w) => $w <= 200)
      ),
    ];
  }

  /**
   * Generate expected srcset for balloons.png.
   */
  private static function generateExpectedSrcSet(array $widths): string {
    return implode(', ', array_map(
      fn ($width) => "/sites/default/files/styles/canvas_parametrized_width--$width/public/balloons.png.webp?itok=uQqtnjV1 {$width}w",
      $widths
    ));
  }

}
