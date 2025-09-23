<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Component as ComponentPlugin;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\JsonSchemaInterpreter\JsonSchemaStringFormat;
use Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent;
use Drupal\canvas\PropExpressions\Component\ComponentPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropShape\PropShape;
use Drupal\canvas\PropShape\StorablePropShape;
use Drupal\canvas\PropSource\StaticPropSource;
use Drupal\canvas\ShapeMatcher\JsonSchemaFieldInstanceMatcher;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\VfsPublicStreamUrlTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\user\Entity\User;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * @group canvas
 */
class PropShapeRepositoryTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use VfsPublicStreamUrlTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The two only modules Drupal truly requires.
    'system',
    'user',
    // The module being tested.
    'canvas',
    // The dependent modules.
    'sdc',
    // Modules providing additional SDCs.
    'sdc_test',
    'sdc_test_all_props',
    'canvas_test_sdc',
    // Modules providing field types and widgets that the PropShapes are using.
    'ckeditor5',
    'datetime',
    'editor',
    'image',
    'file',
    'filter',
    'link',
    'media',
    'options',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['stark']);
    // @see core/modules/system/config/install/core.date_format.html_date.yml
    // @see core/modules/system/config/install/core.date_format.html_datetime.yml
    // @see \Drupal\datetime\Plugin\Field\FieldWidget\DateTimeDefaultWidget::formElement()
    $this->installConfig(['system']);
    // @see config/install/image.style.canvas_parametrized_width.yml
    $this->installConfig(['canvas']);
    // @see \Drupal\file\Plugin\Field\FieldType\FileItem::generateSampleValue()
    $this->installEntitySchema('file');
  }

  /**
   * Tests finding all unique prop schemas.
   */
  public function testUniquePropSchemaDiscovery(): array {
    $sdc_manager = \Drupal::service('plugin.manager.sdc');
    $matcher = \Drupal::service(JsonSchemaFieldInstanceMatcher::class);
    assert($matcher instanceof JsonSchemaFieldInstanceMatcher);

    $components = $sdc_manager->getAllComponents();
    // Shape matching is only ever relevant to SDCs that may appear in the UI,
    // and hence also in Canvas. Omit SDCs with `noUi: true`.
    $components = array_filter(
      $components,
      fn (ComponentPlugin $c) => (property_exists($c->metadata, 'noUi') && $c->metadata->noUi === FALSE)
        // The above only works on Drupal core >=11.3.
        // @todo Remove in https://www.drupal.org/i/3537695
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        || ($c->getPluginDefinition()['noUi'] ?? FALSE) === FALSE,
    );
    $unique_prop_shapes = [];
    foreach ($components as $component) {
      foreach (GeneratedFieldExplicitInputUxComponentSourceBase::getComponentInputsForMetadata($component->getPluginId(), $component->metadata) as $prop_shape) {
        // A `type: object` without `properties` and without `$ref` does not
        // make sense.
        if ($prop_shape->schema['type'] === 'object' && !array_key_exists('$ref', $prop_shape->schema) && empty($prop_shape->schema['properties'] ?? [])) {
          // @see core/modules/system/tests/modules/sdc_test/components/array-to-object/array-to-object.component.yml
          // @see tests/modules/canvas_test_sdc/components/props-invalid-shapes/props-invalid-shapes.component.yml
          assert($component->getPluginId() === 'sdc_test:array-to-object' || $component->getPluginId() === 'canvas_test_sdc:props-invalid-shapes');
          continue;
        }

        $unique_prop_shapes[$prop_shape->uniquePropSchemaKey()] = $prop_shape;
      }
    }
    ksort($unique_prop_shapes);
    $unique_prop_shapes = array_values($unique_prop_shapes);
    $this->assertEquals([
      new PropShape(['type' => 'array', 'items' => ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image']]),
      new PropShape(['type' => 'array', 'items' => ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image'], 'maxItems' => 2]),
      new PropShape(['type' => 'array', 'items' => ['type' => 'integer']]),
      new PropShape(['type' => 'array', 'items' => ['type' => 'integer', 'maximum' => 100, 'minimum' => -100], 'maxItems' => 100]),
      new PropShape(['type' => 'array', 'items' => ['type' => 'integer', 'maximum' => 100, 'minimum' => -100], 'maxItems' => 100, 'minItems' => 2]),
      new PropShape(['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 2]),
      new PropShape(['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 20, 'minItems' => 1]),
      new PropShape(['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1]),
      new PropShape(['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 2]),
      new PropShape(['type' => 'boolean']),
      new PropShape(['type' => 'integer']),
      new PropShape(['type' => 'integer', '$ref' => 'json-schema-definitions://canvas.module/column-width']),
      new PropShape(['type' => 'integer', 'enum' => [1, 2]]),
      new PropShape(['type' => 'integer', 'maximum' => 2147483648, 'minimum' => -2147483648]),
      new PropShape(['type' => 'integer', 'minimum' => 0]),
      new PropShape(['type' => 'integer', 'minimum' => 1]),
      new PropShape(['type' => 'number']),
      new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image']),
      new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/shoe-icon']),
      new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/video']),
      new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://sdc_test_all_props.module/date-range']),
      new PropShape(['type' => 'string']),
      new PropShape(['type' => 'string', '$ref' => 'json-schema-definitions://canvas.module/heading-element']),
      new PropShape(['type' => 'string', '$ref' => 'json-schema-definitions://canvas.module/image-uri']),
      new PropShape(['type' => 'string', '$ref' => 'json-schema-definitions://canvas.module/stream-wrapper-image-uri']),
      new PropShape(['type' => 'string', '$ref' => 'json-schema-definitions://canvas.module/textarea']),
      new PropShape(['type' => 'string', 'contentMediaType' => 'image/*', 'format' => 'uri-reference', 'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$']),
      new PropShape(['type' => 'string', 'contentMediaType' => 'text/html']),
      new PropShape(['type' => 'string', 'contentMediaType' => 'text/html', 'x-formatting-context' => 'block']),
      new PropShape(['type' => 'string', 'contentMediaType' => 'text/html', 'x-formatting-context' => 'inline']),
      new PropShape(['type' => 'string', 'contentMediaType' => 'text/html', 'x-formatting-context' => 'invalid']),
      new PropShape(['type' => 'string', 'enum' => ['', '_blank']]),
      new PropShape(['type' => 'string', 'enum' => ['', 'base', 'l', 's', 'xs', 'xxs']]),
      new PropShape(['type' => 'string', 'enum' => ['', 'dog', 'cat', 'fish', 'rabbit']]),
      new PropShape(['type' => 'string', 'enum' => ['', 'gray', 'primary', 'neutral-soft', 'neutral-medium', 'neutral-loud', 'primary-medium', 'primary-loud', 'black', 'white', 'red', 'gold', 'green']]),
      new PropShape(['type' => 'string', 'enum' => ['7', '3.14']]),
      new PropShape(['type' => 'string', 'enum' => ['_blank', '_parent', '_self', '_top']]),
      new PropShape(['type' => 'string', 'enum' => ['_self', '_blank']]),
      new PropShape(['type' => 'string', 'enum' => ['auto', 'manual']]),
      new PropShape(['type' => 'string', 'enum' => ['default', 'primary', 'success', 'neutral', 'warning', 'danger', 'text']]),
      new PropShape(['type' => 'string', 'enum' => ['foo', 'bar']]),
      new PropShape(['type' => 'string', 'enum' => ['full', 'wide', 'normal', 'narrow']]),
      new PropShape(['type' => 'string', 'enum' => ['horizontal', 'vertical']]),
      new PropShape(['type' => 'string', 'enum' => ['lazy', 'eager']]),
      new PropShape(['type' => 'string', 'enum' => ['moon-stars-fill', 'moon-stars', 'star-fill', 'star', 'stars', 'rocket-fill', 'rocket-takeoff-fill', 'rocket-takeoff', 'rocket']]),
      new PropShape(['type' => 'string', 'enum' => ['power', 'like', 'external']]),
      new PropShape(['type' => 'string', 'enum' => ['prefix', 'suffix']]),
      new PropShape(['type' => 'string', 'enum' => ['primary', 'secondary']]),
      new PropShape(['type' => 'string', 'enum' => ['primary', 'secondary', 'tertiary']]),
      new PropShape(['type' => 'string', 'enum' => ['primary', 'success', 'neutral', 'warning', 'danger']]),
      new PropShape(['type' => 'string', 'enum' => ['small', 'big', 'huge']]),
      new PropShape(['type' => 'string', 'enum' => ['small', 'big', 'huge', 'contains.dots']]),
      new PropShape(['type' => 'string', 'enum' => ['small', 'medium', 'large']]),
      new PropShape(['type' => 'string', 'enum' => ['top', 'bottom', 'start', 'end']]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Date->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::DateTime->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Duration->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Email->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Hostname->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::IdnEmail->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::IdnHostname->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Ipv4->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Ipv6->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Iri->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::IriReference->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::JsonPointer->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Regex->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::RelativeJsonPointer->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Time->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Uri->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::UriReference->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::UriTemplate->value]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::UriTemplate->value, 'x-required-variables' => ['width']]),
      new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Uuid->value]),
      new PropShape(['type' => 'string', 'minLength' => 2]),
    ], $unique_prop_shapes);

    return $unique_prop_shapes;
  }

  /**
   * @return \Drupal\canvas\PropShape\StorablePropShape[]
   */
  public static function getExpectedStorablePropShapes(): array {
    return [
      'type=boolean' => new StorablePropShape(
        shape: new PropShape(['type' => 'boolean']),
        fieldTypeProp: new FieldTypePropExpression('boolean', 'value'),
        fieldWidget: 'boolean_checkbox',
      ),
      'type=integer' => new StorablePropShape(
        shape: new PropShape(['type' => 'integer']),
        fieldTypeProp: new FieldTypePropExpression('integer', 'value'),
        fieldWidget: 'number',
      ),
      'type=integer&$ref=json-schema-definitions://canvas.module/column-width' => new StorablePropShape(
        shape: new PropShape(['type' => 'integer', 'enum' => [25, 33, 50, 66, 75]]),
        fieldTypeProp: new FieldTypePropExpression('list_integer', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=integer&maximum=2147483648&minimum=-2147483648' => new StorablePropShape(
        shape: new PropShape(['type' => 'integer', 'maximum' => 2147483648, 'minimum' => -2147483648]),
        fieldTypeProp: new FieldTypePropExpression('integer', 'value'),
        fieldWidget: 'number',
        fieldInstanceSettings: ['min' => -2147483648, 'max' => 2147483648],
      ),
      'type=integer&minimum=0' => new StorablePropShape(
        shape: new PropShape(['type' => 'integer', 'minimum' => 0]),
        fieldTypeProp: new FieldTypePropExpression('integer', 'value'),
        fieldWidget: 'number',
        fieldInstanceSettings: ['min' => 0, 'max' => ''],
      ),
      'type=integer&minimum=1' => new StorablePropShape(
        shape: new PropShape(['type' => 'integer', 'minimum' => 1]),
        fieldTypeProp: new FieldTypePropExpression('integer', 'value'),
        fieldWidget: 'number',
        fieldInstanceSettings: ['max' => '', 'min' => 1],
      ),
      'type=number' => new StorablePropShape(
        shape: new PropShape(['type' => 'number']),
        fieldTypeProp: new FieldTypePropExpression('float', 'value'),
        fieldWidget: 'number',
      ),
      'type=string' => new StorablePropShape(
        shape: new PropShape(['type' => 'string']),
        fieldTypeProp: new FieldTypePropExpression('string', 'value'),
        fieldWidget: 'string_textfield',
      ),
      // ⚠️Identical to the below, because its `$ref` resolves to this.
      'type=string&contentMediaType=image/*&format=uri-reference&pattern=^(/|https?://)?(?!.*\://)[^\s]+$' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'contentMediaType' => 'image/*', 'format' => 'uri-reference', 'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$']),
        fieldTypeProp: new FieldTypePropExpression('image', 'src_with_alternate_widths'),
        fieldWidget: 'image_image',
      ),
      // ⚠️Identical to the above, because `$ref` resolves to the above.
      'type=string&$ref=json-schema-definitions://canvas.module/image-uri' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'contentMediaType' => 'image/*', 'format' => 'uri-reference', 'pattern' => '^(/|https?://)?(?!.*\://)[^\s]+$']),
        fieldTypeProp: new FieldTypePropExpression('image', 'src_with_alternate_widths'),
        fieldWidget: 'image_image',
      ),
      'type=string&$ref=json-schema-definitions://canvas.module/stream-wrapper-image-uri' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'contentMediaType' => 'image/*', 'format' => 'uri', 'pattern' => '^(?!https?://)[\w\-]+://']),
        fieldTypeProp: new ReferenceFieldTypePropExpression(
          referencer: new FieldTypePropExpression('image', 'entity'),
          referenced: new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'value'),
        ),
        fieldWidget: 'image_image',
      ),
      'type=object&$ref=json-schema-definitions://canvas.module/image' => new StorablePropShape(
        shape: new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image']),
        fieldTypeProp: new FieldTypeObjectPropsExpression('image', [
          'src' => new FieldTypePropExpression('image', 'src_with_alternate_widths'),
          'alt' => new FieldTypePropExpression('image', 'alt'),
          'width' => new FieldTypePropExpression('image', 'width'),
          'height' => new FieldTypePropExpression('image', 'height'),
        ]),
        fieldWidget: 'image_image',
      ),
      'type=object&$ref=json-schema-definitions://canvas.module/video' => new StorablePropShape(
        new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/video']),
        fieldTypeProp: new FieldTypeObjectPropsExpression('file', [
          'src' => new ReferenceFieldTypePropExpression(
            new FieldTypePropExpression('file', 'entity'),
            new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'url'),
          ),
        ]),
        fieldInstanceSettings: ['file_extensions' => 'mp4'],
        fieldWidget: 'file_generic',
      ),
      'type=string&$ref=json-schema-definitions://canvas.module/heading-element' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'enum' => ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6']]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=foo&enum[1]=bar' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['foo', 'bar'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=_blank&enum[1]=_parent&enum[2]=_self&enum[3]=_top' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['_blank', '_parent', '_self', '_top'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=auto&enum[1]=manual' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['auto', 'manual'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=default&enum[1]=primary&enum[2]=success&enum[3]=neutral&enum[4]=warning&enum[5]=danger&enum[6]=text' => new StorablePropShape(
        new PropShape([
          'type' => 'string',
          'enum' => ['default', 'primary', 'success', 'neutral', 'warning', 'danger', 'text'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=full&enum[1]=wide&enum[2]=normal&enum[3]=narrow' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['full', 'wide', 'normal', 'narrow'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=lazy&enum[1]=eager' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['lazy', 'eager'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=moon-stars-fill&enum[1]=moon-stars&enum[2]=star-fill&enum[3]=star&enum[4]=stars&enum[5]=rocket-fill&enum[6]=rocket-takeoff-fill&enum[7]=rocket-takeoff&enum[8]=rocket' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['moon-stars-fill', 'moon-stars', 'star-fill', 'star', 'stars', 'rocket-fill', 'rocket-takeoff-fill', 'rocket-takeoff', 'rocket'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=prefix&enum[1]=suffix' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['prefix', 'suffix'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=primary&enum[1]=secondary' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['primary', 'secondary'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=primary&enum[1]=success&enum[2]=neutral&enum[3]=warning&enum[4]=danger' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['primary', 'success', 'neutral', 'warning', 'danger'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=primary&enum[1]=secondary&enum[2]=tertiary' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['primary', 'secondary', 'tertiary'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=small&enum[1]=medium&enum[2]=large' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['small', 'medium', 'large'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=top&enum[1]=bottom&enum[2]=start&enum[3]=end' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['top', 'bottom', 'start', 'end'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=power&enum[1]=like&enum[2]=external' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'string',
          'enum' => ['power', 'like', 'external'],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&format=uri' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'format' => 'uri']),
        fieldTypeProp: new FieldTypePropExpression('link', 'url'),
        fieldInstanceSettings: ['title' => DRUPAL_DISABLED],
        fieldWidget: 'link_default',
      ),
      'type=string&minLength=2' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'minLength' => 2]),
        fieldTypeProp: new FieldTypePropExpression('string', 'value'),
        fieldWidget: 'string_textfield',
      ),
      'type=string&format=date' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Date->value]),
        fieldTypeProp: new FieldTypePropExpression('datetime', 'value'),
        fieldWidget: 'datetime_default',
        fieldStorageSettings: [
          'datetime_type' => DateTimeItem::DATETIME_TYPE_DATE,
        ],
      ),
      'type=string&format=date-time' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::DateTime->value]),
        fieldTypeProp: new FieldTypePropExpression('datetime', 'value'),
        fieldWidget: 'datetime_default',
        fieldStorageSettings: [
          'datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME,
        ],
      ),
      'type=string&format=email' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Email->value]),
        fieldTypeProp: new FieldTypePropExpression('email', 'value'),
        fieldWidget: 'email_default',
      ),
      'type=string&format=idn-email' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::IdnEmail->value]),
        fieldTypeProp: new FieldTypePropExpression('email', 'value'),
        fieldWidget: 'email_default',
      ),
      'type=string&format=iri' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Iri->value]),
        fieldTypeProp: new FieldTypePropExpression('link', 'url'),
        fieldInstanceSettings: ['title' => DRUPAL_DISABLED],
        fieldWidget: 'link_default',
      ),
      'type=string&format=iri-reference' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::IriReference->value]),
        fieldTypeProp: new FieldTypePropExpression('link', 'url'),
        fieldInstanceSettings: ['title' => DRUPAL_DISABLED],
        fieldWidget: 'link_default',
      ),
      'type=string&format=uri-reference' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::UriReference->value]),
        fieldTypeProp: new FieldTypePropExpression('link', 'url'),
        fieldInstanceSettings: ['title' => DRUPAL_DISABLED],
        fieldWidget: 'link_default',
      ),
      'type=string&$ref=json-schema-definitions://canvas.module/textarea' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', '$ref' => 'json-schema-definitions://canvas.module/textarea']),
        fieldTypeProp: new FieldTypePropExpression('string_long', 'value'),
        fieldWidget: 'string_textarea',
      ),
      'type=string&contentMediaType=text/html' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'contentMediaType' => 'text/html']),
        fieldTypeProp: new FieldTypePropExpression('text_long', 'value'),
        fieldWidget: 'text_textarea',
        fieldInstanceSettings: [
          'allowed_formats' => [
            'canvas_html_block',
          ],
        ],
      ),
      'type=string&contentMediaType=text/html&x-formatting-context=block' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'contentMediaType' => 'text/html', 'x-formatting-context' => 'block']),
        fieldTypeProp: new FieldTypePropExpression('text_long', 'value'),
        fieldWidget: 'text_textarea',
        fieldInstanceSettings: [
          'allowed_formats' => [
            'canvas_html_block',
          ],
        ],
      ),
      'type=string&contentMediaType=text/html&x-formatting-context=inline' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'contentMediaType' => 'text/html', 'x-formatting-context' => 'inline']),
        fieldTypeProp: new FieldTypePropExpression('text', 'value'),
        fieldWidget: 'text_textfield',
        fieldInstanceSettings: [
          'allowed_formats' => [
            'canvas_html_inline',
          ],
        ],
      ),
      'type=integer&enum[0]=1&enum[1]=2' => new StorablePropShape(
        shape: new PropShape([
          'type' => 'integer',
          'enum' => [1, 2],
        ]),
        fieldTypeProp: new FieldTypePropExpression('list_integer', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=array&items[type]=integer' => new StorablePropShape(
        shape: new PropShape(['type' => 'array', 'items' => ['type' => 'integer']]),
        fieldTypeProp: new FieldTypePropExpression('integer', 'value'),
        cardinality: FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        fieldWidget: 'number',
      ),
      'type=array&items[type]=integer&maxItems=2' => new StorablePropShape(
        shape: new PropShape(['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 2]),
        fieldTypeProp: new FieldTypePropExpression('integer', 'value'),
        cardinality: 2,
        fieldWidget: 'number',
      ),
      'type=array&items[$ref]=json-schema-definitions://canvas.module/image&items[type]=object&maxItems=2' => new StorablePropShape(
        shape: new PropShape(['type' => 'array', 'items' => ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image'], 'maxItems' => 2]),
        fieldTypeProp: new FieldTypeObjectPropsExpression('image', [
          'src' => new FieldTypePropExpression('image', 'src_with_alternate_widths'),
          'alt' => new FieldTypePropExpression('image', 'alt'),
          'width' => new FieldTypePropExpression('image', 'width'),
          'height' => new FieldTypePropExpression('image', 'height'),
        ]),
        cardinality: 2,
        fieldWidget: 'image_image',
      ),
      'type=array&items[$ref]=json-schema-definitions://canvas.module/image&items[type]=object' => new StorablePropShape(
        shape: new PropShape(['type' => 'array', 'items' => ['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/image']]),
        fieldTypeProp: new FieldTypeObjectPropsExpression('image', [
          'src' => new FieldTypePropExpression('image', 'src_with_alternate_widths'),
          'alt' => new FieldTypePropExpression('image', 'alt'),
          'width' => new FieldTypePropExpression('image', 'width'),
          'height' => new FieldTypePropExpression('image', 'height'),
        ]),
        cardinality: FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        fieldWidget: 'image_image',
      ),
      'type=array&items[type]=integer&items[minimum]=-100&items[maximum]=100&maxItems=100' => new StorablePropShape(
        shape: new PropShape(['type' => 'array', 'items' => ['type' => 'integer', 'maximum' => 100, 'minimum' => -100], 'maxItems' => 100]),
        fieldTypeProp: new FieldTypePropExpression('integer', 'value'),
        cardinality: 100,
        fieldWidget: 'number',
        fieldStorageSettings: NULL,
        fieldInstanceSettings: [
          'min' => -100,
          'max' => 100,
        ],
      ),
      'type=string&enum[0]=7&enum[1]=3.14' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'enum' => ['7', '3.14']]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=_self&enum[1]=_blank' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'enum' => ['_self', '_blank']]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=horizontal&enum[1]=vertical' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'enum' => ['horizontal', 'vertical']]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=small&enum[1]=big&enum[2]=huge' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'enum' => ['small', 'big', 'huge']]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
      'type=string&enum[0]=small&enum[1]=big&enum[2]=huge&enum[3]=contains.dots' => new StorablePropShape(
        shape: new PropShape(['type' => 'string', 'enum' => ['small', 'big', 'huge', 'contains.dots']]),
        fieldTypeProp: new FieldTypePropExpression('list_string', 'value'),
        fieldWidget: 'options_select',
        fieldStorageSettings: [
          'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
        ],
      ),
    ];
  }

  /**
   * @return \Drupal\canvas\PropShape\PropShape[]
   */
  public static function getExpectedUnstorablePropShapes(): array {
    return [
      'type=array&items[type]=integer&maxItems=20&minItems=1' => new PropShape(['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 20, 'minItems' => 1]),
      'type=array&items[type]=integer&minItems=1' => new PropShape(['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 1]),
      'type=array&items[type]=integer&minItems=2' => new PropShape(['type' => 'array', 'items' => ['type' => 'integer'], 'minItems' => 2]),
      'type=object&$ref=json-schema-definitions://sdc_test_all_props.module/date-range' => new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://sdc_test_all_props.module/date-range']),
      'type=object&$ref=json-schema-definitions://canvas.module/shoe-icon' => new PropShape(['type' => 'object', '$ref' => 'json-schema-definitions://canvas.module/shoe-icon']),
      'type=string&format=duration' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Duration->value]),
      'type=string&format=hostname' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Hostname->value]),
      'type=string&format=idn-hostname' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::IdnHostname->value]),
      'type=string&format=ipv4' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Ipv4->value]),
      'type=string&format=ipv6' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Ipv6->value]),
      'type=string&format=json-pointer' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::JsonPointer->value]),
      'type=string&format=regex' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Regex->value]),
      'type=string&format=relative-json-pointer' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::RelativeJsonPointer->value]),
      'type=string&format=time' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Time->value]),
      'type=string&format=uri-template' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::UriTemplate->value]),
      'type=string&format=uuid' => new PropShape(['type' => 'string', 'format' => JsonSchemaStringFormat::Uuid->value]),
      // These can't be stored as they have empty values as enum values.
      'type=string&enum[0]=&enum[1]=_blank' => new PropShape([
        'type' => 'string',
        'enum' => ['', '_blank'],
      ]),
      'type=string&enum[0]=&enum[1]=base&enum[2]=l&enum[3]=s&enum[4]=xs&enum[5]=xxs' => new PropShape([
        'type' => 'string',
        'enum' => ['', 'base', 'l', 's', 'xs', 'xxs'],
      ]),
      'type=string&enum[0]=&enum[1]=gray&enum[2]=primary&enum[3]=neutral-soft&enum[4]=neutral-medium&enum[5]=neutral-loud&enum[6]=primary-medium&enum[7]=primary-loud&enum[8]=black&enum[9]=white&enum[10]=red&enum[11]=gold&enum[12]=green' => new PropShape([
        'type' => 'string',
        'enum' => [
          '',
          'gray',
          'primary',
          'neutral-soft',
          'neutral-medium',
          'neutral-loud',
          'primary-medium',
          'primary-loud',
          'black',
          'white',
          'red',
          'gold',
          'green',
        ],
      ]),
      'type=array&items[type]=integer&items[minimum]=-100&items[maximum]=100&maxItems=100&minItems=2' => new PropShape([
        'type' => 'array',
        'items' => ['type' => 'integer', 'maximum' => 100, 'minimum' => -100],
        'maxItems' => 100,
        'minItems' => 2,
      ]),
      'type=string&contentMediaType=text/html&x-formatting-context=invalid' => new PropShape([
        'type' => 'string',
        'contentMediaType' => 'text/html',
        'x-formatting-context' => 'invalid',
      ]),
      'type=string&enum[0]=&enum[1]=dog&enum[2]=cat&enum[3]=fish&enum[4]=rabbit' => new PropShape([
        'type' => 'string',
        'enum' => ['', 'dog', 'cat', 'fish', 'rabbit'],
      ]),
      'type=string&format=uri-template&x-required-variables[0]=width' => new PropShape([
        'type' => 'string',
        'format' => JsonSchemaStringFormat::UriTemplate->value,
        'x-required-variables' => ['width'],
      ]),
    ];
  }

  /**
   * @depends testUniquePropSchemaDiscovery
   */
  public function testStorablePropShapes(array $unique_prop_shapes): array {
    $this->assertNotEmpty($unique_prop_shapes);

    $unique_storable_prop_shapes = [];
    foreach ($unique_prop_shapes as $prop_shape) {
      assert($prop_shape instanceof PropShape);
      // If this prop shape is not storable, then fall back to the PropShape
      // object, to make it easy to assert which shapes are storable vs not.
      $unique_storable_prop_shapes[$prop_shape->uniquePropSchemaKey()] = $prop_shape->getStorage() ?? $prop_shape;
    }

    $unstorable_prop_shapes = array_filter($unique_storable_prop_shapes, fn ($s) => $s instanceof PropShape);
    $unique_storable_prop_shapes = array_filter($unique_storable_prop_shapes, fn ($s) => $s instanceof StorablePropShape);

    $this->assertEquals(static::getExpectedStorablePropShapes(), $unique_storable_prop_shapes);

    // ⚠️ No field type + widget yet for these! For some that is fine though.
    $this->assertEquals(static::getExpectedUnstorablePropShapes(), $unstorable_prop_shapes);

    return $unique_storable_prop_shapes;
  }

  /**
   * @depends testStorablePropShapes
   * @param \Drupal\canvas\PropShape\StorablePropShape[] $storable_prop_shapes
   */
  public function testPropShapesYieldWorkingStaticPropSources(array $storable_prop_shapes): void {
    $this->assertNotEmpty($storable_prop_shapes);

    // A StaticPropSource is never rendered in an abstract context; it's always
    // rendered for a concrete component's prop. So, this test should do the
    // same.
    // @see \Drupal\canvas\Form\ComponentInstanceForm
    $sdc_manager = \Drupal::service('plugin.manager.sdc');
    $components = $sdc_manager->getAllComponents();
    $some_sdc_prop_for_unique_prop_shape = [];
    foreach ($components as $component) {
      foreach (GeneratedFieldExplicitInputUxComponentSourceBase::getComponentInputsForMetadata($component->getPluginId(), $component->metadata) as $component_prop_expression => $prop_shape) {
        // First SDC prop with this unique shape wins — doesn't really matter.
        if (!array_key_exists($prop_shape->uniquePropSchemaKey(), $some_sdc_prop_for_unique_prop_shape)) {
          $sdc_prop = ComponentPropExpression::fromString($component_prop_expression);
          $component_id = SingleDirectoryComponent::convertMachineNameToId($sdc_prop->sourceSpecificComponentId);
          $some_sdc_prop_for_unique_prop_shape[$prop_shape->uniquePropSchemaKey()] = [
            $component_id,
            // Note: on the live site, an older version than the active version
            // may be used in the ComponentInstanceForm, because the Content
            // Author may be editing an ancient component instance. For the
            // purpose of this test, the active version is fine.
            Component::load($component_id)?->getActiveVersion(),
            $sdc_prop->propName,
          ];
        }
      }
    }

    foreach ($storable_prop_shapes as $key => $storable_prop_shape) {
      // A static prop source can be generated.
      $prop_source = $storable_prop_shape->toStaticPropSource();

      // A widget can be generated.
      [$component_id, $component_version, $prop_name] = $some_sdc_prop_for_unique_prop_shape[$key];
      $widget = $prop_source->getWidget($component_id, $component_version, $prop_name, $this->randomString(), $storable_prop_shape->fieldWidget);
      $this->assertSame($storable_prop_shape->fieldWidget, $widget->getPluginId());

      // A widget form can be generated.
      // @see \Drupal\Core\Entity\Entity\EntityFormDisplay::buildForm()
      // @see \Drupal\Core\Field\WidgetBase::form()
      $form = ['#parents' => [$this->randomMachineName()]];
      $form_state = new FormState();
      $form = $prop_source->formTemporaryRemoveThisExclamationExclamationExclamation($widget, 'some-prop-name', FALSE, User::create([]), $form, $form_state);

      // Finally, prove the total compatibility of the StaticPropSource
      // generated by the StorablePropShape:
      // - generate a random value using the field type
      // - store the StaticPropSource that contains this random value
      // - (this simulated the user entering a value)
      // - verify it is present after loading from storage
      // - finally: verify that evaluating the StaticPropSource returns the
      //   parts of the generated value using the stored expression in such a
      //   way that the SDC component validator reports no errors.
      $randomized_prop_source = $prop_source->randomizeValue();

      // Some core SDCs have enums without meta:enums, which we aren't
      // supporting. So instead of option_list we are getting a textfield.
      // So we would need to ignore those or just use one of the
      // valid values for now. This should not be needed after requiring 11.2.x
      // which will include https://drupal.org/i/3493070.
      if (isset($storable_prop_shape->shape->schema['enum'])) {
        $randomized_prop_source = $prop_source->withValue($storable_prop_shape->shape->schema['enum'][0]);
      }

      $random_value = $randomized_prop_source->getValue();
      $stored_randomized_prop_source = (string) $randomized_prop_source;
      $reloaded_randomized_prop_source = StaticPropSource::parse(json_decode($stored_randomized_prop_source, TRUE));
      $this->assertSame($random_value, $reloaded_randomized_prop_source->getValue());
      // @see \Drupal\Core\Theme\Component\ComponentValidator::validateProps()
      $some_prop_name = $this->randomMachineName();
      $schema = Validator::arrayToObjectRecursive([
        'type' => 'object',
        'required' => [$some_prop_name],
        'properties' => [$some_prop_name => $storable_prop_shape->shape->schema],
        'additionalProperties' => FALSE,
      ]);
      $props = Validator::arrayToObjectRecursive([$some_prop_name => $reloaded_randomized_prop_source->evaluate(NULL, is_required: TRUE)]);
      $validator = new Validator();
      $validator->validate($props, $schema, Constraint::CHECK_MODE_TYPE_CAST);
      $this->assertSame(
        [],
        $validator->getErrors(),
        sprintf("Sample value %s generated by field type %s for %s is invalid!",
          json_encode($random_value),
          $storable_prop_shape->fieldTypeProp instanceof ReferenceFieldTypePropExpression
            ? $storable_prop_shape->fieldTypeProp->referencer->fieldType
            : $storable_prop_shape->fieldTypeProp->fieldType,
          $storable_prop_shape->shape->uniquePropSchemaKey()
        )
      );
    }
  }

  /**
   * @depends testStorablePropShapes
   * @param \Drupal\canvas\PropShape\StorablePropShape[] $storable_prop_shapes
   *
   * @covers \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::fieldWidgetInfoAlter()
   */
  public function testAllWidgetsForPropShapesHaveTransforms(array $storable_prop_shapes): void {
    self::assertNotEmpty($storable_prop_shapes);
    $widget_manager = $this->container->get('plugin.manager.field.widget');
    \assert($widget_manager instanceof WidgetPluginManager);
    $definitions = $widget_manager->getDefinitions();
    foreach ($storable_prop_shapes as $storable_prop_shape) {
      // A static prop source can be generated.
      $storable_prop_shape->toStaticPropSource();

      $widget_plugin_id = $storable_prop_shape->fieldWidget;
      self::assertArrayHasKey($widget_plugin_id, $definitions);
      $definition = $definitions[$widget_plugin_id];
      self::assertArrayHasKey('canvas', $definition, \sprintf('Found transform for %s', $widget_plugin_id));
      self::assertArrayHasKey('transforms', $definition['canvas']);
    }
  }

}
