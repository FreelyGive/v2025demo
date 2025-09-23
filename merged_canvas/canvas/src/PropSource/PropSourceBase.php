<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * @phpstan-type PropSourceTypePrefix 'static'|'dynamic'|'adapter'|'default-relative-url'
 * @phpstan-type PropSourceArray array{sourceType: string, expression: string, value?: mixed|array<string, mixed>, sourceTypeSettings?: array{instance?: array<string, mixed>, storage?: array<string, mixed>}}
 * TRICKY: adapters can be chained/nested, PHPStan does not allow expressing that.
 * @phpstan-type AdaptedPropSourceArray array{sourceType: string, adapterInputs: array<string, mixed>}
 * @phpstan-type DefaultRelativeUrlPropSourceArray array{sourceType: string, value: mixed, jsonSchema: array, componentId: string}
 */
abstract class PropSourceBase implements \Stringable, ContentAwareDependentInterface {

  const SOURCE_TYPE_PREFIX_SEPARATOR = ':';

  /**
   * @param PropSourceArray|AdaptedPropSourceArray|DefaultRelativeUrlPropSourceArray $sdc_prop_source
   */
  abstract public static function parse(array $sdc_prop_source): static;

  abstract public function evaluate(?FieldableEntityInterface $host_entity, bool $is_required): mixed;

  abstract public function asChoice(): string;

  abstract public static function getSourceTypePrefix(): string;

  abstract public function getSourceType(): string;

  /**
   * Gets the array representation.
   *
   * @return PropSourceArray|AdaptedPropSourceArray|DefaultRelativeUrlPropSourceArray
   */
  abstract public function toArray(): array;

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

}
