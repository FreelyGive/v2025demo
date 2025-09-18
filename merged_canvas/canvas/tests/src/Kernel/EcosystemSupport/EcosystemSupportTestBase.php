<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\EcosystemSupport;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ExtensionLifecycle;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;

/**
 * Base class for testing Canvas support for some aspect of the Drupal ecosystem.
 */
abstract class EcosystemSupportTestBase extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'ckeditor5',
    'editor',
    'canvas',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('canvas');
  }

  public static function getUninstalledStableModulesWithPlugin(string $plugin_type_subdir): array {
    return array_keys(array_filter(
      \Drupal::service(ModuleExtensionList::class)->getList(),
      // Filter out contrib, hidden, testing, experimental, and deprecated
      // modules. We also don't need to enable modules that are already enabled.
      // @phpstan-ignore-next-line
      fn (Extension $module): bool => $module->origin === 'core'
        && empty($module->info['hidden'])
        // @phpstan-ignore-next-line
        && $module->status == FALSE
        && $module->info['package'] !== 'Testing'
        && $module->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] !== ExtensionLifecycle::EXPERIMENTAL
        && $module->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER] !== ExtensionLifecycle::DEPRECATED
        && file_exists($module->getPath() . '/src/' . $plugin_type_subdir),
    ));
  }

}
