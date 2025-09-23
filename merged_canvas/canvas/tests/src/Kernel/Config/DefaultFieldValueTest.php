<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\SingleDirectoryComponentTreeTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;

/**
 * @group canvas
 */
class DefaultFieldValueTest extends KernelTestBase {

  use SingleDirectoryComponentTreeTestTrait;
  use ContribStrictConfigSchemaTestTrait;
  use GenerateComponentConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'block',
    'canvas',
    'system',
    'canvas_test_sdc',
    'canvas_test_config_node_article',
    // All of `canvas_test_config_node_article`'s dependencies.
    'node',
    'field',
    'link',
    'text',
    // Canvas's dependencies.
    'datetime',
    'file',
    'image',
    'options',
    'path',
    'media',
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
    $this->generateComponentConfig();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['canvas_test_config_node_article']);
  }

  public static function providerDefaultFieldValue(): array {
    $test_cases = static::getValidTreeTestCases();
    array_walk($test_cases, fn (array &$test_case) => array_push($test_case, NULL, NULL));
    $test_cases = array_merge($test_cases, static::getInvalidTreeTestCases());
    array_push(
      $test_cases['invalid UUID, missing component_id key'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.0] The array must contain a &quot;component_id&quot; key.',
    );
    array_push(
      $test_cases['invalid values using dynamic inputs'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.0] The &#039;dynamic&#039; prop source type must be absent.',
    );
    // Ensure the input validation is enforced even if the root is invalid.
    array_push(
      $test_cases['inputs invalid, using only static inputs'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.0.inputs.9145b0da-85a1-4ee7-ad1d-b1b63614aed6.heading] The property heading is required.'
    );
    array_push(
      $test_cases['missing inputs key'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.0] The array must contain an &quot;inputs&quot; key.',
    );
    // If dynamic prop sources are used the validation cannot be performed for the default value.
    array_push(
      $test_cases['missing components, using dynamic inputs'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.0.component_id] The &#039;canvas.component.sdc.sdc_test.missing&#039; config does not exist., 1 [default_value.1.component_id] The &#039;canvas.component.sdc.sdc_test.missing-also&#039; config does not exist., 2 [default_value.0] The &#039;dynamic&#039; prop source type must be absent., 3 [default_value.1] The &#039;dynamic&#039; prop source type must be absent., 4 [default_value.2] The &#039;dynamic&#039; prop source type must be absent.'
    );
    array_push(
      $test_cases['inputs invalid, using dynamic inputs'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.0] The &#039;dynamic&#039; prop source type must be absent.',
    );
    array_push(
      $test_cases['missing components, using only static inputs'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.0.component_id] The &#039;canvas.component.sdc.sdc_test.missing&#039; config does not exist.'
    );
    array_push(
      $test_cases['non unique uuids'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value] Not all component instance UUIDs in this component tree are unique.'
    );
    array_push(
      $test_cases['invalid parent'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.1.parent_uuid] Invalid component tree item with UUID &lt;em class=&quot;placeholder&quot;&gt;e303dd88-9409-4dc7-8a8b-a31602884a94&lt;/em&gt; references an invalid parent &lt;em class=&quot;placeholder&quot;&gt;6381352f-5b0a-4ca1-960d-a5505b37b27c&lt;/em&gt;.',
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [.default_value.1.parent_uuid] Invalid component tree item with UUID &lt;em class=&quot;placeholder&quot;&gt;e303dd88-9409-4dc7-8a8b-a31602884a94&lt;/em&gt; references an invalid parent &lt;em class=&quot;placeholder&quot;&gt;6381352f-5b0a-4ca1-960d-a5505b37b27c&lt;/em&gt;.'
    );
    array_push(
      $test_cases['invalid slot'],
      SchemaIncompleteException::class,
      'Schema errors for field.field.node.article.field_canvas_test with the following errors: 0 [default_value.1.slot] Invalid component subtree. This component subtree contains an invalid slot name for component &lt;em class=&quot;placeholder&quot;&gt;sdc.canvas_test_sdc.props-slots&lt;/em&gt;: &lt;em class=&quot;placeholder&quot;&gt;banana&lt;/em&gt;. Valid slot names are: &lt;em class=&quot;placeholder&quot;&gt;the_body, the_footer, the_colophon&lt;/em&gt;.'
    );
    return $test_cases;
  }

  /**
   * @coversClass \Drupal\canvas\Plugin\Validation\Constraint\ValidComponentTreeItemConstraintValidator
   * @dataProvider providerDefaultFieldValue
   */
  public function testDefaultFieldValue(array $field_values, ?string $expected_exception, ?string $expected_message): void {
    $field_config = FieldConfig::loadByName('node', 'article', 'field_canvas_test');
    $this->assertInstanceOf(FieldConfig::class, $field_config);

    $field_config->setDefaultValue($field_values);
    if ($expected_exception && $expected_message) {
      // @phpstan-ignore-next-line
      $this->expectException($expected_exception);
      $this->expectExceptionMessage($expected_message);
    }

    $field_config->save();
  }

}
