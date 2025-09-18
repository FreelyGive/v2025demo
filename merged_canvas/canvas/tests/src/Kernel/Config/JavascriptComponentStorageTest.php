<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\canvas\ComponentIncompatibilityReasonRepository;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests JavascriptComponentStorage.
 *
 * @covers \Drupal\canvas\EntityHandlers\JavascriptComponentStorage
 * @covers \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent::createConfigEntity
 * @covers \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent::updateConfigEntity
 * @group JavaScriptComponents
 * @group canvas
 */
final class JavascriptComponentStorageTest extends AssetLibraryStorageTest {

  use UserCreationTrait;
  use ConstraintViolationsTestTrait;
  use GenerateComponentConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'user',
    'system',
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

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system']);
    $this->container->get(ThemeInstallerInterface::class)->install(['stark']);
    $this->installConfig(['canvas']);
  }

  /**
   * @covers \Drupal\canvas\EntityHandlers\CanvasAssetStorage::generateFiles()
   */
  public function testGeneratedFiles(): void {
    $js_component = JavaScriptComponent::create([
      'machineName' => $this->randomMachineName(),
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => FALSE,
      'props' => [],
      'required' => [],
      'slots' => [],
      'js' => [
        'original' => 'console.log("hey");',
        'compiled' => 'console.log("hey");',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test { display: none; }',
      ],
      'dataDependencies' => [],
    ]);
    $this->assertGeneratedFiles($js_component);
  }

  /**
   * @covers \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent::createConfigEntity()
   */
  public function testComponentEntityCreation(): array {
    $js_component_id = $this->randomMachineName();
    $component_id = JsComponent::componentIdFromJavascriptComponentId($js_component_id);
    $reason_repository = $this->container->get(ComponentIncompatibilityReasonRepository::class);

    // When the JS component does not exist, nor should the component config
    // entity.
    $component = Component::load($component_id);
    self::assertNull($component);

    // Now let's create the JavaScript component.
    // Should fail - missing examples.
    $props = [
      'title' => [
        'type' => 'string',
        'title' => 'Title',
      ],
    ];
    $js_component = JavaScriptComponent::create([
      'machineName' => $js_component_id,
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => FALSE,
      'props' => $props,
      'required' => ['title'],
      'slots' => [],
      'js' => [
        'original' => 'console.log("hey");',
        'compiled' => 'console.log("hey");',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test { display: none; }',
      ],
      'dataDependencies' => [],
    ]);
    $this->assertSame([
      '' => 'Prop "title" is required, but does not have example value',
    ], self::violationsToArray($js_component->getTypedData()->validate()));

    // Make it pass validation by adding the missing `examples`, and save it.
    $props['title']['examples'] = ['Title'];
    $js_component->setProps($props);
    $this->assertSame([], self::violationsToArray($js_component->getTypedData()->validate()));
    $js_component->save();

    // No Component config entity is ever created for JavaScript Components not
    // explicitly flagged to be added to Canvas's component library.
    $component = Component::load($component_id);
    self::assertEmpty($reason_repository->getReasons()[JsComponent::SOURCE_PLUGIN_ID] ?? []);
    self::assertNull($component);

    // Use a non-storable prop shape. The JavaScript Component config entity's
    // config schema SHOULD prevent the component author from choosing props
    // that the Drupal Canvas cannot generate an input UX for.
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase
    // @see the `Choice` constraints on `type: canvas.js_component.*`'s for prop `format`.
    $props['title']['format'] = 'hostname';
    $js_component->setProps($props);
    $this->assertSame([
      '' => 'Drupal Canvas does not know of a field type/widget to allow populating the <code>title</code> prop, with the shape <code>{"type":"string","format":"hostname"}</code>.',
      'props.title.format' => 'The value you selected is not a valid choice.',
    ], self::violationsToArray($js_component->getTypedData()->validate()));
    // @see the `Choice` constraints on `type: canvas.js_component.*`'s for prop `type`.
    unset($props['title']['format']);
    $props['title']['type'] = 'array';
    $js_component->setProps($props);
    $this->assertSame([
      '' => 'Prop "title" has invalid example value: [] String value found, but an array or an object is required',
      'props.title.type' => 'The value you selected is not a valid choice.',
    ], self::violationsToArray($js_component->getTypedData()->validate()));

    // In other words: if the JavaScript Component config entity is sufficiently
    // tightly validated, the following should always be true.
    self::assertSame([], $reason_repository->getReasons()[JsComponent::SOURCE_PLUGIN_ID] ?? []);

    // Now remove the attempts to bypass the JavaScriptComponent config entity's
    // validation, enable it and verify that a corresponding Component config
    // entity is created.
    $props['title']['type'] = 'string';
    $js_component
      ->setProps($props)
      ->enable()
      ->save();

    $component = Component::load($component_id);
    self::assertInstanceOf(ComponentInterface::class, $component);
    self::assertNull($component->get('provider'));
    self::assertEquals(['title'], \array_keys($component->getSettings()['prop_field_definitions']));

    // Now update the js component and confirm we update the matching component.
    $props['noodles'] = [
      'type' => 'string',
      'title' => 'What sort of noodles do you like?',
      'examples' => ['Soba', 'Wheat', 'Pool'],
    ];
    $new_name = 'Will you accept my name?';
    $js_component->set('name', $new_name);
    $js_component->setProps($props)->save();

    $component = $this->loadComponent($component_id);
    self::assertEquals($new_name, $component->label());
    self::assertEquals(['noodles', 'title'], \array_keys($component->getSettings()['prop_field_definitions']));

    return $js_component->toArray();
  }

  /**
   * @covers \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent::updateConfigEntity()
   * @depends testComponentEntityCreation
   */
  public function testComponentEntityUpdate(array $js_component_values): void {
    $js_component = JavaScriptComponent::create($js_component_values);
    $js_component->save();
    assert(is_string($js_component->id()));
    $component_id = JsComponent::componentIdFromJavascriptComponentId($js_component->id());

    // Name should carry over.
    $new_name = $js_component->label() . ' — updated';
    $js_component->set('name', $new_name)->save();
    $this->assertSame($new_name, $this->loadComponent($component_id)->label());

    // Status should carry over.
    $this->assertTrue($js_component->status());
    $this->assertTrue($this->loadComponent($component_id)->status());
    $js_component->disable()->save();
    $this->assertFalse($js_component->status());
    $this->assertFalse($this->loadComponent($component_id)->status());
    $js_component->enable()->save();
    $this->assertTrue($js_component->status());
    $this->assertTrue($this->loadComponent($component_id)->status());
  }

  private function loadComponent(string $id): Component {
    // @phpstan-ignore-next-line
    return $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage(Component::ENTITY_TYPE_ID)
      ->loadUnchanged($id);
  }

}
