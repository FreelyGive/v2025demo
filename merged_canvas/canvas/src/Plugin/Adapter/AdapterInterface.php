<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Adapter;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * @phpstan-import-type JsonSchema from \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType
 */
interface AdapterInterface extends PluginInspectionInterface {

  /**
   * @param string $input
   * @param mixed $value
   *
   * @return self
   */
  public function addInput(string $input, mixed $value): self;

  /**
   * @return mixed
   */
  public function adapt(): mixed;

  /**
   * @param JsonSchema $schema
   *
   * @return bool
   */
  public function matchesOutputSchema(array $schema): bool;

  /**
   * @return array<string, JsonSchema>
   */
  public function getInputs(): array;

  /**
   * @param string $input
   *
   * @return bool
   */
  public function inputIsRequired(string $input): bool;

  /**
   * @param string $input
   *
   * @return JsonSchema
   */
  public function getInputSchema(string $input): array;

}
