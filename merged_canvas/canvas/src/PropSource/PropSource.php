<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

/**
 * @phpstan-import-type PropSourceArray from PropSourceBase
 * @phpstan-import-type AdaptedPropSourceArray from PropSourceBase
 * @phpstan-import-type DefaultRelativeUrlPropSourceArray from PropSourceBase
 */
final class PropSource {

  /**
   * @param PropSourceArray|AdaptedPropSourceArray|DefaultRelativeUrlPropSourceArray $prop_source
   */
  public static function parse(array $prop_source): PropSourceBase {
    $source_type_prefix = strstr($prop_source['sourceType'], PropSourceBase::SOURCE_TYPE_PREFIX_SEPARATOR, TRUE);
    // If the prefix separator is not present, then use the full source type.
    // For example: `dynamic` does not need a more detailed source type.
    // @see \Drupal\canvas\PropSource\DynamicPropSource::__toString()
    if ($source_type_prefix === FALSE) {
      $source_type_prefix = $prop_source['sourceType'];
    }

    // The DefaultRelativeUrlPropSource allows referring to a component-defined
    // default value for a URL prop shape at storage time, but will then be
    // transformed to a resolvable (working) absolute or root-relative URL at
    // run time.
    // @see \Drupal\canvas\ComponentSource\UrlRewriteInterface
    if ($source_type_prefix === DefaultRelativeUrlPropSource::getSourceTypePrefix()) {
      return DefaultRelativeUrlPropSource::parse($prop_source);
    }

    // The AdaptedPropSource is the exception: it composes multiple other prop
    // sources, and those are listed under `adapterInputs`.
    if ($source_type_prefix === AdaptedPropSource::getSourceTypePrefix()) {
      assert(array_key_exists('adapterInputs', $prop_source));
      return AdaptedPropSource::parse($prop_source);
    }

    // All others PropSources are the norm: they each have an expression.
    assert(array_key_exists('expression', $prop_source));
    return match ($source_type_prefix) {
      StaticPropSource::getSourceTypePrefix() => StaticPropSource::parse($prop_source),
      DynamicPropSource::getSourceTypePrefix() => DynamicPropSource::parse($prop_source),
      default => throw new \LogicException('Unknown source type.'),
    };
  }

}
