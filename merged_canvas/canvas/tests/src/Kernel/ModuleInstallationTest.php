<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests module installation.
 *
 * @group canvas
 */
final class ModuleInstallationTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('entity_test');
  }

  public function testModuleInstallation(): void {
    self::assertFalse($this->container->get('module_handler')->moduleExists('canvas'));
    self::assertFalse($this->container->get('theme_handler')->themeExists('canvas_stark'));

    $this->container->get('module_installer')->install(['canvas']);
    self::assertTrue($this->container->get('module_handler')->moduleExists('canvas'));
    $this->assertTCanvasStarkThemeExists();

    $test_entity = EntityTest::create([
      'name' => 'Test entity',
    ]);
    $test_entity->save();

    /** @var \Drupal\canvas\AutoSave\AutoSaveManager $autoSave */
    $autoSave = \Drupal::service(AutoSaveManager::class);
    // Update a value to allow auto-save to be stored.
    $test_entity->set('name', 'I can haz auto save');
    $autoSave->saveEntity($test_entity);
    self::assertCount(1, $autoSave->getAllAutoSaveList());

    $this->container->get('module_installer')->uninstall(['canvas']);
    self::assertFalse($this->container->get('module_handler')->moduleExists('canvas'));
    $this->assertTCanvasStarkThemeExists();
    self::assertCount(0, $autoSave->getAllAutoSaveList(), 'Auto-save items are removed after uninstallation.');

    // Installing the module after uninstallation does not lead to errors.
    $this->container->get('module_installer')->install(['canvas']);
    self::assertTrue($this->container->get('module_handler')->moduleExists('canvas'));
    $this->assertTCanvasStarkThemeExists();
  }

  private function assertTCanvasStarkThemeExists(): void {
    $this->container->get('theme_handler')->reset();
    self::assertTrue($this->container->get('theme_handler')->themeExists('canvas_stark'));
  }

}
