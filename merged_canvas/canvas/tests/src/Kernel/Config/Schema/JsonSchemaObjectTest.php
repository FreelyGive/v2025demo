<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config\Schema;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @covers \Drupal\canvas\Config\Schema\JsonSchemaObject
 * @group canvas
 */
final class JsonSchemaObjectTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;

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
  }

  /**
   * @phpcs:ignore Drupal.Commenting.FunctionComment.SeeAdditionalText
   * @see `type: canvas.json_schema.prop.object`
   */
  public function testSchemaDerivation(): void {
    $typed_config = $this->container->get(TypedConfigManagerInterface::class);
    $config = $typed_config->createFromNameAndData('canvas.json_schema.prop.object', [
      'type' => 'object',
      '$ref' => 'json-schema-definitions://canvas.module/heading',
      'title' => $this->randomString(),
      'examples' => [
        [
          'text' => $this->randomString(),
          'element' => 'bobby',
        ],
      ],
    ]);
    self::assertViolations([
      // We only allow image at this point, but we can still derive schema.
      '$ref' => 'The value you selected is not a valid choice.',
      'examples.0.element' => 'The value you selected is not a valid choice.',
    ], $config->validate());
  }

  public function testInvalidRef(): void {
    $typed_config = $this->container->get(TypedConfigManagerInterface::class);
    $config = $typed_config->createFromNameAndData('canvas.json_schema.prop.object', [
      'type' => 'object',
      '$ref' => 'json-schema-definitions://canvas_config_schema_test.module/pony-ballast',
      'title' => $this->randomString(),
      'examples' => [
        [
          'text' => $this->randomString(),
        ],
      ],
    ]);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("The schema definition at `canvas.json_schema.prop.object.examples` is invalid: the parent '\$ref' property should resolve to an object definition");
    $config->validate();
  }

  public function testInvalidDataType(): void {
    $typed_config = $this->container->get(TypedConfigManagerInterface::class);
    $config = $typed_config->createFromNameAndData('canvas.json_schema.prop.object', [
      'type' => 'object',
      '$ref' => 'json-schema-definitions://canvas_config_schema_test.module/chip-nozzle',
      'title' => $this->randomString(),
      'examples' => [
        [
          'tempo' => 'quite fast',
        ],
      ],
    ]);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("The schema definition at `canvas.json_schema.prop.object.examples` is invalid: the parent '\$ref' property contains a 'special' property that uses an unsupported config schema type 'bonanza'. This is not supported.");
    $config->validate();
  }

  public function testInvalidDataTypeResolution(): void {
    $typed_config = $this->container->get(TypedConfigManagerInterface::class);
    $config = $typed_config->createFromNameAndData('canvas.json_schema.prop.object', [
      'type' => 'object',
      '$ref' => 'json-schema-definitions://canvas_config_schema_test.module/escape-goat',
      'title' => $this->randomString(),
      'examples' => [
        [
          'nesting' => 'unlocked',
        ],
      ],
    ]);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage("The schema definition at `canvas.json_schema.prop.object.examples` is invalid: the parent '\$ref' property contains a 'nesting' property that uses an unsupported config schema type 'object'. This is not supported.");
    $config->validate();
  }

  /**
   * Asserts that the expected violations were found.
   *
   * @param array $expected
   *   Expected violation messages keyed by property paths.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   A list of violations.
   */
  protected static function assertViolations(array $expected, ConstraintViolationListInterface $violations): void {
    $list = [];
    foreach ($violations as $violation) {
      \assert($violation instanceof ConstraintViolation);
      $list[$violation->getPropertyPath()] = \strip_tags((string) $violation->getMessage());
    }
    self::assertEquals($expected, $list);
  }

}
