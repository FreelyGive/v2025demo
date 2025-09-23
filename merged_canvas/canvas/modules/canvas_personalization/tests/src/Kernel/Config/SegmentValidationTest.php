<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Kernel\Config;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Tests\canvas\Kernel\Config\BetterConfigEntityValidationTestBase;
use Drupal\Tests\canvas\Traits\BetterConfigDependencyManagerTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\CreateTestJsComponentTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\canvas_personalization\Entity\Segment;

/**
 * @group canvas
 * @group canvas_personalization
 */
class SegmentValidationTest extends BetterConfigEntityValidationTestBase {

  use BetterConfigDependencyManagerTrait;
  use ContentTypeCreationTrait;
  use CreateTestJsComponentTrait;
  use GenerateComponentConfigTrait;
  use ContribStrictConfigSchemaTestTrait;

  /**
   * {@inheritdoc}
   */
  protected bool $hasLabel = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The two only modules Drupal truly requires.
    'system',
    'user',
    // The module being tested.
    'canvas',
    'canvas_personalization',
    // Modules providing used Components (and their ComponentSource plugins).
    'block',
    'sdc_test',
    'canvas_test_sdc',
    // Canvas's dependencies (modules providing field types + widgets).
    'field',
    'file',
    'image',
    'link',
    'media',
    'node',
    'options',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entity = Segment::create([
      'id' => 'test_segment',
      'label' => 'Test segment',
      'description' => 'Test segment description',
      'status' => TRUE,
      'rules' => [
        'current_theme' => [
          'id' => 'current_theme',
          'theme' => 'stark',
          'negate' => FALSE,
        ],
      ],
    ]);
    $this->entity->save();
    $this->installConfig('user');
  }

  protected static array $propertiesWithOptionalValues = [
    'description',
  ];

  /**
   * @dataProvider providerSegmentsDependencies
   */
  public function testCalculateDependencies(array $rules, array $expectedDependencies): void {
    $entity = Segment::create([
      'id' => $this->randomMachineName(),
      'label' => 'Test segment',
      'description' => 'Test segment description',
      'status' => TRUE,
      'rules' => [],
    ]);
    foreach ($rules as $plugin_id => $rule) {
      $entity->addSegmentRule($plugin_id, $rule);
    }
    $entity->save();
    $this->assertSame($expectedDependencies, $entity->getDependencies());
  }

  public static function providerSegmentsDependencies(): \Generator {
    yield 'none' => [
      [],
      [],
    ];
    yield 'a module provided plugin' => [
      [
        'user_role' => [
          'id' => 'user_role',
          'roles' => ['authenticated' => 'authenticated'],
          'negate' => FALSE,
        ],
      ],
      ['module' => ['user']],
    ];
  }

  /**
   * @dataProvider providerMissingConditions
   */
  public function testConditionPlugins(array $rules, string $exceptionMessage): void {
    $this->expectException(PluginNotFoundException::class);
    $this->expectExceptionMessage($exceptionMessage);
    $entity = Segment::create([
      'id' => $this->randomMachineName(),
      'label' => 'Test segment',
      'description' => 'Test segment description',
      'status' => TRUE,
      'rules' => [],
    ]);
    foreach ($rules as $plugin_id => $rule) {
      $entity->addSegmentRule($plugin_id, $rule);
    }
    $entity->save();
  }

  public static function providerMissingConditions(): \Generator {
    yield 'a non-existing plugin' => [
      [
        'non_existing_plugin' => [
          'id' => 'non_existing_plugin',
          'properties' => ['foo', 'bar'],
          'negate' => FALSE,
        ],
      ],
      'The "non_existing_plugin" plugin does not exist.',
    ];
    yield 'an opted-out existing plugin' => [
      [
        'request_path' => [
          'id' => 'request_path',
          'pages' => '/opted-out/*',
          'negate' => FALSE,
        ],
      ],
      'The "request_path" plugin does not exist.',
    ];

  }

}
