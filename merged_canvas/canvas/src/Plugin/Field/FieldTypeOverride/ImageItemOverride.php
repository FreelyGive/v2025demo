<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\canvas\Plugin\DataType\ComputedUrlWithQueryString;
use Drupal\canvas\Plugin\DataType\UriTemplate;
use Drupal\canvas\Plugin\Validation\Constraint\StringSemanticsConstraint;
use Drupal\canvas\Plugin\Validation\Constraint\UriTargetMediaTypeConstraint;
use Drupal\canvas\Plugin\Validation\Constraint\UriTemplateWithVariablesConstraint;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\canvas\TypedData\ImageDerivativeWithParametrizedWidth;

/**
 * @todo Fix upstream.
 */
class ImageItemOverride extends ImageItem {

  public const string ALT_WIDTHS_QUERY_PARAM = 'alternateWidths';

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    // @todo Remove once https://drupal.org/i/3513317 is fixed.
    return ['display_default' => TRUE] + parent::defaultStorageSettings();
  }

  public static function defaultFieldSettings() {
    // Add default support for AVIF.
    return ['file_extensions' => 'png gif jpg jpeg webp avif'] +
      parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['alt']->addConstraint('StringSemantics', StringSemanticsConstraint::PROSE);
    $properties['title']->addConstraint('StringSemantics', StringSemanticsConstraint::PROSE);
    // A computed URI template to populate `<img srcset>` using a parametrized
    // width.
    // @see https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/srcset#value
    // @see https://tools.ietf.org/html/rfc6570
    // @see \Drupal\canvas\TypedData\ImageDerivativeWithParametrizedWidth::getAllowedWidths()
    // @todo It's not sustainable nor ecosystem-friendly to add computed field properties to field types. Remove in favor of adapters in https://www.drupal.org/project/canvas/issues/3464003.
    // ⚠️ TRICKY: switching to adapters will require an update path for ALL component trees where this field property is being consumed.
    $properties['srcset_candidate_uri_template'] = DataDefinition::create(UriTemplate::PLUGIN_ID)
      ->setLabel(new TranslatableMarkup('srcset template'))
      ->setDescription(new TranslatableMarkup('Image candidate string URL template.'))
      ->setComputed(TRUE)
      ->setReadOnly(TRUE)
      ->addConstraint(UriTemplateWithVariablesConstraint::PLUGIN_ID, [
        'requiredVariables' => ['width'],
      ])
      ->setClass(ImageDerivativeWithParametrizedWidth::class);
    // A computed URL to provide an easier-to-use-or-ignore alternative to the
    // raw URI template above: appends to the URL provided by the referenced
    // File entity' `uri` field's `url` property an `?alternateWidths` query
    // parameter that contains an (encoded) URI template for a front-end
    // developer to use if they choose to do so.
    // @todo It's not sustainable nor ecosystem-friendly to add computed field properties to field types. Remove in favor of adapters in https://www.drupal.org/project/canvas/issues/3464003.
    // ⚠️ TRICKY: switching to adapters will require an update path for ALL component trees where this field property is being consumed.
    $properties['src_with_alternate_widths'] = DataDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Resolved image URL with ?alternateWidths query parameter'))
      ->setDescription(new TranslatableMarkup('Combines the referenced image file URL with the computed srcset template'))
      ->setComputed(TRUE)
      ->setReadOnly(TRUE)
      ->setRequired($properties['target_id']->isRequired())
      ->setSettings([
        'url' => (string) (new ReferenceFieldTypePropExpression(
          new FieldTypePropExpression('image', 'entity'),
          new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'url')
        )),
        'query_parameters' => [
          self::ALT_WIDTHS_QUERY_PARAM => (string) (new FieldTypePropExpression('image', 'srcset_candidate_uri_template')),
        ],
      ])
      ->addConstraint(UriTargetMediaTypeConstraint::PLUGIN_ID, ['mimeType' => 'image/*'])
      // The ComputedFileUrl data type generates a browser-accessible URL (root-
      // relative, absolute using HTTP, absolute using HTTPs or relative).
      // @see \Drupal\Tests\canvas\Unit\SchemaJsonPatternsTest::testImageUriPattern()
      ->addConstraint('Regex', ['pattern' => "/^(\/|https?:\/\/)?(?!.*\:\/\/)[^\s]+$/"])
      ->setClass(ComputedUrlWithQueryString::class);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(FieldDefinitionInterface $field_definition) {
    return NestedArray::mergeDeep(
      parent::calculateDependencies($field_definition),
      // @see \Drupal\canvas\TypedData\ImageDerivativeWithParametrizedWidth
      // @see config/install/image.style.canvas_parametrized_width.yml
      [
        'config' => [
          'image.style.canvas_parametrized_width',
        ],
      ],
    );
  }

}
