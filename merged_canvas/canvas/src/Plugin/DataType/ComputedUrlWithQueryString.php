<?php

namespace Drupal\canvas\Plugin\DataType;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\Uri;
use Drupal\canvas\PropExpressions\StructuredData\Evaluator;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;

#[DataType(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup("URI template")
)]
class ComputedUrlWithQueryString extends Uri implements DependentPluginInterface {

  public const string PLUGIN_ID = 'computed_url_with_query_string';

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $field_item = $this->getParent();
    if (!$field_item instanceof FieldItemInterface) {
      throw new \LogicException('This data type must be used as a computed field property.');
    }

    // Gather instructions for this computed field property.
    $instructions = $this->getDataDefinition()->getSettings();
    if (!array_key_exists('url', $instructions)) {
      throw new \LogicException(sprintf("No `url` setting specified for %s.", $this->getName()));
    }
    if (!array_key_exists('query_parameters', $instructions)) {
      throw new \LogicException(sprintf("No `query_parameters` setting specified for %s.", $this->getName()));
    }
    $url_prop_expression = StructuredDataPropExpression::fromString($instructions['url']);
    assert($url_prop_expression instanceof ReferenceFieldTypePropExpression);

    // Compute the URL from the provided instructions.
    $url = Evaluator::evaluate($field_item, $url_prop_expression, is_required: TRUE);
    $url_components = UrlHelper::parse($url);
    foreach ($instructions['query_parameters'] as $query_parameter_name => $query_parameter_instruction) {
      $url_components['query'][$query_parameter_name] = Evaluator::evaluate(
        $field_item,
        StructuredDataPropExpression::fromString($query_parameter_instruction),
        is_required: TRUE,
      );
    }

    // Assemble it.
    $computed_url = sprintf("%s?%s",
      $url_components['path'],
      UrlHelper::buildQuery($url_components['query']),
    );
    if ($url_components['fragment'] !== '') {
      $computed_url .= '#' . $url_components['fragment'];
    }

    return $computed_url;
  }

  /**
   * {@inheritdoc}
   *
   * Conveys that this computed field property class depends on data not
   * contained in the host entity. Important for dependency tracking.
   */
  public function calculateDependencies(): array {
    assert($this->getParent() !== NULL);
    $field_item_list = $this->getParent()->getParent();
    assert($field_item_list instanceof FieldItemListInterface);
    $instructions = $this->getDataDefinition()->getSettings();
    assert(array_key_exists('url', $instructions) && is_string($instructions['url']));
    assert(array_key_exists('query_parameters', $instructions) && is_array($instructions['query_parameters']));

    // Calculate the dependencies for this computed field property, by
    // calculating the dependencies of all structured data prop expressions this
    // (see ::getValue()) uses.
    $url_prop_expression = StructuredDataPropExpression::fromString($instructions['url']);
    assert($url_prop_expression instanceof ReferenceFieldTypePropExpression);
    $dependencies = $url_prop_expression->calculateDependencies($field_item_list);
    foreach ($instructions['query_parameters'] as $query_parameter_instruction) {
      $dependencies = NestedArray::mergeDeep($dependencies, StructuredDataPropExpression::fromString($query_parameter_instruction)->calculateDependencies($field_item_list));
    }

    // Ignore the referencing entity type's field type if this is a field item
    // list on an entity: then the dependency would already be represented by
    // the config dependency on a `field.field.*` config entity.
    // For example, otherwise the `image` module would become an explicit
    // dependency, instead of just relying on the config dependency on
    // `field.field.media.image.field_media_image`.
    if ($field_item_list->getParent() !== NULL) {
      $referencer_dependencies = $url_prop_expression->referencer->calculateDependencies();
      $module_dependencies_to_omit = $referencer_dependencies['module'] ?? [];
      $dependencies['module'] = array_values(array_diff($dependencies['module'] ?? [], $module_dependencies_to_omit));
    }
    return $dependencies;
  }

}
