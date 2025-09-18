<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\canvas\Entity\ParametrizedImageStyle;
use Drupal\canvas\Routing\ParametrizedImageStyleConverter;
use Drupal\file\FileRepositoryInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\TestFileCreationTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Drupal\canvas\Entity\ParametrizedImageStyle
 * @group canvas
 */
class ParametrizedImageStyleTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'canvas',
    'image',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ImageStyle::create(
      Yaml::parseFile(__DIR__ . '/../../../config/install/image.style.canvas_parametrized_width.yml')
    )->save();

    // Fixate the private key & hash salt to get predictable `itok`.
    $this->container->get('state')->set('system.private_key', 'dynamic_image_style_private_key');
    $settings_class = new \ReflectionClass(Settings::class);
    $instance_property = $settings_class->getProperty('instance');
    $settings = new Settings([
      'hash_salt' => 'dynamic_image_style_hash_salt',
    ]);
    $instance_property->setValue(NULL, $settings);
  }

  /**
   * @covers ::buildUrlTemplate
   */
  public function testBuildUrlTemplate(): void {
    // ::buildUrlTemplate() returns an absolute URL, just like ::buildUrl().
    $parametrized_image_style_url = ParametrizedImageStyle::load('canvas_parametrized_width')?->buildUrlTemplate('public://2025-04/cat.jpg');
    self::assertSame(
      PublicStream::baseUrl() . '/styles/canvas_parametrized_width--{width}/public/2025-04/cat.jpg.webp?itok=DnW_VIs-',
      $parametrized_image_style_url
    );

    // Transform to relative URL.
    $file_url_generator = \Drupal::service(FileUrlGeneratorInterface::class);
    assert($file_url_generator instanceof FileUrlGeneratorInterface);
    $parametrized_image_style_relative_url = $file_url_generator->transformRelative($parametrized_image_style_url);
    self::assertSame(
      \base_path() . $this->siteDirectory . '/files/styles/canvas_parametrized_width--{width}/public/2025-04/cat.jpg.webp?itok=DnW_VIs-',
      $parametrized_image_style_relative_url
    );
  }

  /**
   * @covers ::flush
   */
  public function testFlush(): void {
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $path = \dirname(__DIR__, 2) . '/fixtures/images/gracie-big.jpg';
    $contents = \file_get_contents($path);
    \assert(\is_string($contents));
    $file_repository = $this->container->get(FileRepositoryInterface::class);
    \assert($file_repository instanceof FileRepositoryInterface);
    $file = $file_repository->writeData($contents, 'public://good-dog.jpg');
    $original_uri = $file->getFileUri();
    \assert(\is_string($original_uri));
    $uris = [$original_uri];
    self::assertFileExists($original_uri);
    foreach (ParametrizedImageStyleConverter::ALLOWED_WIDTHS as $width) {
      $style = ParametrizedImageStyle::loadWithParameters('canvas_parametrized_width', ['width' => $width]);
      \assert($style instanceof ParametrizedImageStyle);
      $destination = $style->buildUri($original_uri);
      $style->createDerivative($original_uri, $destination);
      self::assertFileExists($destination);
      $uris[] = $destination;
    }

    // Moving a file should trigger a flush of the given paths.
    $file = $file_repository->move($file, 'public://still-a-good-dog.jpg');
    foreach ($uris as $uri) {
      self::assertFileDoesNotExist($uri);
    }

    $new_uri = $file->getFileUri();
    $uris = [];
    \assert(\is_string($new_uri));
    self::assertFileExists($new_uri);
    foreach (ParametrizedImageStyleConverter::ALLOWED_WIDTHS as $width) {
      $style = ParametrizedImageStyle::loadWithParameters('canvas_parametrized_width', ['width' => $width]);
      \assert($style instanceof ParametrizedImageStyle);
      $destination = $style->buildUri($new_uri);
      $style->createDerivative($new_uri, $destination);
      self::assertFileExists($destination);
      $uris[] = $destination;
    }

    // Triggering a manual flush should remove the files.
    $style->flush($new_uri);
    foreach ($uris as $uri) {
      self::assertFileDoesNotExist($uri);
      // Recreate the file.
      $style->createDerivative($new_uri, $uri);
      self::assertFileExists($uri);
    }

    // Delete the original file which should also trigger a flush.
    $file->delete();
    self::assertFileDoesNotExist($new_uri);
    foreach ($uris as $uri) {
      self::assertFileDoesNotExist($uri);
    }
  }

}
