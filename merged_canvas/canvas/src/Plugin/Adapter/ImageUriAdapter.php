<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Adapter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;

#[Adapter(
  id: 'image_extract_url',
  label: new TranslatableMarkup('Extract image URL'),
  inputs: [
    'imageUri' => ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/stream-wrapper-image-uri'],
  ],
  requiredInputs: ['image'],
  output: ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image-uri'],
)]
final class ImageUriAdapter extends AdapterBase implements ContainerFactoryPluginInterface {

  use EntityTypeManagerDependentAdapterTrait;

  protected string $imageUri;

  public function adapt(): mixed {
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties(['filename' => urldecode(basename($this->imageUri))]);
    $image = reset($files);
    if (!$image instanceof FileInterface) {
      throw new \Exception('No image file found');
    }
    return $image->createFileUrl(FALSE);
  }

}
