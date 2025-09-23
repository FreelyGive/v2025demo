<?php

declare(strict_types=1);

namespace Drupal\canvas\Config\Schema;

use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Defines a schema data type based on a JSON schema object $ref.
 */
final class JsonSchemaObject extends Mapping {

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, ?TypedDataInterface $parent = NULL) {
    \assert($definition instanceof MapDataDefinition);
    $ref = $parent?->getParent()?->getValue()['$ref'] ?? NULL;
    if ($ref === NULL) {
      // This will be caught by the parent constraint that requires a $ref key.
      parent::__construct($definition, $name, $parent);
      return;
    }
    $schema = \json_decode(\file_get_contents($ref) ?: '{}', TRUE, \JSON_THROW_ON_ERROR);
    if ($schema['type'] !== 'object') {
      throw new \LogicException(sprintf("The schema definition at `%s` is invalid: the parent '\$ref' property should resolve to an object definition.", $parent?->getPropertyPath() ?? $name));
    }
    $supported_property_types = [
      'boolean',
      'integer',
      'number',
      'string',
    ];
    foreach ($schema['properties'] as $property_name => $detail) {
      if (\array_key_exists('$ref', $detail)) {
        $prop_schema = \json_decode(\file_get_contents($detail['$ref']) ?: '{}', TRUE, \JSON_THROW_ON_ERROR);
        if (!\in_array($prop_schema['type'] ?? NULL, $supported_property_types)) {
          throw new \LogicException(sprintf("The schema definition at `%s` is invalid: the parent '\$ref' property contains a '%s' property that uses an unsupported config schema type '%s'. This is not supported.", $parent?->getPropertyPath() ?? $name, $property_name, $prop_schema['type'] ?? 'unknown'));
        }
        // Resolve the $ref.
        $detail += $prop_schema;
      }
      if (!\in_array($detail['type'], $supported_property_types)) {
        throw new \LogicException(sprintf("The schema definition at `%s` is invalid: the parent '\$ref' property contains a '%s' property that uses an unsupported config schema type '%s'. This is not supported.", $parent?->getPropertyPath() ?? $name, $property_name, $detail['type']));
      }
      $definition['mapping'][$property_name] = [
        'type' => $detail['type'] ?? 'unknown',
        'label' => $detail['title'] ?? '',
      ];
      if (!\in_array($property_name, $schema['required'] ?? [], TRUE)) {
        $definition['mapping'][$property_name]['requiredKey'] = FALSE;
      }
      if (\array_key_exists('pattern', $detail)) {
        $definition['mapping'][$property_name]['constraints']['Regex'] = [
          'pattern' => \sprintf('@%s@', $detail['pattern']),
          'message' => '%value does not match the pattern %pattern.',
        ];
      }
      if (\array_key_exists('enum', $detail)) {
        $definition['mapping'][$property_name]['constraints']['Choice'] = ['choices' => $detail['enum']];
      }
    }
    parent::__construct($definition, $name, $parent);
  }

}
