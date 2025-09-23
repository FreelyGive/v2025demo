<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\KernelTests\Core\Config\ConfigEntityValidationTestBase;

class BetterConfigEntityValidationTestBase extends ConfigEntityValidationTestBase {

  use SchemaCheckTrait;

  /**
   * Asserts validation errors and zero schema incompleteness errors.
   */
  protected function assertValidationErrors(array $expected_messages): void {
    parent::assertValidationErrors($expected_messages);

    // Drupal core's ConfigSchemaChecker performs additional config schema
    // checking beyond just validation: it also checks the completeness.
    // Unfortunately (and understandably), it only runs for *saved* config
    // entities. For Drupal Canvas, we want to be maximally confident that
    // its config is fully covered by config schema, so apply it during all
    // validation checks too, even for unsaved config.
    // @see \Drupal\Core\Config\Development\ConfigSchemaChecker::onConfigSave()
    $this->configName = $name = $this->entity->getConfigDependencyName();
    $this->schema = $this->entity->getTypedData();
    $errors = [];
    foreach ($this->entity->toArray() as $key => $value) {
      $errors[] = $this->checkValue($key, $value);
    }
    $errors = array_merge(...$errors);
    if (empty($errors)) {
      return;
    }

    // Omit any schema errors for which an explicit validation error occurs.
    $config_entity_prefix = $this->entity->getConfigDependencyName() . '.';
    $nonsensical_subtrees = [];
    $schema_errors_not_covered_by_validation = array_filter(
      $errors,
      function (string $schema_error_message, string $property_path) use ($expected_messages, $config_entity_prefix, &$nonsensical_subtrees): bool {
        $relative_property_path = substr($property_path, strlen($config_entity_prefix));
        // Ignore when there is a validation error for exactly this property.
        if (array_key_exists($relative_property_path, $expected_messages)) {
          return FALSE;
        }
        // Ignore when this is a validation error about typecasting, because the
        // config system (for better or worse) does perform typecasting.
        if (str_contains($schema_error_message, 'but applied schema class is')) {
          return FALSE;
        }
        // Ignore when there is a validation error about an unknown key at the
        // parent property path. Track this as a nonsensical subtree, because
        // anything in deeper levels will also trigger 'missing schema' errors.
        // For example, schema error for `props.some_boolean.enum` but a
        // validation error for `props.some_boolean` like:
        // @code
        // 'enum' is an unknown key because props.some_boolean.type is boolean (see config schema type canvas.json_schema.prop.boolean).
        // @endcode
        $parts = explode('.', $relative_property_path);
        $popped = array_pop($parts);
        $parent_property_path = implode('.', $parts);
        $validation_error_message = match (array_key_exists($parent_property_path, $expected_messages)) {
          TRUE => is_array($expected_messages[$parent_property_path])
            ? reset($expected_messages[$parent_property_path])
            : $expected_messages[$parent_property_path],
          FALSE => '',
        };
        assert(is_string($validation_error_message));
        if (str_starts_with($validation_error_message, sprintf("'%s' is an unknown key because %s.type is", $popped, $parent_property_path))) {
          NestedArray::setValue($nonsensical_subtrees, $parts, TRUE);
          return FALSE;
        }
        // Finally, ignore 'missing schema' errors if they target a property
        // path inside a config subtree already marked as nonsensical.
        if ($schema_error_message === 'missing schema') {
          do {
            array_pop($parts);
            if (NestedArray::getValue($nonsensical_subtrees, $parts)) {
              return FALSE;
            }
          } while (!empty($parts));
        }
        return TRUE;
      },
      ARRAY_FILTER_USE_BOTH
    );
    if (empty($schema_errors_not_covered_by_validation)) {
      return;
    }

    // Generate an exception similar to ConfigSchemaChecker::onConfigSave(),
    // but for this *unsaved* config entity.
    $text_errors = [];
    foreach ($schema_errors_not_covered_by_validation as $key => $error) {
      $text_errors[] = new FormattableMarkup('@key @error', ['@key' => $key, '@error' => $error]);
    }
    throw new SchemaIncompleteException("Schema errors for $name with the following errors: " . implode(', ', $text_errors));
  }

  /**
   * Complete override of parent method to add a try/catch for TypeError.
   *
   * The parent method doesn't allow for typed properties. See 👉️👈️ markers for
   * the changes made here (other than three extra asserts to meet phpstan level
   * 6).
   *
   * @todo Remove when https://drupal.org/i/3526908 is fixed
   */
  public function testRequiredPropertyValuesMissing(?array $additional_expected_validation_errors_when_missing = NULL): void {
    \assert($this->entity->getEntityType() instanceof ConfigEntityTypeInterface);
    \assert(\is_array($this->entity->getEntityType()->getPropertiesToExport()));
    $config_entity_properties = array_keys($this->entity->getEntityType()->getPropertiesToExport());

    // Guide developers when $additional_expected_validation_errors_when_missing
    // does not contain sensible values.
    $non_existing_properties = array_diff(array_keys($additional_expected_validation_errors_when_missing ?? []), $config_entity_properties);
    if ($non_existing_properties) {
      throw new \LogicException(sprintf('The test %s lists `%s` in $additional_expected_validation_errors_when_missing but it is not a property of the `%s` config entity type.',
        __METHOD__,
        implode(', ', $non_existing_properties),
        $this->entity->getEntityTypeId(),
      ));
    }
    $properties_with_optional_values = $this->getPropertiesWithOptionalValues();

    // Get the config entity properties that are immutable.
    // @see ::testImmutableProperties()
    $immutable_properties = $this->entity->getEntityType()->getConstraints()['ImmutableProperties'];

    // Config entity properties containing plugin collections are special cases:
    // setting them to NULL would cause them to get out of sync with the plugin
    // collection.
    // @see \Drupal\Core\Config\Entity\ConfigEntityBase::set()
    // @see \Drupal\Core\Config\Entity\ConfigEntityBase::preSave()
    $plugin_collection_properties = $this->entity instanceof EntityWithPluginCollectionInterface
      ? array_keys($this->entity->getPluginCollections())
      : [];

    // To test properties with missing required values, $this->entity must be
    // modified to be able to use ::assertValidationErrors(). To allow restoring
    // $this->entity to its original value for each tested property, a clone of
    // the original entity is needed.
    $original_entity = clone $this->entity;
    foreach ($config_entity_properties as $property) {
      // Do not try to set immutable properties to NULL: their immutability is
      // already tested.
      // @see ::testImmutableProperties()
      if (in_array($property, $immutable_properties, TRUE)) {
        continue;
      }

      // Do not try to set plugin collection properties to NULL.
      if (in_array($property, $plugin_collection_properties, TRUE)) {
        continue;
      }

      $this->entity = clone $original_entity;
      // 👉️ Start overrides of core.
      try {
        \assert(\is_string($property));
        $this->entity->set($property, NULL);
      }
      catch (\TypeError) {
        // Validation is provided at the language level.
        continue;
      }
      // End overrides of core 👈️.
      $expected_validation_errors = in_array($property, $properties_with_optional_values, TRUE)
        ? []
        : [$property => 'This value should not be null.'];

      // @see `type: required_label`
      // @see \Symfony\Component\Validator\Constraints\NotBlank
      if (!$this->isFullyValidatable() && $this->entity->getEntityType()->getKey('label') == $property) {
        $expected_validation_errors = [$property => 'This value should not be blank.'];
      }

      $this->assertValidationErrors(($additional_expected_validation_errors_when_missing[$property] ?? []) + $expected_validation_errors);
    }
  }

}
