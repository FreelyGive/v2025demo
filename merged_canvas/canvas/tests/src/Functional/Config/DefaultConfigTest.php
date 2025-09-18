<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional\Config;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\KernelTests\AssertConfigTrait;
use Drupal\Tests\canvas\Functional\FunctionalTestBase;

/**
 * @group canvas
 */
class DefaultConfigTest extends FunctionalTestBase {

  use AssertConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'sdc_test_all_props',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * Tests the module-supplied configuration is the same after installation.
   *
   * @see \Drupal\Tests\demo_umami\Functional\DemoUmamiProfileTest::assertDefaultConfig()
   */
  public function testConfig(): void {
    // Just connect directly to the config table so we don't need to worry about
    // the cache layer.
    $active_config_storage = $this->container->get('config.storage');

    $default_config_storage = new FileStorage($this->container->get('extension.list.module')->getPath('canvas') . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY, InstallStorage::DEFAULT_COLLECTION);
    $this->assertDefaultConfig($default_config_storage, $active_config_storage);

    $default_config_storage = new FileStorage($this->container->get('extension.list.module')->getPath('canvas') . '/' . InstallStorage::CONFIG_OPTIONAL_DIRECTORY, InstallStorage::DEFAULT_COLLECTION);
    $this->assertDefaultConfig($default_config_storage, $active_config_storage);
  }

  /**
   * Asserts that the default configuration matches active configuration.
   *
   * @param \Drupal\Core\Config\StorageInterface $default_config_storage
   *   The default configuration storage to check.
   * @param \Drupal\Core\Config\StorageInterface $active_config_storage
   *   The active configuration storage.
   */
  protected function assertDefaultConfig(StorageInterface $default_config_storage, StorageInterface $active_config_storage): void {
    /** @var \Drupal\Core\Config\ConfigManagerInterface $config_manager */
    $config_manager = $this->container->get('config.manager');

    foreach ($default_config_storage->listAll() as $config_name) {
      if ($active_config_storage->exists($config_name)) {
        $result = $config_manager->diff($default_config_storage, $active_config_storage, $config_name);
        $this->assertConfigDiff($result, $config_name, []);
      }
      else {
        $this->fail("$config_name has not been installed");
      }
    }
  }

}
