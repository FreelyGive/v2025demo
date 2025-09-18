<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\canvas\PropSource\AdaptedPropSource;
use Drupal\canvas\PropSource\DynamicPropSource;
use Drupal\canvas\PropSource\DefaultRelativeUrlPropSource;
use Drupal\canvas\PropSource\StaticPropSource;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Checks that a component tree (or an array of them) meets requirements.
 *
 * Examples:
 * - content entities and ContentTypeTemplate config entities MAY use
 *   DynamicPropSources, but PageRegion and Pattern config entities MUST NOT
 * - content entities, ContentTypeTemplate and Pattern config entities MUST NOT
 *   use any "title" or "messages" blocks, but a PageRegion config entity MAY do
 *   so.
 *
 * Assumes valid component trees.
 *
 * @see \Drupal\canvas\Plugin\Validation\Constraint\ValidComponentTreeItemConstraint
 * @phpstan-import-type PropSourceTypePrefix from \Drupal\canvas\PropSource\PropSourceBase
 * @phpstan-import-type ComponentConfigEntityId from \Drupal\canvas\Entity\Component
 */
#[Constraint(
  id: 'ComponentTreeMeetRequirements',
  label: new TranslatableMarkup('Validates one or multiple component trees to meet specified requirements', [], ['context' => 'Validation']),
)]
class ComponentTreeMeetsRequirementsConstraint extends SymfonyConstraint {

  public string $componentPresenceMessage = "The '@component_id' component must be present.";
  public string $componentAbsenceMessage = "The '@component_id' component must be absent.";
  public string $componentInterfacePresenceMessage = "The '@component_interface' component interface must be present.";
  public string $componentInterfaceAbsenceMessage = "The '@component_interface' component interface must be absent.";
  public string $propSourceTypePresenceMessage = "The '@prop_source_type_prefix' prop source type must be present.";
  public string $propSourceTypeAbsenceMessage = "The '@prop_source_type_prefix' prop source type must be absent.";

  /**
   * Requirements for component tree's inputs: absence and/or presence.
   *
   * Accepts for both `absence` and `presence` either NULL (no requirement) or a
   * list of:
   * - a prop source prefix
   *
   * @var array{'absence': ?array<PropSourceTypePrefix>, 'presence': ?array<PropSourceTypePrefix>}
   *
   * @see \Drupal\canvas\PropSource\PropSourceBase::getSourceTypePrefix()
   */
  public array $inputs;

  /**
   * Requirements for component tree's components: absence and/or presence.
   *
   * Accepts for both `absence` and `presence` either NULL (no requirement) or a
   * list of:
   * - a Component config entity ID
   * - a (plugin) interface
   *
   * @var array{'absence': ?array<ComponentConfigEntityId|class-string>, 'presence': ?array<ComponentConfigEntityId|class-string>}
   */
  public array $tree;

  /**
   * {@inheritdoc}
   */
  public function __construct(mixed $options = NULL, ?array $groups = NULL, mixed $payload = NULL) {
    parent::__construct($options, $groups, $payload);

    // Match the constraint option validation logic in ::normalizeOptions(), but
    // for the nested key-value pairs.
    $missing_nested_options = [];
    foreach (['tree', 'inputs'] as $option) {
      foreach (['absence', 'presence'] as $nested_option) {
        if (!array_key_exists('absence', $this->$option)) {
          $missing_nested_options[] = "$option.$nested_option";
        }
      }
    }
    if (!empty($missing_nested_options)) {
      throw new MissingOptionsException(sprintf('The options "%s" must be set for constraint "%s".', implode('", "', array_keys($missing_nested_options)), static::class), array_keys($missing_nested_options));
    }

    // Verify sensible values are present for $this->inputs: an array of source
    // type prefixes, or NULL if there is no requirement.
    $supported_prop_source_types = [
      StaticPropSource::getSourceTypePrefix(),
      DynamicPropSource::getSourceTypePrefix(),
      AdaptedPropSource::getSourceTypePrefix(),
      DefaultRelativeUrlPropSource::getSourceTypePrefix(),
    ];
    foreach (['absence', 'presence'] as $nested_option) {
      if ($this->inputs[$nested_option] === NULL) {
        continue;
      }
      if (!is_array($this->inputs[$nested_option])) {
        throw new InvalidArgumentException(sprintf(
          'The option "%s" must be an array of source type prefixes. Supported source type prefixes are: "%s".',
          "inputs.$nested_option",
          implode('", "', $supported_prop_source_types),
        ));
      }
      $invalid_values = array_diff($this->inputs[$nested_option], $supported_prop_source_types);
      if ($invalid_values) {
        throw new InvalidArgumentException(sprintf(
          'The option "%s" specifies the invalid source type prefixes "%s". Supported source type prefixes are: "%s".',
          "inputs.$nested_option",
          implode('", "', $invalid_values),
          implode('", "', $supported_prop_source_types),
        ));
      }
    }

    // Verify sensible values are present for $this->tree: an array of Component
    // config entity IDs, or NULL if there is no requirement.
    foreach (['absence', 'presence'] as $nested_option) {
      if ($this->tree[$nested_option] === NULL) {
        continue;
      }
      if (!is_array($this->tree[$nested_option])) {
        throw new InvalidArgumentException(sprintf(
          'The option "%s" must be an array of Component config entity IDs and/or Component (plugin) interfaces.',
          "tree.$nested_option",
        ));
      }
      // TRICKY: verifying sensible values are present for $this->tree is
      // impossible, because they refer to Component config entities, which do
      // not yet exist at this time.
      // @see \Drupal\canvas\Entity\Component
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['inputs', 'tree'];
  }

}
