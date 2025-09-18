<?php

declare(strict_types=1);

// cspell:ignore Bwidth

namespace Drupal\canvas\Entity;

use Drupal\Core\File\Exception\FileException;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\canvas\Routing\ParametrizedImageStyleConverter;
use Drupal\image\Entity\ImageStyle;

/**
 * Parametrized image style, with (currently) a hardcoded parametrized width.
 *
 * The parametrization creates a subdirectory, so ::flush() works as expected.
 *
 * @internal
 */
final class ParametrizedImageStyle extends ImageStyle {

  private array $parameters = [];
  private bool $buildingTemplate = FALSE;

  public static function load($id): ?self {
    $image_style = ImageStyle::load($id);
    if ($image_style === NULL) {
      return NULL;
    }
    return new self($image_style->toArray(), 'image_style');
  }

  public static function loadWithParameters(string $id, array $parameters): ?self {
    $entity = self::load($id);
    if ($entity === NULL) {
      return NULL;
    }
    $entity->parameters = $parameters;
    return $entity;
  }

  /**
   * @see \Drupal\canvas\Routing\ParametrizedImageStyleConverter
   */
  public function buildUrlTemplate(string $path): string {
    $this->buildingTemplate = TRUE;
    $url_template = parent::buildUrl($path);
    $this->buildingTemplate = FALSE;
    return str_replace(\urlencode('{width}'), '{width}', $url_template);
  }

  public function buildUrl($path, $clean_urls = NULL) {
    throw new \LogicException();
  }

  /**
   * @see \Drupal\image\Controller\ImageStyleDownloadController::deliver()
   */
  public function buildUri($uri) {
    $uri = str_replace(
      '/styles/' . $this->id() . '/',
      '/styles/' . $this->id() . '--{width}/',
      parent::buildUri($uri)
    );
    return $this->buildingTemplate
      ? $uri
      : str_replace('{width}', (string) $this->parameters['width'], $uri);
  }

  /**
   * {@inheritdoc}
   */
  public function flush($path = NULL) {
    if ($path === NULL) {
      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      // Delete all parametrized style directories in each registered wrapper.
      $wrappers = $this->getStreamWrapperManager()->getWrappers(StreamWrapperInterface::WRITE_VISIBLE);
      foreach ($wrappers as $wrapper => $wrapper_data) {
        if (!file_exists($wrapper . '://styles/')) {
          continue;
        }
        // Find all parametrized image style directories on disk.
        $directories_to_delete = array_keys($file_system->scanDirectory(
          $wrapper . '://styles', sprintf("/%s--%s/", $this->id(), '.*'),
          ['recurse' => FALSE, 'key' => 'filename'],
        ));
        foreach ($directories_to_delete as $directory) {
          $file_system->deleteRecursive($wrapper . '://styles/' . $directory);
        }
      }
    }
    else {
      // A specific image path has been provided. Flush only that derivative.
      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      $original_building = $this->buildingTemplate;
      $original_width = $this->parameters['width'] ?? NULL;
      $this->buildingTemplate = FALSE;
      // @todo Read this from third-party settings -
      //   https://drupal.org/i/3533563
      // @see config/install/image.style.canvas_parametrized_width.yml
      foreach (ParametrizedImageStyleConverter::ALLOWED_WIDTHS as $width) {
        $this->parameters['width'] = $width;
        $derivative_uri = $this->buildUri($path);
        if (file_exists($derivative_uri)) {
          try {
            $file_system->delete($derivative_uri);
          }
          catch (FileException) {
            // Ignore failed deletes.
          }
        }
      }
      // Restore things the way they were.
      $this->buildingTemplate = $original_building;
      $this->parameters['width'] = $original_width;
    }
    return $this;
  }

}
