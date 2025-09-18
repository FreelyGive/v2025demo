<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\canvas\Plugin\ComponentPluginManager;

trait GenerateComponentConfigTrait {

  protected function generateComponentConfig(): void {
    // Installing a module with SDCs should result in Component config entities
    // being generated. This requires hook_module_preinstall() and subsequently
    // hook_modules_installed() to be invoked, but `::setUp()` and
    // `::enableModules()` do not do that, for performance reasons.
    // @see \Drupal\KernelTests\KernelTestBase::enableModules()
    // @see \Drupal\Tests\ckeditor5\Kernel\CKEditor5PluginManagerTest::enableModules()
    $componentPluginManager = $this->container->get(ComponentPluginManager::class);

    // 1. Simulate hook_module_preinstall() getting invoked.
    // @see canvas_module_preinstall()
    $componentPluginManager->clearCachedDefinitions();

    // 2. Simulate canvas_modules_installed() getting invoked.
    // @see \Drupal\canvas\Hook\ComponentSourceHooks::modulesInstalled()
    $componentPluginManager->getDefinitions();

    // Repeat, but for block-based components.
    if ($this->container->get('module_handler')->moduleExists('block')) {
      $blockPluginManager = $this->container->get(BlockManagerInterface::class);
      $blockPluginManager->clearCachedDefinitions();
      $blockPluginManager->getDefinitions();
    }
  }

}
