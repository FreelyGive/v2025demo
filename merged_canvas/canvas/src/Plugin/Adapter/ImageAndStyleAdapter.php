<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Adapter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;

#[Adapter(
  id: 'image_apply_style',
  label: new TranslatableMarkup('Apply image style'),
  inputs: [
    'image' => [
      'type' => 'object',
      // @todo Make `width` and `height` required?
      'required' => ['src'],
      'properties' => [
        'src' => [
          'title' => 'Original image stream wrapper URI',
          '$ref' => 'json-schema-definitions://canvas.module/stream-wrapper-image-uri',
        ],
        'width' => [
          'title' => 'Original image width',
          'type' => 'integer',
        ],
        'height' => [
          'title' => 'Original image height',
          'type' => 'integer',
        ],
        'alt' => [
          'title' => 'Original image alternative text',
          'type' => 'string',
        ],
      ],
    ],
    'imageStyle' => ['type' => 'string', '$ref' => 'json-schema-definitions://canvas.module/config-entity-id'],
  ],
  requiredInputs: ['image'],
  output: ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image'],
)]
final class ImageAndStyleAdapter extends AdapterBase implements ContainerFactoryPluginInterface {

  use EntityTypeManagerDependentAdapterTrait;

  /**
   * @var array{src:string, alt: string, width:integer, height:integer}
   */
  protected array $image;
  protected string $imageStyle;

  public function adapt(): mixed {
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties(['filename' => urldecode(basename($this->image['src']))]);
    $image = reset($files);
    if (!$image instanceof FileInterface) {
      throw new \Exception('No image file found');
    }

    $image_style = ImageStyle::load($this->imageStyle);
    if ($image_style instanceof ImageStyleInterface) {
      $src = $image_style->buildUrl((string) $image->getFileUri());
      $dimensions = ['width' => $this->image['width'], 'height' => $this->image['height']];
      $image_style->transformDimensions($dimensions, $this->image['src']);
      ['width' => $width, 'height' => $height] = $dimensions;
    }
    else {
      $src = $image->createFileUrl(FALSE);
      $height = $this->image['height'];
      $width = $this->image['width'];
    }

    return [
      'src' => $src,
      'alt' => $this->image['alt'],
      'width' => $width,
      'height' => $height,
    ];
  }

}
