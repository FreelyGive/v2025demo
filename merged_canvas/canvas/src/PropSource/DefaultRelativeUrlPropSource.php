<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\canvas\ComponentSource\UrlRewriteInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\JsonSchemaInterpreter\JsonSchemaStringFormat;
use Drupal\canvas\PropShape\PropShape;

/**
 * Prop source that is used to reference default relative URLs.
 *
 * Example links and image URLs should refer to real resources, but the full
 * URL cannot be hardcoded. Component sources that implement UrlRewriteInterface
 * are capable of taking a relative URL and expanding it to an absolute URL
 * that can be used as a default value.
 *
 * @see \Drupal\canvas\ComponentSource\UrlRewriteInterface
 * @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::exampleValueRequiresEntity()
 * @internal
 *
 * @phpstan-import-type DefaultRelativeUrlPropSourceArray from PropSourceBase
 */
final class DefaultRelativeUrlPropSource extends PropSourceBase {

  private readonly UrlRewriteInterface $componentSource;

  public function __construct(
    private readonly mixed $value,
    private readonly array $jsonSchema,
    private readonly string $componentId,
  ) {
    $component = Component::load($componentId);
    assert($component instanceof Component);
    $componentSource = $component->getComponentSource();
    assert($componentSource instanceof UrlRewriteInterface);
    $this->componentSource = $componentSource;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSourceTypePrefix(): string {
    return 'default-relative-url';
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
   * @return DefaultRelativeUrlPropSourceArray
   */
  public function toArray(): array {
    return [
      'sourceType' => $this->getSourceType(),
      'value' => $this->value,
      // Store the:
      // - resolved schema, to avoid $refs changing later having an effect
      // - normalized schema, to minimize storage consumption
      // @todo Make this far less clunky 🙈
      'jsonSchema' => PropShape::normalize(
        // First do basic normalization, and resolve.
        PropShape::normalize($this->jsonSchema)->resolvedSchema
      )->schema,
      'componentId' => $this->componentId,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function parse(array $sdc_prop_source): static {
    // `sourceType = default-relative-url` requires a value and schema to be specified.
    $missing = array_diff(['value', 'jsonSchema', 'componentId'], array_keys($sdc_prop_source));
    if (!empty($missing)) {
      throw new \LogicException(sprintf('Missing the keys %s.', implode(',', $missing)));
    }
    assert(array_key_exists('value', $sdc_prop_source));
    assert(array_key_exists('jsonSchema', $sdc_prop_source));
    assert(array_key_exists('componentId', $sdc_prop_source));

    // @todo Make this far less clunky 🙈
    $minimal = PropShape::normalize(
    // First do basic normalization, and resolve.
      PropShape::normalize($sdc_prop_source['jsonSchema'])->resolvedSchema
    )->schema;
    ksort($sdc_prop_source['jsonSchema']);
    ksort($minimal);
    if ($sdc_prop_source['jsonSchema'] !== $minimal) {
      throw new \LogicException(sprintf('Extraneous JSON Schema information detected: %s should have been just %s.', json_encode($sdc_prop_source['jsonSchema'], JSON_PRETTY_PRINT), json_encode($minimal, JSON_PRETTY_PRINT)));
    }

    return new self(
      $sdc_prop_source['value'],
      $sdc_prop_source['jsonSchema'],
      $sdc_prop_source['componentId'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(?FieldableEntityInterface $host_entity, bool $is_required): mixed {
    if (is_string($this->value)) {
      \assert(self::isUrlJsonSchema($this->jsonSchema));
      return $this->componentSource->rewriteExampleUrl($this->value);
    }

    return self::recurse($this->jsonSchema, $this->value, $this->componentSource);
  }

  private static function recurse(array $json_schema, mixed $value, UrlRewriteInterface $component_source): mixed {
    if ($json_schema['type'] === 'array') {
      assert(array_is_list($value));
      $evaluated = [];
      foreach ($value as $k => $v) {
        $evaluated[$k] = self::recurse($json_schema['items'], $v, $component_source);
      }
      return $evaluated;
    }
    elseif ($json_schema['type'] === 'object') {
      assert(!array_is_list($value));
      $evaluated = [];
      foreach ($value as $k => $v) {
        $evaluated[$k] = self::recurse($json_schema['properties'][$k], $v, $component_source);
      }
      return $evaluated;
    }
    elseif (is_string($value) && self::isUrlJsonSchema($json_schema)) {
      return $component_source->rewriteExampleUrl($value);
    }
    else {
      return $value;
    }
  }

  private static function isUrlJsonSchema(array $property_definition): bool {
    if ($property_definition['type'] !== 'string') {
      return FALSE;
    }
    return in_array($property_definition['format'] ?? '', [
      JsonSchemaStringFormat::Uri->value,
      JsonSchemaStringFormat::UriReference->value,
      JsonSchemaStringFormat::Iri->value,
      JsonSchemaStringFormat::IriReference->value,
    ]);
  }

  public function asChoice(): string {
    throw new \LogicException();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $host_entity = NULL): array {
    assert($host_entity === NULL || $host_entity instanceof FieldableEntityInterface);
    // @phpstan-ignore-next-line
    $component_definition = \Drupal::entityTypeManager()->getDefinition(Component::ENTITY_TYPE_ID);
    assert($component_definition instanceof ConfigEntityTypeInterface);
    $component_prefix = $component_definition->getConfigPrefix();
    return ['config' => ["$component_prefix.$this->componentId"]];
  }

}
