<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Database\Database;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalTests\Installer\InstallerTestBase;

/**
 * Tests the uninstalling module page is loaded.
 *
 * @group canvas
 */
class UninstallModulePageTest extends InstallerTestBase {

  /**
   * {@inheritdoc}
   *
   * This is to get config/optional/field.field.node.article.field_canvas_demo.yml installed, and trigger the edge case.
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $connection_info = Database::getConnectionInfo();
    if (isset($connection_info['default']['driver']) && $connection_info['default']['driver'] == 'pgsql') {
      $this->markTestSkipped("This test does not support the {$connection_info['default']['driver']} database driver. See https://drupal.org/i/3464830");
    }
  }

  /**
   * Tests that the uninstalling module page is loaded.
   */
  public function testUninstallModulePage(): void {
    \Drupal::service('module_installer')->install(['canvas']);
    $this->drupalGet('admin/modules/uninstall');
    $session = $this->assertSession();
    $this->assertSession()->statusCodeEquals(200);
    // Load & delete dependent field config for module uninstall.
    $entity_type = 'node';
    $field_name = 'field_canvas_demo';
    $field_config = FieldConfig::load($entity_type . '.' . $field_name);
    if ($field_config) {
      $field_config->delete();
    }
    // Load & delete dependent field storage config for module uninstall.
    $field_storage = FieldStorageConfig::load($entity_type . '.' . $field_name);
    if ($field_storage) {
      $field_storage->delete();
    }

    $this->drupalGet('admin/modules/uninstall');
    $this->submitForm(['uninstall[canvas]' => 1], 'Uninstall');
    $this->submitForm([], 'Uninstall');
    $session->pageTextContains('The selected modules have been uninstalled.');
    $session->pageTextNotContains('Drupal Canvas');
  }

}
