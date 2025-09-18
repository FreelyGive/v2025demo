<?php

declare(strict_types=1);

namespace Drupal\canvas\Render;

use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a value object to hold a rendered preview and additional data.
 */
final class PreviewEnvelope {

  public function __construct(
    public readonly array $previewRenderArray,
    public readonly array $additionalData = [],
    public readonly int $statusCode = Response::HTTP_OK,
  ) {
  }

}
