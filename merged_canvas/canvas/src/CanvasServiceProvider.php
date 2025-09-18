<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\canvas\Access\CanvasUiAccessCheck;
use Symfony\Component\DependencyInjection\Reference;

class CanvasServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $modules = $container->getParameter('container.modules');
    assert(is_array($modules));
    if (array_key_exists('media_library', $modules)) {
      $container->register('canvas.media_library.opener', MediaLibraryCanvasPropOpener::class)
        ->addArgument(new Reference(CanvasUiAccessCheck::class))
        ->addTag('media_library.opener');
    }
  }

}
