<?php

declare(strict_types=1);

namespace Drupal\canvas\PropShape;

use Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropSource\StaticPropSource;

/**
 * A storable prop shape: a prop shape with corresponding field type + widget.
 *
 * @see \Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression
 * @see \Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression
 * @see \Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression
 * @see \Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::computeStorablePropShape()
 * @internal
 */
final class StorablePropShape {

  /**
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED|int<1, max>|null $cardinality
   */
  public function __construct(
    public readonly PropShape $shape,
    // The corresponding UX for the prop shape:
    // - field type to use + which field properties to extract from an instance of the field type
    public readonly FieldTypePropExpression|ReferenceFieldTypePropExpression|FieldTypeObjectPropsExpression $fieldTypeProp,
    // - which widget to use to populate an instance of the field type
    public readonly string $fieldWidget,
    // - (optionally) which cardinality to use in case of a list (`type: array`)
    // @see \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    public readonly ?int $cardinality = NULL,
    // - (optionally) which storage settings to specify for an instance of this
    //   field type — crucial for e.g. the `enum` use case
    public readonly ?array $fieldStorageSettings = NULL,
    // - (optionally) which storage settings to specify for an instance of this
    //   field type — necessary for the `entity_reference` field type
    public readonly ?array $fieldInstanceSettings = NULL,
  ) {
    if ($this->shape->resolvedSchema['type'] === JsonSchemaType::Array->value) {
      match ($this->cardinality) {
        NULL => throw new \LogicException('Array prop shapes MUST have a cardinality.'),
        0 => throw new \OutOfRangeException('Nonsensical cardinality of zero for an array prop shape.'),
        1 => throw new \OutOfRangeException('Nonsensical cardinality of one for an array prop shape.'),
        default => NULL,
      };
    }
    elseif ($this->cardinality !== NULL) {
      throw new \LogicException('Non-array prop shapes MUST NOT have a cardinality.');
    }
    // In theory, this could be validated: `$this->fieldTypeProp->fieldType` is
    // a field type plugin ID, which determines which field widgets
    // (`$this->fieldWidget`) would be acceptable, and what
    // `$this->fieldStorageSettings`, if any, would be acceptable.
    // In practice, we leave this to the Component config entity, because that
    // is where these values of the StorablePropShape object are persisted.
    // @see \Drupal\canvas\Entity\Component
    // @see `type: canvas.component.*`.
  }

  public function toStaticPropSource(): StaticPropSource {
    return StaticPropSource::generate($this->fieldTypeProp, $this->cardinality, $this->fieldStorageSettings, $this->fieldInstanceSettings);
  }

}
