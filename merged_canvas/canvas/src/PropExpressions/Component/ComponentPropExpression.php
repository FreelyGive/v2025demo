<?php

declare(strict_types=1);

namespace Drupal\canvas\PropExpressions\Component;

/**
 * For pointing to a prop in a JSON Schema-described component.
 *
 * @internal
 *
 * Used to power ComponentSource plugins without a native explicit input UX that
 * want to reuse the same auto-generated field-based explicit input UX built for
 * SDCs originally, but now also used for "code components".
 *
 * @see \Drupal\Core\Theme\Component\ComponentMetadata
 * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase
 * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent
 * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent
 *
 * @todo Move into a different namespace, perhaps \Drupal\Canvas\GeneratedFieldExplicitInputUx?
 */
final class ComponentPropExpression implements ComponentPropExpressionInterface {

  public function __construct(
    public readonly string $sourceSpecificComponentId,
    public readonly string $propName,
  ) {}

  public function __toString(): string {
    return sprintf(static::PREFIX . "%s␟%s", $this->sourceSpecificComponentId, $this->propName);
  }

  public static function fromString(string $representation): static {
    $parts = explode('␟', mb_substr($representation, 1));
    return new static(...$parts);
  }

}
