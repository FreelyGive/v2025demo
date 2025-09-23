<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use PhpTuf\ComposerStager\API\Process\Service\RsyncProcessRunnerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class DrupalCmsHelperServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $modules = $container->getParameter('container.modules');
    if (isset($modules['package_manager'])) {
      // Decorate the Composer and rsync runners to account for database idle
      // timeouts.
      $container->register('drupal_cms.composer_runner')
        ->setClass(ProcessRunner::class)
        ->setDecoratedService(ComposerProcessRunnerInterface::class)
        ->setArguments([
          new Reference('.inner'),
          new Reference(Connection::class),
        ]);

      $container->register('drupal_cms.rsync_runner')
        ->setClass(ProcessRunner::class)
        ->setDecoratedService(RsyncProcessRunnerInterface::class)
        ->setArguments([
          new Reference('.inner'),
          new Reference(Connection::class),
        ]);
    }
  }

}
