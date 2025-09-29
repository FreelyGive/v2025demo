<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

use Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\canvas\MissingHostEntityException;
use Drupal\canvas\PropExpressions\StructuredData\Evaluator;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Describes structured data to map to 1 explicit input of a component instance.
 *
 * @see \Drupal\canvas\ShapeMatcher\JsonSchemaFieldInstanceMatcher
 * @internal
 *
 * @phpstan-import-type PropSourceArray from PropSourceBase
 */
final class DynamicPropSource extends PropSourceBase {

  public function __construct(
    private readonly FieldPropExpression|ReferenceFieldPropExpression|FieldObjectPropsExpression $expression,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSourceTypePrefix(): string {
    return 'dynamic';
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceType(): string {
    return self::getSourceTypePrefix();
  }

  /**
   * {@inheritdoc}
   *
   * @return PropSourceArray
   */
  public function toArray(): array {
    return [
      'sourceType' => $this->getSourceType(),
      'expression' => (string) $this->expression,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function parse(array $sdc_prop_source): static {
    // `sourceType = dynamic` requires an expression to be specified.
    $missing = array_diff(['expression'], array_keys($sdc_prop_source));
    if (!empty($missing)) {
      throw new \LogicException(sprintf('Missing the keys %s.', implode(',', $missing)));
    }
    assert(array_key_exists('expression', $sdc_prop_source));

    // @phpstan-ignore-next-line argument.type
    return new DynamicPropSource(StructuredDataPropExpression::fromString($sdc_prop_source['expression']));
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(?FieldableEntityInterface $host_entity, bool $is_required): mixed {
    if ($host_entity === NULL) {
      throw new MissingHostEntityException();
    }
    return Evaluator::evaluate($host_entity, $this->expression, $is_required);
  }

  public function asChoice(): string {
    return (string) $this->expression;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $host_entity = NULL): array {
    assert($host_entity === NULL || $host_entity instanceof FieldableEntityInterface);
    // The only dependencies are those of the used expression. If a host entity
    // is given, then `content` dependencies may appear as well; otherwise the
    // calculated dependencies will be limited to the entity types, bundle (if
    // any) and fields (if any) that this expression depends on.
    // @see \Drupal\Tests\canvas\Kernel\PropExpressionDependenciesTest
    return $this->expression->calculateDependencies($host_entity);
  }

  public function label(): TranslatableMarkup|string {
    $entity_data_definition = $this->expression instanceof ReferenceFieldPropExpression
      ? $this->expression->referencer->entityType
      : $this->expression->entityType;
    $field_definitions = $entity_data_definition->getPropertyDefinitions();

    $field_name = $this->expression instanceof ReferenceFieldPropExpression
      ? $this->expression->referencer->fieldName
      : $this->expression->fieldName;
    // TRICKY: FieldPropExpression::$fieldName can be an array, but only
    // when used in a reference.
    // @see https://www.drupal.org/i/3530521
    assert(is_string($field_name));

    \assert(\array_key_exists($field_name, $field_definitions));
    // @phpstan-ignore-next-line return.type
    return $field_definitions[$field_name]->getLabel();
  }

}
