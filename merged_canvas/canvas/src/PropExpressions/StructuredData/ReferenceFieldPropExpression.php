<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\StructuredData;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;

final class ReferenceFieldPropExpression implements StructuredDataPropExpressionInterface {

  use CompoundExpressionTrait;

  public function __construct(
    public readonly FieldPropExpression $referencer,
    public readonly ReferenceFieldPropExpression|FieldPropExpression|FieldObjectPropsExpression $referenced,
  ) {}

  public function __toString(): string {
    return static::PREFIX
      . self::withoutPrefix((string) $this->referencer)
      . self::PREFIX_ENTITY_LEVEL
      . self::withoutPrefix((string) $this->referenced);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $host_entity = NULL): array {
    assert($host_entity === NULL || $host_entity instanceof FieldableEntityInterface);
    $dependencies = $this->referencer->calculateDependencies();
    if ($host_entity !== NULL) {
      // ⚠️ Do not require values while calculating dependencies: this MUST not
      // fail.
      $referenced_content_entities = Evaluator::evaluate($host_entity, $this->referencer, is_required: FALSE);
      $referenced_content_entities = match (gettype($referenced_content_entities)) {
        // Reference field containing nothing.
        'null' => [],
        // Reference field containing multiple references.
        'array' => $referenced_content_entities,
        // Reference field containing a single reference.
        default => [$referenced_content_entities],
      };
      $dependencies['content'] = [
        ...$dependencies['content'] ?? [],
        ...array_map(
          fn (FieldableEntityInterface $entity) => $entity->getConfigDependencyName(),
          $referenced_content_entities,
        ),
      ];
    }
    return $dependencies;
  }

  public function withDelta(int $delta): static {
    return new static(
      $this->referencer->withDelta($delta),
      $this->referenced,
    );
  }

  public static function fromString(string $representation): static {
    $parts = explode(self::PREFIX_ENTITY_LEVEL . self::PREFIX_ENTITY_LEVEL, $representation);
    $referencer = FieldPropExpression::fromString($parts[0]);
    // @todo detect and support ReferenceFieldPropExpression + FieldObjectPropsExpression
    $referenced = FieldPropExpression::fromString(static::PREFIX . static::PREFIX_ENTITY_LEVEL . $parts[1]);
    return new static($referencer, $referenced);
  }

  public function isSupported(EntityInterface|FieldItemInterface|FieldItemListInterface $entity): bool {
    assert($entity instanceof EntityInterface);
    $expected_entity_type_id = $this->referencer->entityType->getEntityTypeId();
    $expected_bundles = $this->referencer->entityType->getBundles() ?? [$expected_entity_type_id];
    if ($entity->getEntityTypeId() !== $expected_entity_type_id) {
      throw new \DomainException(sprintf("`%s` is an expression for entity type `%s`, but the provided entity is of type `%s`.", (string) $this, $expected_entity_type_id, $entity->getEntityTypeId()));
    }
    if (!in_array($entity->bundle(), $expected_bundles)) {
      throw new \DomainException(sprintf("`%s` is an expression for entity type `%s`, bundle(s) `%s`, but the provided entity is of the bundle `%s`.", (string) $this, $expected_entity_type_id, implode(', ', $expected_bundles), $entity->bundle()));
    }
    // @todo validate that the field exists?
    return TRUE;
  }

}
