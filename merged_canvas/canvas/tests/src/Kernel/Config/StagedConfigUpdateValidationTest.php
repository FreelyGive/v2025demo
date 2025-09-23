<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\canvas\Entity\StagedConfigUpdate;
use PHPUnit\Framework\Attributes\DataProvider;

class StagedConfigUpdateValidationTest extends BetterConfigEntityValidationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entity = StagedConfigUpdate::createFromClientSide([
      'id' => 'test_staged_config_update',
      'label' => 'Test Update',
      'target' => 'system.site',
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => ['key' => 'value'],
        ],
      ],
    ]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function testEntityIsValid(): void {
    parent::testEntityIsValid();

    // Also validate config dependencies are computed correctly.
    $this->assertSame([], $this->entity->getDependencies());
  }

  #[DataProvider('validationsProvider')]
  public function testValidations(array $data, array $violations): void {
    $this->installConfig(['system']);

    $sut = StagedConfigUpdate::create($data);
    self::assertInstanceOf(StagedConfigUpdate::class, $sut);

    $actual_violations = $sut->getTypedData()->validate();
    self::assertCount(count($actual_violations), $violations, (string) $actual_violations);
    foreach ($actual_violations as $violation) {
      self::assertContains("{$violation->getPropertyPath()} {$violation->getMessage()}", $violations, (string) $actual_violations);
    }
  }

  public static function validationsProvider(): \Generator {
    yield 'invalid target' => [
      [
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'foo.bar',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => ['key' => 'value'],
          ],
        ],
      ],
      ["target The 'foo.bar' config does not exist."],
    ];

    yield 'invalid action name' => [
      [
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'manipulateConfig',
            'input' => ['key' => 'value'],
          ],
        ],
      ],
      [
        "actions.0.name The 'manipulateConfig' plugin does not exist.",
      ],
    ];
  }

}
