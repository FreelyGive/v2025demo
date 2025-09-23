<?php

declare(strict_types=1);

namespace Drupal\canvas\PropShape;

use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;

/**
 * A candidate storable prop shape: for hook_storage_prop_shape_alter().
 *
 * The difference with StorablePropShape: all alterable properties are:
 * - writable instead of read-only
 * - optional instead of required
 *
 * @see \Drupal\canvas\PropShape\StorablePropShape
 */
final class CandidateStorablePropShape {

  public function __construct(
    public readonly PropShape $shape,
    public FieldTypePropExpression|ReferenceFieldTypePropExpression|FieldTypeObjectPropsExpression|null $fieldTypeProp = NULL,
    public string|null $fieldWidget = NULL,
    public int|null $cardinality = NULL,
    public array|null $fieldStorageSettings = NULL,
    public array|null $fieldInstanceSettings = NULL,
  ) {}

  public static function fromStorablePropShape(StorablePropShape $immutable): CandidateStorablePropShape {
    return new CandidateStorablePropShape(
      $immutable->shape,
      $immutable->fieldTypeProp,
      $immutable->fieldWidget,
      $immutable->cardinality,
      $immutable->fieldStorageSettings,
      $immutable->fieldInstanceSettings,
    );
  }

  public function toStorablePropShape() : ?StorablePropShape {
    if ($this->fieldTypeProp === NULL) {
      return NULL;
    }

    // Note: this will result in a fatal PHP error if a
    // hook_storage_prop_shape_alter() implementation alters incorrectly.
    // @phpstan-ignore-next-line
    return new StorablePropShape($this->shape, $this->fieldTypeProp, $this->fieldWidget, $this->cardinality, $this->fieldStorageSettings, $this->fieldInstanceSettings);
  }

}
