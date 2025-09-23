<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\canvas\MissingHostEntityException;
use Drupal\canvas\PropExpressions\StructuredData\Evaluator;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;

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
    private readonly StructuredDataPropExpressionInterface $expression,
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

}
