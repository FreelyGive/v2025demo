<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Adapter;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Adapter attribute object.
 *
 * Plugin Namespace: Plugin\Adapter
 *
 * @see \Drupal\canvas\Plugin\Adapter\AdapterInterface
 * @see \Drupal\canvas\Plugin\AdapterManager
 * @see plugin_api
 *
 * @phpstan-import-type JsonSchema from \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Adapter extends Plugin {

  /**
   * @param string $id
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   * @param array<string, JsonSchema> $inputs
   * @param array<string> $requiredInputs
   * @param JsonSchema $output
   * @param class-string|null $deriver
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    protected array $inputs,
    protected array $requiredInputs,
    protected array $output,
    public readonly ?string $deriver = NULL,
  ) {}

}
