<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

#[Constraint(
  id: 'JsComponentHasValidAndSupportedSdcMetadata',
  // @see docs/shape-matching-into-field-types.md, section 3.1.2.b
  label: new TranslatableMarkup('Maps to valid SDC definition, and meets Canvas requirements.', [], ['context' => 'Validation']),
  type: [
    'canvas.js_component.*',
  ],
)]
final class JsComponentHasValidAndSupportedSdcMetadataConstraint extends SymfonyConstraint {}
