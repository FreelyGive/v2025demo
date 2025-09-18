<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\canvas\ShapeMatcher\FieldForComponentSuggester;
use Drupal\canvas\Plugin\Adapter\AdapterInterface;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;
use Drupal\Core\Plugin\Component;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;

/**
 * @coversClass \Drupal\canvas\ShapeMatcher\FieldForComponentSuggester
 * @group canvas
 */
class FieldForComponentSuggesterTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;

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
    'media',
    // The module providing realistic test SDCs.
    'canvas_test_sdc',
    // The module providing the sample SDC to test all JSON schema types.
    'sdc_test_all_props',
    'canvas_test_sdc',
    // All other core modules providing field types.
    'comment',
    'datetime',
    'datetime_range',
    'file',
    'image',
    'link',
    'options',
    'path',
    'telephone',
    'text',
    // Create sample configurable fields on the `node` entity type.
    'node',
    'field',
    // Modules that field type-providing modules depend on.
    'filter',
    'ckeditor5',
    'editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('canvas');
    $this->installEntitySchema('node');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    // Create a "Foo" node type.
    NodeType::create([
      'name' => 'Foo',
      'type' => 'foo',
    ])->save();
    // Create a "silly image" field on the "Foo" node type.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_silly_image',
      'type' => 'image',
      // This is the default, but being explicit is helpful in tests.
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_silly_image',
      'bundle' => 'foo',
      'required' => TRUE,
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_before_and_after',
      'type' => 'image',
      'cardinality' => 2,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_before_and_after',
      'bundle' => 'foo',
      'required' => TRUE,
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_screenshots',
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_screenshots',
      'bundle' => 'foo',
    ])->save();
    // Create a "event duration" field on the "Foo" node type.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_event_duration',
      'type' => 'daterange',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_event_duration',
      'bundle' => 'foo',
      'required' => TRUE,
    ])->save();
    // Create a "wall of text" field on the "Foo" node type.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wall_of_text',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_wall_of_text',
      'bundle' => 'foo',
      'required' => TRUE,
    ])->save();
  }

  /**
   * @param array<string, array{'required': bool, 'instances': array<string, string>, 'adapters': array<string, string>}> $expected
   *
   * @dataProvider provider
   */
  public function test(string $component_plugin_id, ?string $data_type_context, array $expected): void {
    $component = \Drupal::service(ComponentPluginManager::class)->find($component_plugin_id);
    assert($component instanceof Component);
    $suggestions = $this->container->get(FieldForComponentSuggester::class)
      ->suggest(
        $component_plugin_id,
        $component->metadata,
        $data_type_context ? EntityDataDefinition::createFromDataType($data_type_context) : NULL,
      );

    // All expectations that are present must be correct.
    foreach (array_keys($expected) as $prop_name) {
      $this->assertSame(
        $expected[$prop_name],
        [
          'required' => $suggestions[$prop_name]['required'],
          'instances' => array_map(fn (StructuredDataPropExpressionInterface $e): string => (string) $e, $suggestions[$prop_name]['instances']),
          'adapters' => array_map(fn (AdapterInterface $a): string => $a->getPluginId(), $suggestions[$prop_name]['adapters']),
        ],
        "Unexpected prop source suggestion for $prop_name"
      );
    }

    // Finally, the set of expectations must be complete.
    $this->assertSame(array_keys($expected), array_keys($suggestions));
  }

  public static function provider(): \Generator {
    yield 'the image component' => [
      'canvas_test_sdc:image',
      'entity:node:foo',
      [
        '⿲canvas_test_sdc:image␟image' => [
          'required' => TRUE,
          'instances' => [
            "Subset of this Foo's field_silly_image: src_with_alternate_widths, alt, width, height (4 of 7 props — absent: entity, title, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
          'adapters' => [
            'Apply image style' => 'image_apply_style',
            'Make relative image URL absolute' => 'image_url_rel_to_abs',
          ],
        ],
      ],
    ];

    yield 'the image component — free of context' => [
      'canvas_test_sdc:image',
      NULL,
      [
        '⿲canvas_test_sdc:image␟image' => [
          'required' => TRUE,
          'instances' => [],
          'adapters' => [
            'Apply image style' => 'image_apply_style',
            'Make relative image URL absolute' => 'image_url_rel_to_abs',
          ],
        ],
      ],
    ];

    // 💡 Demonstrate it is possible to reuse an Canvas-defined prop shape, add a
    // new computed property to a field type, and match that, too. (This
    // particular computed property happens to be added by Canvas itself, but any
    // module can follow this pattern.)
    yield 'the image-srcset-candidate-template-uri component' => [
      'canvas_test_sdc:image-srcset-candidate-template-uri',
      'entity:node:foo',
      [
        '⿲canvas_test_sdc:image-srcset-candidate-template-uri␟image' => [
          'required' => TRUE,
          'instances' => [
            "Subset of this Foo's field_silly_image: src_with_alternate_widths, alt, width, height (4 of 7 props — absent: entity, title, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
          'adapters' => [
            'Apply image style' => 'image_apply_style',
            'Make relative image URL absolute' => 'image_url_rel_to_abs',
          ],
        ],
        '⿲canvas_test_sdc:image-srcset-candidate-template-uri␟srcSetCandidateTemplate' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: srcset_candidate_uri_template (1 of 7 props — absent: entity, alt, title, width, height, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟srcset_candidate_uri_template',
          ],
          'adapters' => [],
        ],
      ],
    ];

    yield 'the "ALL PROPS" test component' => [
      'sdc_test_all_props:all-props',
      'entity:node:foo',
      [
        '⿲sdc_test_all_props:all-props␟test_bool_default_false' => [
          'required' => FALSE,
          'instances' => [
            "This Foo's Default translation" => 'ℹ︎␜entity:node:foo␝default_langcode␞␟value',
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝status␞␟value',
            "This Foo's Promoted to front page" => 'ℹ︎␜entity:node:foo␝promote␞␟value',
            "This Foo's Default revision" => 'ℹ︎␜entity:node:foo␝revision_default␞␟value',
            "Subset of this Foo's Revision user: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝status␞␟value',
            "This Foo's Published" => 'ℹ︎␜entity:node:foo␝status␞␟value',
            "This Foo's Sticky at top of lists" => 'ℹ︎␜entity:node:foo␝sticky␞␟value',
            "Subset of this Foo's Authored by: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝status␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_bool_default_true' => [
          'required' => FALSE,
          'instances' => [
            "This Foo's Default translation" => 'ℹ︎␜entity:node:foo␝default_langcode␞␟value',
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝status␞␟value',
            "This Foo's Promoted to front page" => 'ℹ︎␜entity:node:foo␝promote␞␟value',
            "This Foo's Default revision" => 'ℹ︎␜entity:node:foo␝revision_default␞␟value',
            "Subset of this Foo's Revision user: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝status␞␟value',
            "This Foo's Published" => 'ℹ︎␜entity:node:foo␝status␞␟value',
            "This Foo's Sticky at top of lists" => 'ℹ︎␜entity:node:foo␝sticky␞␟value',
            "Subset of this Foo's Authored by: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝status␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: alt (1 of 7 props — absent: entity, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟alt',
            "Subset of this Foo's field_silly_image: title (1 of 7 props — absent: entity, alt, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟title',
            "This Foo's Revision log message" => 'ℹ︎␜entity:node:foo␝revision_log␞␟value',
            "This Foo's Title" => 'ℹ︎␜entity:node:foo␝title␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_multiline' => [
          'required' => FALSE,
          'instances' => [
            "This Foo's Revision log message" => 'ℹ︎␜entity:node:foo␝revision_log␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_REQUIRED_string' => [
          'required' => TRUE,
          'instances' => [
            "This Foo's Title" => 'ℹ︎␜entity:node:foo␝title␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_enum' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_integer_enum' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_date_time' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_event_duration: end_value (1 of 2 props — absent: value)" => 'ℹ︎␜entity:node:foo␝field_event_duration␞␟end_value',
            "Subset of this Foo's field_event_duration: value (1 of 2 props — absent: end_value)" => 'ℹ︎␜entity:node:foo␝field_event_duration␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_date' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_event_duration: end_value (1 of 2 props — absent: value)" => 'ℹ︎␜entity:node:foo␝field_event_duration␞␟end_value',
            "Subset of this Foo's field_event_duration: value (1 of 2 props — absent: end_value)" => 'ℹ︎␜entity:node:foo␝field_event_duration␞␟value',
          ],
          'adapters' => [
            'UNIX timestamp to date' => 'unix_to_date',
          ],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_time' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_duration' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_email' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's Revision user: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝mail␞␟value',
            "Subset of this Foo's Authored by: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝mail␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_idn_email' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's Revision user: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝mail␞␟value',
            "Subset of this Foo's Authored by: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝mail␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_hostname' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_idn_hostname' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_ipv4' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_ipv6' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_uuid' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uuid␞␟value',
            "Subset of this Foo's Revision user: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝uuid␞␟value',
            "Subset of this Foo's Revision user: target_uuid (1 of 2 props — absent: entity)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟target_uuid',
            "Subset of this Foo's Authored by: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝uuid␞␟value',
            "Subset of this Foo's Authored by: target_uuid (1 of 2 props — absent: entity)" => 'ℹ︎␜entity:node:foo␝uid␞␟target_uuid',
            "This Foo's UUID" => 'ℹ︎␜entity:node:foo␝uuid␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_REQUIRED_string_format_uri' => [
          'required' => TRUE,
          'instances' => [
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            "Subset of this Foo's field_silly_image: src_with_alternate_widths (1 of 7 props — absent: entity, alt, title, width, height, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_uri' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            "Subset of this Foo's field_silly_image: src_with_alternate_widths (1 of 7 props — absent: entity, alt, title, width, height, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_uri_image' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟url',
            "Subset of this Foo's field_silly_image: src_with_alternate_widths (1 of 7 props — absent: entity, alt, title, width, height, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
          ],
          'adapters' => [
            'Extract image URL' => 'image_extract_url',
          ],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_uri_image_using_ref' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟url',
            "Subset of this Foo's field_silly_image: src_with_alternate_widths (1 of 7 props — absent: entity, alt, title, width, height, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
          ],
          'adapters' => [
            'Extract image URL' => 'image_extract_url',
          ],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_uri_reference' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            "Subset of this Foo's field_silly_image: src_with_alternate_widths (1 of 7 props — absent: entity, alt, title, width, height, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_iri' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            "Subset of this Foo's field_silly_image: src_with_alternate_widths (1 of 7 props — absent: entity, alt, title, width, height, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_iri_reference' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            "Subset of this Foo's field_silly_image: src_with_alternate_widths (1 of 7 props — absent: entity, alt, title, width, height, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟src_with_alternate_widths',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_uri_template' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_json_pointer' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_relative_json_pointer' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_format_regex' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_integer' => [
          'required' => FALSE,
          'instances' => [
            "This Foo's Changed" => 'ℹ︎␜entity:node:foo␝changed␞␟value',
            "This Foo's Authored on" => 'ℹ︎␜entity:node:foo␝created␞␟value',
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝filesize␞␟value',
            "Subset of this Foo's field_silly_image: height (1 of 7 props — absent: entity, alt, title, width, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟height',
            "Subset of this Foo's field_silly_image: width (1 of 7 props — absent: entity, alt, title, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟width',
            "This Foo's Revision create time" => 'ℹ︎␜entity:node:foo␝revision_timestamp␞␟value',
            "Subset of this Foo's Revision user: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝login␞␟value',
            "Subset of this Foo's Authored by: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝login␞␟value',
          ],
          'adapters' => [
            'Count days' => 'day_count',
          ],
        ],
        '⿲sdc_test_all_props:all-props␟test_integer_range_minimum' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_integer_range_minimum_maximum_timestamps' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's Revision user: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝login␞␟value',
            "Subset of this Foo's Authored by: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝login␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_number' => [
          'required' => FALSE,
          'instances' => [
            "This Foo's Changed" => 'ℹ︎␜entity:node:foo␝changed␞␟value',
            "This Foo's Authored on" => 'ℹ︎␜entity:node:foo␝created␞␟value',
            "Subset of this Foo's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟entity␜␜entity:file␝filesize␞␟value',
            "Subset of this Foo's field_silly_image: height (1 of 7 props — absent: entity, alt, title, width, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟height',
            "Subset of this Foo's field_silly_image: width (1 of 7 props — absent: entity, alt, title, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟width',
            "This Foo's Revision create time" => 'ℹ︎␜entity:node:foo␝revision_timestamp␞␟value',
            "Subset of this Foo's Revision user: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝revision_uid␞␟entity␜␜entity:user␝login␞␟value',
            "Subset of this Foo's Authored by: entity (1 of 2 props — absent: target_uuid)" => 'ℹ︎␜entity:node:foo␝uid␞␟entity␜␜entity:user␝login␞␟value',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_object_drupal_image' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_silly_image: src_with_alternate_widths, alt, width, height (4 of 7 props — absent: entity, title, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_silly_image␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
          'adapters' => [
            'Apply image style' => 'image_apply_style',
            'Make relative image URL absolute' => 'image_url_rel_to_abs',
          ],
        ],
        '⿲sdc_test_all_props:all-props␟test_object_drupal_image_ARRAY' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_before_and_after: src_with_alternate_widths, alt, width, height (4 of 7 props — absent: entity, title, srcset_candidate_uri_template)" => 'ℹ︎␜entity:node:foo␝field_before_and_after␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_object_drupal_video' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_object_drupal_date_range' => [
          'required' => FALSE,
          'instances' => [
            "This Foo's field_event_duration" => 'ℹ︎␜entity:node:foo␝field_event_duration␞␟{from↠value,to↠end_value}',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_html_inline' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_html_block' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_wall_of_text: processed (1 of 3 props — absent: value, format)" => 'ℹ︎␜entity:node:foo␝field_wall_of_text␞␟processed',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_string_html' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_wall_of_text: processed (1 of 3 props — absent: value, format)" => 'ℹ︎␜entity:node:foo␝field_wall_of_text␞␟processed',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_REQUIRED_string_html_inline' => [
          'required' => TRUE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_REQUIRED_string_html_block' => [
          'required' => TRUE,
          'instances' => [
            "Subset of this Foo's field_wall_of_text: processed (1 of 3 props — absent: value, format)" => 'ℹ︎␜entity:node:foo␝field_wall_of_text␞␟processed',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_REQUIRED_string_html' => [
          'required' => TRUE,
          'instances' => [
            "Subset of this Foo's field_wall_of_text: processed (1 of 3 props — absent: value, format)" => 'ℹ︎␜entity:node:foo␝field_wall_of_text␞␟processed',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_array_integer' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_screenshots: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_screenshots␞␟entity␜␜entity:file␝filesize␞␟value',
            "Subset of this Foo's field_screenshots: height (1 of 7 props — absent: entity, alt, title, width, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_screenshots␞␟height',
            "Subset of this Foo's field_screenshots: width (1 of 7 props — absent: entity, alt, title, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_screenshots␞␟width',
          ],
          'adapters' => [],
        ],

        '⿲sdc_test_all_props:all-props␟test_array_integer_minItems' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_array_integer_maxItems' => [
          'required' => FALSE,
          'instances' => [
            "Subset of this Foo's field_before_and_after: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_before_and_after␞␟entity␜␜entity:file␝filesize␞␟value',
            "Subset of this Foo's field_before_and_after: height (1 of 7 props — absent: entity, alt, title, width, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_before_and_after␞␟height',
            "Subset of this Foo's field_before_and_after: width (1 of 7 props — absent: entity, alt, title, height, srcset_candidate_uri_template, src_with_alternate_widths)" => 'ℹ︎␜entity:node:foo␝field_before_and_after␞␟width',
          ],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_array_integer_minItemsMultiple' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
        '⿲sdc_test_all_props:all-props␟test_array_integer_minMaxItems' => [
          'required' => FALSE,
          'instances' => [],
          'adapters' => [],
        ],
      ],
    ];
  }

}
