<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * No-op validation constraint to enable informed data connection suggestions.
 *
 * Note: this MUST be a validation constraint, not an interface, because:
 * - a field or data type's semantics may be context-dependent
 * - a field or data type's semantics may be overridden using
 *   constraints
 * - therefore it must be defined as a validation constraint too.
 * There is precedent for in Drupal core: the `FullyValidatable` constraint.
 *
 * @see https://github.com/json-schema-org/json-schema-spec/issues/1557
 */
#[Constraint(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Validates a URI target', [], ['context' => 'Validation']),
  type: [
    'uri',
  ],
)]
final class UriTargetMediaTypeConstraint extends SymfonyConstraint {

  public const string PLUGIN_ID = 'UriTargetMediaTypeConstraint';

  /**
   * Validation constraint option to define the MIME type targeted by this URI.
   *
   * @var string
   */
  public $mimeType;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions() : array {
    return ['mimeType'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption() : string {
    return 'mimeType';
  }

}
