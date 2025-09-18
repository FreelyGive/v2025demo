<?php

declare(strict_types=1);

namespace Drupal\canvas\TypedData;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\canvas\Entity\ParametrizedImageStyle;
use Drupal\canvas\Plugin\DataType\UriTemplate;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\ImageItemOverride;
use Drupal\file\Entity\File;

/**
 * Computes URI template with a `{width}` variable to populate `<img srcset>`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/srcset#value
 * @see https://tools.ietf.org/html/rfc6570
 * @internal
 */
final class ImageDerivativeWithParametrizedWidth extends UriTemplate implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  private function getParametrizedImageStyle(): ParametrizedImageStyle {
    // @phpstan-ignore-next-line
    return ParametrizedImageStyle::load('canvas_parametrized_width');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->getParent() === NULL) {
      return NULL;
    }
    assert($this->getParent() instanceof ImageItemOverride);

    $entity = $this->getParent()->get('entity');

    // The image field may still be empty.
    if ($entity === NULL) {
      return NULL;
    }
    assert($entity instanceof EntityReference);
    $file = $entity->getTarget()?->getValue();
    assert($file instanceof File);

    assert(is_string($file->getFileUri()));
    $url_template = $this->getParametrizedImageStyle()->buildUrlTemplate($file->getFileUri());
    assert(str_contains($url_template, '{width}'));

    // Transform absolute to relative URL template.
    $file_url_generator = \Drupal::service(FileUrlGeneratorInterface::class);
    assert($file_url_generator instanceof FileUrlGeneratorInterface);
    $url_template = $file_url_generator->transformRelative($url_template);
    assert(str_contains($url_template, '{width}'));
    return $url_template;
  }

  public function getCastedValue(): string {
    return $this->getValue();
  }

  public function getCacheTags() {
    return $this->getParametrizedImageStyle()->getCacheTags();
  }

  public function getCacheContexts() {
    return $this->getParametrizedImageStyle()->getCacheContexts();
  }

  public function getCacheMaxAge() {
    return $this->getParametrizedImageStyle()->getCacheMaxAge();
  }

}
