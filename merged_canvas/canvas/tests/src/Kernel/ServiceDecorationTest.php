<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Theme\ComponentPluginManager as CoreComponentPluginManager;
use Drupal\canvas\Plugin\BlockManager as CanvasBlockManager;
use Drupal\canvas\Plugin\ComponentPluginManager as CanvasComponentPluginManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * @group canvas
 */
final class ServiceDecorationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['canvas'];

  public function testServiceDecoration(): void {
    $this->assertInstanceOf(CanvasComponentPluginManager::class, $this->container->get(CanvasComponentPluginManager::class));
    $this->assertInstanceOf(CanvasComponentPluginManager::class, $this->container->get(CoreComponentPluginManager::class));
    $this->assertInstanceOf(CanvasComponentPluginManager::class, $this->container->get('plugin.manager.sdc'));

    $this->assertInstanceOf(CanvasBlockManager::class, $this->container->get(CanvasBlockManager::class));
    $this->assertInstanceOf(CanvasBlockManager::class, $this->container->get(BlockManagerInterface::class));
    $this->assertInstanceOf(CanvasBlockManager::class, $this->container->get('plugin.manager.block'));
  }

}
