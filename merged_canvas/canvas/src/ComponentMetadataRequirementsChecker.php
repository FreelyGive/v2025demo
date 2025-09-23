<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Component\ComponentMetadata;
use Drupal\canvas\PropExpressions\Component\ComponentPropExpression;
use Drupal\canvas\PropShape\StorablePropShape;
use JsonSchema\Validator;

/**
 * Defines a class for checking if component metadata meets requirements.
 *
 * @todo Move into a new \Drupal\Canvas\ComponentMetadataDerivers namespace, alongside ComponentPropExpression
 */
final class ComponentMetadataRequirementsChecker {

  /**
   * Checks the given component meets requirements.
   *
   * @param string $component_id
   *   Component ID.
   * @param \Drupal\Core\Theme\Component\ComponentMetadata $metadata
   *   Component metadata.
   * @param string[] $required_props
   *   Array of required prop names.
   *
   * @throws \Drupal\canvas\ComponentDoesNotMeetRequirementsException
   *   When the component does not meet requirements.
   */
  public static function check(string $component_id, ComponentMetadata $metadata, array $required_props): void {
    $messages = [];
    // Canvas always requires schema, even for theme components.
    // @see \Drupal\Core\Theme\ComponentPluginManager::shouldEnforceSchemas()
    // @see \Drupal\Core\Theme\Component\ComponentMetadata::parseSchemaInfo()
    if ($metadata->schema === NULL) {
      throw new ComponentDoesNotMeetRequirementsException(['Component has no props schema']);
    }

    if ($metadata->group == 'Elements') {
      $messages[] = 'Component uses the reserved "Elements" category';
    }

    // Every slot must have a title.
    foreach ($metadata->slots as $slot_name => $slot_definition) {
      if (!array_key_exists('title', $slot_definition)) {
        $messages[] = \sprintf('Slot "%s" must have title', $slot_name);
      }
    }

    $props_for_metadata = GeneratedFieldExplicitInputUxComponentSourceBase::getComponentInputsForMetadata($component_id, $metadata);
    $validator = new Validator();
    foreach ($metadata->schema['properties'] as $prop_name => $prop) {
      if (in_array(Attribute::class, $prop['type'], TRUE)) {
        continue;
      }

      // Enums must not have empty values.
      if (array_key_exists('enum', $prop) && in_array('', $prop['enum'], TRUE)) {
        $messages[] = \sprintf('Prop "%s" has an empty enum value.', $prop_name);
        continue;
      }

      // A prop may not be of type "object" unless it has a $ref defined.
      if ($prop['type'][0] === 'object' && !isset($prop['$ref'])) {
        $messages[] = \sprintf('Prop "%s" is of type "object" without a $ref, which is not supported', $prop_name);
        continue;
      }

      // Required props must have examples.
      if (in_array($prop_name, $required_props, TRUE) && !isset($prop['examples'][0])) {
        $messages[] = \sprintf('Prop "%s" is required, but does not have example value', $prop_name);
      }

      // JSON Schema does not require that examples must be valid, but we do
      // require the first one to be, as we use it as the default value for
      // the prop.
      if (isset($prop['examples'][0])) {
        $example = $prop['examples'][0];
        if (is_array($example)) {
          $example = (object) $example;
        }
        $validator->reset();
        $validator->validate($example, $prop);
        if (!$validator->isValid()) {
          $messages[] = \sprintf('Prop "%s" has invalid example value: %s', $prop_name, implode("\n", array_map(
            static fn(array $error): string => sprintf("[%s] %s", $error['property'], $error['message']),
            $validator->getErrors()
          )));
        }
      }

      // Validation for the additional functionality overlaid on top of the SDC
      // JSON Schema.
      // @see docs/shape-matching-into-field-types.md#3.2
      if (array_key_exists('contentMediaType', $prop) && $prop['contentMediaType'] === 'text/html' && isset($prop['x-formatting-context'])) {
        if (!in_array($prop['x-formatting-context'], ['inline', 'block'], TRUE)) {
          $messages[] = \sprintf('Invalid value "%s" for "x-formatting-context". Valid values are "inline" and "block".', $prop['x-formatting-context']);
          continue;
        }
      }

      // Every prop must have a title.
      if (!isset($prop['title'])) {
        $messages[] = \sprintf('Prop "%s" must have title', $prop_name);
      }
      if (isset($prop['enum'], $prop['meta:enum'])) {
        foreach ($prop['meta:enum'] as $meta_key => $meta_value) {
          if (str_contains((string) $meta_key, ".")) {
            $messages[] = \sprintf('The "meta:enum" keys for the "%s" prop enum cannot contain a dot. Offending key: "%s"', $prop_name, $meta_key);
          }
        }

        // Ensure we replace dots with underscores when checking meta:enums.
        $meta_enum_valid_keys = array_map(function ($key) {
          if (is_numeric($key) && !is_int($key)) {
            // Dots are not valid for config schema, so we need to replace any
            // dot in the key with an underscore.
            return (string) str_replace('.', '_', (string) $key);
          }
          return $key;
        }, $prop['enum']);
        $enum_keys_diff = \array_diff($meta_enum_valid_keys, \array_keys($prop['meta:enum']));
        if (!empty($enum_keys_diff)) {
          $messages[] = \sprintf('The values for the "%s" prop enum must be defined in "meta:enum". Missing keys: "%s"', $prop_name, \implode(', ', $enum_keys_diff));
        }
      }

      // If messages is not empty, we should stop checking,
      // because $prop_shape->getStorage() could trigger warnings.
      if (!empty($messages)) {
        continue;
      }

      // Every prop must have a StorablePropShape.
      $component_prop_expression = new ComponentPropExpression($component_id, $prop_name);
      $prop_shape = $props_for_metadata[(string) $component_prop_expression];
      $storable_prop_shape = $prop_shape->getStorage();
      if ($storable_prop_shape instanceof StorablePropShape) {
        continue;
      }
      $messages[] = \sprintf('Drupal Canvas does not know of a field type/widget to allow populating the <code>%s</code> prop, with the shape <code>%s</code>.', $prop_name, json_encode($prop_shape->schema, JSON_UNESCAPED_SLASHES));
    }
    if (!empty($messages)) {
      throw new ComponentDoesNotMeetRequirementsException($messages);
    }
  }

}
