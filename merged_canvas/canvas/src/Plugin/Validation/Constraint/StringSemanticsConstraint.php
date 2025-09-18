<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * No-op validation constraint to enable informed data connection suggestions.
 *
 * Note: the default string semantic is "structured", because this requires
 * additional validation constraints to be specified anyway. If the default were
 * "prose", then there inevitably would be a lot of noise (for example: URIs and
 * dates would be surfaced as viable values for text, captions, button labels,
 * et cetera.)
 *
 * Note: this MUST be a validation constraint, not an interface, because:
 * - a field or data type's semantics may be context-dependent
 * - a field or data type's semantics may be overridden using
 *   constraints
 * - therefore it must be defined as a validation constraint too.
 * There is precedent for in Drupal core: the `FullyValidatable` constraint.
 */
#[Constraint(
  id: 'StringSemantics',
  label: new TranslatableMarkup('Whether this string contains prose, (HTML) markup or a structured string.', [], ['context' => 'Validation']),
  type: [
    'string',
  ],
)]
final class StringSemanticsConstraint extends SymfonyConstraint {

  /**
   * Prose: a string of written language targeted intended to be read by humans.
   *
   * Examples:
   * - names
   * - titles
   * - sentences without (HTML) markup.
   */
  const PROSE = 'prose';

  /**
   * Markup: a string of HTML markup intended to be processed by web browsers.
   */
  const MARKUP = 'markup';

  /**
   * Structured data representation in strings: dates, URIs, machine names, etc.
   *
   * For this to be matched against SDC props' schema, additional validation
   * constraints must be present.
   */
  const STRUCTURED = 'structured';

  /**
   * Validation constraint option to define the semantics of this string.
   *
   * @var string
   */
  public $semantic = self::STRUCTURED;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions() : array {
    return ['semantic'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption() : string {
    return 'semantic';
  }

}
