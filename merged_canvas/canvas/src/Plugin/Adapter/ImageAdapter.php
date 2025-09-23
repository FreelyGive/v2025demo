<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Adapter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;

#[Adapter(
  id: 'image_url_rel_to_abs',
  label: new TranslatableMarkup('Make relative image URL absolute'),
  inputs: [
    'image' => ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image'],
  ],
  requiredInputs: ['image'],
  output: ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image'],
)]
final class ImageAdapter extends AdapterBase implements ContainerFactoryPluginInterface {

  use EntityTypeManagerDependentAdapterTrait;

  /**
   * @var array{src: string, alt: string, width:integer, height:integer}
   */
  protected array $image;

  public function adapt(): mixed {
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties(['filename' => urldecode(basename($this->image['src']))]);
    $image = reset($files);
    if (!$image instanceof FileInterface) {
      throw new \Exception('No image file found');
    }

    return [
      'src' => $image->createFileUrl(FALSE),
      'alt' => $this->image['alt'],
      'width' => $this->image['width'],
      'height' => $this->image['height'],
    ];
  }

}
