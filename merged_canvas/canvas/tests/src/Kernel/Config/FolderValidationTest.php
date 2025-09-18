<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Folder;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\Tests\ConfigTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;

class FolderValidationTest extends BetterConfigEntityValidationTestBase {

  use ConfigTestTrait;

  const FOLDER_NAME = 'Unique name';

  use ContribStrictConfigSchemaTestTrait;
  use GenerateComponentConfigTrait;

  protected static $modules = [
    'canvas',
    'sdc',
    // Canvas's dependencies (modules providing field types + widgets).
    'datetime',
    'file',
    'image',
    'options',
    'path',
    'link',
    'filter',
    'ckeditor5',
    'editor',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('canvas');
    $this->entity = Folder::create([
      'name' => 'Test folder, please ignore',
      'configEntityTypeId' => Component::ENTITY_TYPE_ID,
    ]);
    $this->entity->save();
  }

  protected function randomMachineName($length = 8): string {
    return \Drupal::service('uuid')->generate();
  }

  public function testItemsConstraintValidation(): void {
    $this->enableModules(['canvas_test_sdc']);

    // 1. A Component-targeting Folder containing all actual Component config
    // entities. Empty all Folders so we're effectively starting from scratch.
    $this->generateComponentConfig();
    $folders = Folder::loadMultiple();
    foreach ($folders as $folder) {
      $folder->set('items', []);
      $folder->save();
    }

    $components = Component::loadMultiple();
    $this->assertNotEmpty($components);
    $items = \array_keys($components);
    $this->entity->set('items', $items);
    $this->assertValidationErrors([]);

    // 2. A Component-targeting Folder containing 1 actual Asset Library config
    // entity name, 2 non-existing IDs and a code component ID.
    $items = [
      'canvas.asset_library.global',
      'global',
      'fake_component',
    ];
    $this->entity->set('items', $items);
    $this->assertValidationErrors([
      'items.0' => 'The \'canvas.component.canvas.asset_library.global\' config does not exist.',
      'items.1' => 'The \'canvas.component.global\' config does not exist.',
      'items.2' => 'The \'canvas.component.fake_component\' config does not exist.',
    ]);

    // 3. A JavaScriptComponent-targeting Folder containing:
    // - 1 code component ID
    // - The full config name for that same code component.
    // - The original code component ID again (so: duplication).
    self::assertTrue($this->container->get('module_installer')->install(['canvas_test_code_components']));
    $code_components = JavaScriptComponent::loadMultiple();
    self::assertArrayHasKey('canvas_test_code_components_using_imports', $code_components);
    $test_code_component = $code_components['canvas_test_code_components_using_imports'];
    $cool_code_components_folder = Folder::create([
      'name' => 'My cool code components',
      'configEntityTypeId' => JavaScriptComponent::ENTITY_TYPE_ID,
      'items' => [
        // An existing code component config entity's ID.
        $test_code_component->id(),
        // An existing code component config entity's full config name.
        $test_code_component->getConfigDependencyName(),
        // Duplicated — the config target is the same as the ID.
        $test_code_component->getConfigTarget(),
      ],
    ]);
    $this->entity = $cool_code_components_folder;
    $this->assertValidationErrors([
      'items' => 'This collection should contain only unique elements.',
      'items.1' => "The 'canvas.js_component.canvas.js_component.canvas_test_code_components_using_imports' config does not exist.",
    ]);
  }

  public function testConfigEntityTypeIdConstraintValidation(): void {
    $this->entity = Folder::create([
      'name' => 'Test folder, please ignore',
      'configEntityTypeId' => AssetLibrary::ENTITY_TYPE_ID,
    ]);
    $this->assertValidationErrors([
      'configEntityTypeId' => 'The \'asset_library\' plugin must implement or extend Drupal\canvas\Entity\FolderItemInterface.',
    ]);
  }

  public function testUniqueNamePerFolderTypeConstraintValidation(): void {
    $original_name = $this->entity->label();

    // Create new entity with unique name - success.
    $this->entity = Folder::create([
      'name' => self::FOLDER_NAME,
      'configEntityTypeId' => Component::ENTITY_TYPE_ID,
    ]);
    $this->assertValidationErrors([]);

    // Create new entity with different id and type but reuse name - success.
    $this->entity = Folder::create([
      'name' => self::FOLDER_NAME,
      'configEntityTypeId' => JavaScriptComponent::ENTITY_TYPE_ID,
    ]);
    $this->assertValidationErrors([]);

    // Create new entity with different id, but reuse name and type - validation error.
    $this->entity = Folder::create([
      'name' => $original_name,
      'configEntityTypeId' => Component::ENTITY_TYPE_ID,
    ]);
    $this->assertValidationErrors([
      'name' => 'Name <em class="placeholder">Test folder, please ignore</em> is not unique in Folder type "<em class="placeholder">component</em>"',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testImmutableProperties(array $valid_values = []): void {
    // If we don't set a random name here, we will get unrelated validation
    // errors, because the same folder name will then be used multiple times
    // for the same folder type.
    // @see \Drupal\canvas\Plugin\Validation\Constraint\UniqueNamePerFolderTypeConstraint
    $this->entity->set('name', self::randomString());
    parent::testImmutableProperties([
      'configEntityTypeId' => JavaScriptComponent::ENTITY_TYPE_ID,
    ]);
  }

  public function testOneFolderPerItemLimitConstraintValidation(): void {
    $this->enableModules(['canvas_test_sdc']);

    // 1. A Component-targeting Folder containing all actual Component config
    // entities. Empty all folders so we're effectively starting from scratch.
    $this->generateComponentConfig();
    $folders = Folder::loadMultiple();
    foreach ($folders as $folder) {
      $folder->set('items', []);
      $folder->save();
    }
    $components = Component::loadMultiple();
    $this->assertNotEmpty($components);
    $items = \array_keys($components);
    $this->entity->set('items', $items);
    $this->assertValidationErrors([]);
    $this->entity->save();
    $test_item = 'sdc.canvas_test_sdc.video';

    // 2. A Component-targeting Folder, that contains a Component that already
    // belongs to previously saved Folder.
    $this->entity = Folder::create([
      'name' => 'Test folder, please ignore 2',
      'configEntityTypeId' => Component::ENTITY_TYPE_ID,
      'items' => [$test_item],
    ]);
    $this->assertValidationErrors([
      'items.0' => 'Folder item <em class="placeholder">sdc.canvas_test_sdc.video</em> is already assigned to folder <em class="placeholder">Test folder, please ignore</em>',
    ]);

    // 3. Import config containing multiple invalid Folders. Import will succeed
    // (because it does not validate), imported config entities can be loaded by
    // their label and configEntityTypeId. Loading Folder by item id and
    // configEntityTypeId throws RuntimeException.
    // ⚠️ This is testing an extreme edge case that can only be caused by developers.
    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
    $sync = \Drupal::service('config.storage.sync');
    $sync->write('canvas.folder.fe79d3f7-9cd4-46b7-a285-43d2a22b0048', [
      'uuid' => 'fe79d3f7-9cd4-46b7-a285-43d2a22b0048',
      'name' => 'Test Folder, please ignore 3',
      'configEntityTypeId' => Component::ENTITY_TYPE_ID,
      'weight' => 0,
      'items' => [$test_item],
    ]);
    $sync->write('canvas.folder.fe79d3f7-9cd4-46b7-a285-43d2a22b0049', [
      'uuid' => 'fe79d3f7-9cd4-46b7-a285-43d2a22b0049',
      'name' => 'Test Folder, please ignore 4',
      'configEntityTypeId' => Component::ENTITY_TYPE_ID,
      'weight' => 0,
      'items' => [$test_item],
    ]);
    $config_importer = $this->configImporter();
    $config_importer->import();

    $folder = Folder::loadByNameAndConfigEntityTypeId('Test Folder, please ignore 3', Component::ENTITY_TYPE_ID);
    assert($folder instanceof Folder);
    $folder = Folder::loadByNameAndConfigEntityTypeId('Test Folder, please ignore 4', Component::ENTITY_TYPE_ID);
    assert($folder instanceof Folder);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('It is impossible for an item to exist in multiple Folders.');
    Folder::loadByItemAndConfigEntityTypeId($test_item, Component::ENTITY_TYPE_ID);
  }

}
