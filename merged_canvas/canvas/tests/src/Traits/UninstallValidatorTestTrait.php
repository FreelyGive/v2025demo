<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\Core\Extension\ModuleUninstallValidatorException;

/**
 * Defines a trait for testing uninstall validators.
 */
trait UninstallValidatorTestTrait {

  protected function assertUninstallFailureReasons(array $reasons, string|null $not_contains = NULL, array $modules = ['link']): void {
    try {
      $this->container->get('module_installer')->uninstall($modules);
      if (\count($reasons) > 0) {
        self::fail('Expected an exception');
      }
      $this->addToAssertionCount(1);
    }
    catch (ModuleUninstallValidatorException $exception) {
      if ($reasons) {
        self::assertSame($reasons, array_unique($reasons));
        foreach ($reasons as $reason) {
          self::assertStringContainsString($reason, $exception->getMessage());
        }
      }
      if ($not_contains) {
        self::assertStringNotContainsString($not_contains, $exception->getMessage());
      }

    }
  }

}
