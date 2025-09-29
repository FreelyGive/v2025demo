<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

// cspell:ignore sofie componente extraño

use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\Tests\canvas\Traits\BetterConfigDependencyManagerTrait;

/**
 * Tests validation of JavaScriptComponent entities.
 *
 * @group canvas
 * @group JavaScriptComponents
 */
class JavaScriptComponentValidationTest extends BetterConfigEntityValidationTestBase {

  use BetterConfigDependencyManagerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    // Canvas's dependencies (the subset that is needed for these tests).
    'file',
    'image',
    'link',
    'options',
    'user',
  ];

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore property.defaultValue
   */
  protected static array $propertiesWithRequiredKeys = [
    'css' => [
      "'original' is a required key.",
      "'compiled' is a required key.",
    ],
    'js' => [
      "'original' is a required key.",
      "'compiled' is a required key.",
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $javascript_component_base = [
      'name' => 'Test',
      'status' => TRUE,
      'props' => [
        'text' => [
          'type' => 'string',
          'title' => 'Title',
          'examples' => ['Press', 'Submit now'],
        ],
      ],
      'slots' => [
        'test-slot' => [
          'title' => 'test',
          'description' => 'Title',
          'examples' => [
            'Test 1',
            'Test 2',
          ],
        ],
      ],
      'js' => [
        'original' => 'console.log("Test")',
        'compiled' => 'console.log("Test")',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test{display:none;}',
      ],
      'dataDependencies' => [],
    ];
    JavaScriptComponent::create([...$javascript_component_base, 'machineName' => 'other'])->save();
    $this->entity = JavaScriptComponent::create([
      ...$javascript_component_base,
      'machineName' => 'test',
      'dependencies' => [
        'enforced' => [
          'config' => [
            // @phpstan-ignore-next-line
            JavaScriptComponent::load('other')->getConfigDependencyName(),
          ],
        ],
      ],
    ]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function testEntityIsValid(): void {
    parent::testEntityIsValid();

    // Beyond validity, validate config dependencies are computed correctly.
    $this->assertSame(
      [
        'config' => [
          'canvas.js_component.other',
        ],
      ],
      $this->entity->getDependencies()
    );
    $this->assertSame([
      'config' => [
        'canvas.js_component.other',
      ],
      'module' => [
        'canvas',
      ],
    ], $this->getAllDependencies($this->entity));
  }

  /**
   * @testWith [true, true, []]
   *           [true, false, {"": "Prop \"silly\" is required, but does not have example value"}]
   *           [false, true, []]
   *           [false, false, []]
   */
  public function testPropExample(bool $required, bool $has_example, array $expected_validation_errors): void {
    $test_prop_definition = [
      'type' => 'boolean',
      'title' => $this->randomMachineName(),
      'examples' => [TRUE],
    ];
    if (!$has_example) {
      unset($test_prop_definition['examples']);
    }
    $this->entity
      ->set('required', $required ? ['silly'] : [])
      ->set('props', ['silly' => $test_prop_definition]);
    $this->assertValidationErrors($expected_validation_errors);
  }

  public static function providerValidEnumsAndExamples(): \Generator {
    yield 'string' => [
      "string",
      ["the answer", "Wim", "Sofie", "Jack"],
      ["the answer" => "the answer", "Wim" => "Wim", "Sofie" => "Sofie", "Jack" => "Jack"],
      NULL,
    ];
    yield 'integer' => ["integer", [42, 1988, 1992, 2024], ["42" => "42", "1988" => "1988", "1992" => "1992", "2024" => "2024"], NULL];
  }

  /**
   * @dataProvider providerValidEnumsAndExamples
   */
  public function testValidEnumsAndExamples(string $json_schema_type, array $enum_and_examples_both, array $meta_enum, ?array $expected_typecasting): void {
    $this->entity->set('props', [
      'tested_enum_prop' => [
        'type' => $json_schema_type,
        'title' => "enum: $json_schema_type",
        'enum' => $enum_and_examples_both,
        'meta:enum' => $meta_enum,
        'examples' => $enum_and_examples_both,
      ],
    ]);
    $this->assertValidationErrors([]);
    $this->entity->save();

    // The expected output (i.e. after saving) is the input. But in a few cases,
    // typecasting may occur. For readability, the third parameter is only
    // required for those cases.
    $expected = $expected_typecasting ?? $enum_and_examples_both;

    $this->assertSame($expected, $this->entity->get('props')['tested_enum_prop']['enum']);
    $this->assertSame($meta_enum, $this->entity->get('props')['tested_enum_prop']['meta:enum']);
    $this->assertSame($expected, $this->entity->get('props')['tested_enum_prop']['examples']);
  }

  /**
   * @dataProvider providerInvalidEnumsAndExamples
   */
  public function testInvalidEnumsAndExamples(string $json_schema_type, array $enum_and_examples_both, ?array $meta_enum, array $indexed_validation_errors, array $expected_validation_errors = []): void {
    $this->entity->set('props', [
      'tested_enum_prop' => array_merge([
        'type' => $json_schema_type,
        'title' => "enum: $json_schema_type",
        'enum' => $enum_and_examples_both,
        'examples' => $enum_and_examples_both,
      ], $meta_enum ? ['meta:enum' => $meta_enum] : []),
    ]);

    // The expected validation errors are keyed by the index whose value in the
    // $enum_and_examples_both array is expected to trigger a validation error.
    // This is then expanded to expect an explicit validation error for that
    // same index in both `enum` and `examples`, hence ensuring consistent
    // validation for both.
    foreach ($indexed_validation_errors as $index => $validation_error) {
      $expected_validation_errors["props.tested_enum_prop.enum.$index"] = $validation_error;
      $expected_validation_errors["props.tested_enum_prop.examples.$index"] = $validation_error;
    }
    if ($meta_enum) {
      $this->assertSame($meta_enum, $this->entity->get('props')['tested_enum_prop']['meta:enum']);
    }
    $this->assertValidationErrors($expected_validation_errors);
  }

  /**
   * @testWith ["missing", "The JavaScript component with the machine name 'missing' does not exist."]
   *           ["", "The 'importedJsComponents' contains an invalid component name."]
   *           ["🚀", "The 'importedJsComponents' contains an invalid component name."]
   *           ["componente_extraño", "The 'importedJsComponents' contains an invalid component name."]
   *           [";", "The 'importedJsComponents' contains an invalid component name."]
   */
  public function testNonExistingJsDependencies(string $component_id, string $expected_exception_message): void {
    \assert($this->entity instanceof JavaScriptComponent);
    $this->expectException(ConstraintViolationException::class);
    $this->expectExceptionMessage($expected_exception_message);

    \assert($this->entity instanceof JavaScriptComponent);
    $client_values = $this->entity->normalizeForClientSide()->values;
    $client_values['importedJsComponents'] = [$component_id];
    $this->entity->updateFromClientSide($client_values);
  }

  public static function providerInvalidEnumsAndExamples(): array {
    return [
      'Invalid string' => [
        'string',
        ['string', 42, 3.14, NULL],
        NULL,
        ['3' => 'This value should not be null.'],
        [
          '' => [
            // If not meta:enums are specified, they are generated, but number ones
            // with decimals will be invalid.
            'The "meta:enum" keys for the "tested_enum_prop" prop enum cannot contain a dot. Offending key: "3.14"',
            'The values for the "tested_enum_prop" prop enum must be defined in "meta:enum". Missing keys: "3_14"',
          ],
        ],
      ],
      'Invalid integer' => [
        'integer',
        ['string', 42, 3.14, NULL],
        NULL,
        [
          '0' => 'This value should be of the correct primitive type.',
          '2' => 'This value should be of the correct primitive type.',
          '3' => 'This value should not be null.',
        ],
        [
          '' => [
            'Prop "tested_enum_prop" has invalid example value: [] String value found, but an integer or an object is required',
            'The "meta:enum" keys for the "tested_enum_prop" prop enum cannot contain a dot. Offending key: "3.14"',
            'The values for the "tested_enum_prop" prop enum must be defined in "meta:enum". Missing keys: "3_14"',
          ],
        ],
      ],
      // ⚠️ For now, Canvas does not support `enum` on `type: number` to match core and for better usability.
      // @see https://www.drupal.org/project/canvas/issues/3534758
      'Number' => [
        'number',
        [3.14, 1.0],
        NULL,
        [],
        [
          '' => [
            'The "meta:enum" keys for the "tested_enum_prop" prop enum cannot contain a dot. Offending key: "3.14"',
            'The values for the "tested_enum_prop" prop enum must be defined in "meta:enum". Missing keys: "3_14"',
          ],
          'props.tested_enum_prop' => "'enum' is an unknown key because props.tested_enum_prop.type is number (see config schema type canvas.json_schema.prop.number).",
        ],
      ],
      'Invalid number' => [
        'number',
        ['string', 42, 3.14, NULL],
        NULL,
        [],
        [
          '' => [
            'Prop "tested_enum_prop" has invalid example value: [] String value found, but a number or an object is required',
            'The "meta:enum" keys for the "tested_enum_prop" prop enum cannot contain a dot. Offending key: "3.14"',
            'The values for the "tested_enum_prop" prop enum must be defined in "meta:enum". Missing keys: "3_14"',
          ],
          'props.tested_enum_prop' => "'enum' is an unknown key because props.tested_enum_prop.type is number (see config schema type canvas.json_schema.prop.number).",
          'props.tested_enum_prop.examples.0' => 'This value should be of the correct primitive type.',
          'props.tested_enum_prop.examples.3' => 'This value should not be null.',
        ],
      ],
    ];
  }

  /**
   * Tests `type: boolean` validation and edge cases.
   *
   * (Cannot be tested generically, like `string`, `integer` and `number`.)
   */
  public function testBooleanPropDefinition(): void {
    // Try using `enum` on a boolean prop.
    $this->entity->set('props', [
      'some_boolean' => [
        'type' => 'boolean',
        'title' => 'either/or',
        'enum' => [TRUE, FALSE],
        'examples' => [TRUE, NULL, FALSE],
      ],
    ]);
    $this->assertValidationErrors([
      'props.some_boolean' => "'enum' is an unknown key because props.some_boolean.type is boolean (see config schema type canvas.json_schema.prop.boolean).",
      'props.some_boolean.examples.1' => 'This value should not be null.',
    ]);
  }

  /**
   * Tests `type: string` `format: …` validation edge cases.
   *
   * @testWith [{"format": "uri-reference"}, "https://example.com", null]
   *           [{"format": "uri-reference"}, "ftp://example.com", null]
   *           [{"format": "uri-reference"}, "/node/1", null]
   *           [{"format": "uri-reference"}, "bunny.jpg", null]
   *           [{"format": "uri"}, "https://example.com", null]
   *           [{"format": "uri"}, "ftp://example.com", null]
   *           [{"format": "uri"}, "/node/1", "Invalid URL format"]
   *           [{"format": "uri"}, "bunny.jpg", "Invalid URL format"]
   *
   * @todo Expand this test coverage in https://www.drupal.org/project/canvas/issues/3542890 — this shows what is allowed by the two choices offered by the UI.
   */
  public function testStringFormatPropDefinition(array $string_definition, string $example, ?string $validation_error): void {
    $this->entity->set('props', [
      'beep' => [
        'type' => 'string',
        'title' => 'A meaningful title, but irrelevant in this test',
        ...$string_definition,
        'examples' => [$example],
      ],
    ]);
    $expected_validation_errors = is_null($validation_error)
      ? []
      : ['' => 'Prop "beep" has invalid example value: [] ' . $validation_error];
    $this->assertValidationErrors($expected_validation_errors);
  }

  /**
   * Tests `type: object` validation and edge cases.
   *
   * (Cannot be tested generically, like `string`, `integer` and `number`.)
   */
  public function testObjectPropDefinition(): void {
    $this->entity->set('props', [
      'some_object' => [
        'type' => 'object',
        '$ref' => 'json-schema-definitions://canvas.module/image',
        'title' => $this->randomString(),
        'enum' => [NULL],
        'meta:enum' => [NULL => 'Test'],
        'examples' => [
          [],
          NULL,
          [
            'src' => 'https://placehold.co/1200x900@2x.png',
            'width' => 1200,
            'height' => 900,
            'alt' => 'Example image placeholder',
          ],
          [
            // Only required props.
            'src' => 'https://placehold.co/1200x900@2x.png',
          ],
          [
            // Invalid pattern.
            'src' => 'hi mum, this is not a url',
          ],
          [
            // Missing required 'src'.
            'width' => 1200,
          ],
          [
            // Valid (although basically nonsensical) relative path.
            'src' => 'path/to/image.png',
          ],
          [
            // Valid root-relative URL.
            'src' => '/root/relative/path/to/image.png',
          ],
        ],
      ],
    ]);
    $this->assertValidationErrors([
      '' => 'Prop "some_object" has invalid example value: [src] The property src is required',
      'props.some_object.enum.0' => 'This value should not be null.',
      'props.some_object.examples.0' => [
        'This value should not be blank.',
        "'src' is a required key.",
      ],
      'props.some_object.examples.1' => 'This value should not be null.',
      'props.some_object.examples.4.src' => '<em class="placeholder">&quot;hi mum, this is not a url&quot;</em> does not match the pattern <em class="placeholder">@^(/|https?://)?(?!.*\://)[^\s]+$@</em>.',
      'props.some_object.examples.5' => "'src' is a required key.",
    ]);
  }

  /**
   * Tests different permutations of entity values.
   *
   * @param array $shape
   *   Array of entity values.
   * @param array $expected_errors
   *   Expected validation errors.
   *
   * @dataProvider providerTestEntityShapes
   */
  public function testEntityShapes(array $shape, array $expected_errors): void {
    $this->entity = JavaScriptComponent::create($shape);
    $this->assertValidationErrors($expected_errors);
  }

  public static function providerTestEntityShapes(): array {
    return [
      'Invalid: no JS' => [
        [
          'machineName' => 'test-no-slots-no-props',
          'name' => 'Test',
          'props' => [],
          'slots' => [],
          'js' => [
            'original' => NULL,
            'compiled' => NULL,
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [
          'js.compiled' => 'This value should not be null.',
          'js.original' => 'This value should not be null.',
        ],
      ],
      'Invalid: Unknown prop type' => [
        [
          'machineName' => 'test-unknown-prop-type',
          'name' => 'Test',
          'props' => [
            'mixed_up_prop' => [
              'type' => 'unknown',
              'title' => 'Title',
              'enum' => [
                'Press',
                'Click',
                'Submit',
              ],
              'meta:enum' => [
                'Press' => 'Press',
                'Click' => 'Click',
                'Submit' => 'Submit',
              ],
              'examples' => ['Press', 'Submit now'],
            ],
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [
          '' => 'Unable to find class/interface "unknown" specified in the prop "mixed_up_prop" for the component "canvas:test-unknown-prop-type".',
          'props.mixed_up_prop' => [
            "'enum' is an unknown key because props.mixed_up_prop.type is unknown (see config schema type canvas.json_schema.prop.*).",
            "'meta:enum' is an unknown key because props.mixed_up_prop.type is unknown (see config schema type canvas.json_schema.prop.*).",
          ],
          'props.mixed_up_prop.type' => 'The value you selected is not a valid choice.',
        ],
      ],
      'Valid: no props and no slots' => [
        [
          'machineName' => 'test-no-slots-no-props',
          'name' => 'Test',
          'props' => [],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
      'Valid: props (of all supported types), of which two required and no slots' => [
        [
          'machineName' => 'test-props-no-slots',
          'name' => 'Test',
          'props' => [
            'string' => [
              'type' => 'string',
              'title' => 'Title',
              'examples' => ['Press', 'Submit now'],
            ],
            'boolean' => [
              'type' => 'boolean',
              'title' => 'Truth',
              'examples' => [TRUE, FALSE],
            ],
            'integer' => [
              'type' => 'integer',
              'title' => 'Integer',
              'examples' => [23, 10, 2024],
            ],
            'number' => [
              'type' => 'number',
              'title' => 'Number',
              'examples' => [3.14],
            ],
          ],
          'required' => [
            'string',
            'integer',
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
      'Invalid: a non-existent required prop' => [
        [
          'machineName' => 'test-non-existent-required-prop',
          'name' => 'Test',
          'props' => [
            'string' => [
              'type' => 'string',
              'title' => 'Title',
              'examples' => ['Press', 'Submit now'],
            ],
          ],
          'required' => [
            'does_not_exist',
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [
          // ⚠️ SDC does not complain about this!
          // @see \Drupal\Core\Theme\Component\ComponentValidator
          // @todo Update once https://www.drupal.org/project/drupal/issues/3493086 is fixed.
        ],
      ],
      'Valid: props, no slots set' => [
        [
          'machineName' => 'test-props-no-slots',
          'name' => 'Test',
          'props' => [
            'text' => [
              'type' => 'string',
              'title' => 'Title',
              'examples' => ['Press', 'Submit now'],
            ],
          ],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
      'Valid: enum props' => [
        [
          'machineName' => 'test-props-no-slots',
          'name' => 'Test',
          'props' => [
            'text' => [
              'type' => 'string',
              'title' => 'Title',
              'enum' => [
                'Press',
                'Click',
                'Submit',
              ],
              'meta:enum' => [
                'Press' => 'Press',
                'Click' => 'Click',
                'Submit' => 'Submit',
              ],
              'examples' => ['Press', 'Submit'],
            ],
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
      'Valid: slots (one with description+examples, one without), no props' => [
        [
          'machineName' => 'test-slots',
          'status' => TRUE,
          'name' => 'Test',
          'props' => [],
          'slots' => [
            'test-slot' => [
              'title' => 'test',
              'description' => 'Title',
              'examples' => [
                'Test 1',
                'Test 2',
              ],
            ],
            'test-slot-only-required' => [
              'title' => 'test',
            ],
          ],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
      'Valid: empty JS and CSS, no props, and "disabled"' => [
        [
          'machineName' => 'test-no-js-no-css-no-props-nor-slots-and-disabled',
          'status' => FALSE,
          'name' => 'Test',
          'props' => [],
          'slots' => [],
          'js' => [
            'original' => '',
            'compiled' => '',
          ],
          'css' => [
            'original' => '',
            'compiled' => '',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
      'Valid: image prop' => [
        [
          'machineName' => 'image-prop-no-slots',
          'name' => 'Test',
          'props' => [
            'image' => [
              'title' => 'Image title',
              'type' => 'object',
              '$ref' => "json-schema-definitions://canvas.module/image",
              'examples' => [
                [
                  'src' => 'https://example.com/image.png',
                  'alt' => 'Alternative text',
                  'width' => 800,
                  'height' => 600,
                ],
              ],
            ],
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
      'Invalid: required image prop missing examples' => [
        [
          'machineName' => 'image-prop-no-slots-no-examples',
          'name' => 'Test',
          'required' => [
            'image',
          ],
          'props' => [
            'image' => [
              'title' => 'Image title',
              'type' => 'object',
              '$ref' => "json-schema-definitions://canvas.module/image",
            ],
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [
          '' => 'Prop "image" is required, but does not have example value',
        ],
      ],
      'Valid: optional image prop missing examples' => [
        [
          'machineName' => 'image-prop-no-slots-no-examples',
          'name' => 'Test',
          'props' => [
            'image' => [
              'title' => 'Image title',
              'type' => 'object',
              '$ref' => "json-schema-definitions://canvas.module/image",
            ],
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
      'Invalid: image prop $ref' => [
        [
          'machineName' => 'image-prop-no-slots-no-ref',
          'name' => 'Test',
          'props' => [
            'image' => [
              'title' => 'Image title',
              'type' => 'object',
              'examples' => [
                [
                  // @todo this is actually an invalid example, will be detected by https://www.drupal.org/i/3508725
                  'src' => 'https://example.com/image.png',
                  'alt' => 'Alternative text',
                  'width' => 800,
                  'height' => 600,
                ],
              ],
            ],
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [
          '' => 'Prop "image" is of type "object" without a $ref, which is not supported',
          'props.image' => '\'$ref\' is a required key because props.image.type is object (see config schema type canvas.json_schema.prop.object).',
          'props.image.examples.0.alt' => "'alt' is not a supported key.",
          'props.image.examples.0.height' => "'height' is not a supported key.",
          'props.image.examples.0.src' => "'src' is not a supported key.",
          'props.image.examples.0.width' => "'width' is not a supported key.",
        ],
      ],
      'Invalid: image prop with incorrect $ref' => [
        [
          'machineName' => 'test-props-no-slots',
          'name' => 'Test',
          'props' => [
            'image' => [
              'title' => 'Image title',
              'type' => 'object',
              '$ref' => "json-schema-definitions://canvas.module/heading",
              'examples' => [
                [
                  'src' => 'https://example.com/image.png',
                  'alt' => 'Alternative text',
                  'width' => 800,
                  'height' => 600,
                ],
              ],
            ],
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [
          '' => "Prop \"image\" has invalid example value: [text] The property text is required\n[element] The property element is required",
          'props.image.$ref' => 'The value you selected is not a valid choice.',
          'props.image.examples.0' => [
            "'text' is a required key.",
            "'element' is a required key.",
          ],
          'props.image.examples.0.alt' => "'alt' is not a supported key.",
          'props.image.examples.0.height' => "'height' is not a supported key.",
          'props.image.examples.0.src' => "'src' is not a supported key.",
          'props.image.examples.0.width' => "'width' is not a supported key.",
        ],
      ],
      'Valid: textarea prop with $ref' => [
        [
          'machineName' => 'test-props-no-slots',
          'name' => 'Test',
          'props' => [
            'textarea' => [
              'title' => 'Textarea',
              'type' => 'string',
              '$ref' => "json-schema-definitions://canvas.module/textarea",
              'examples' => [
                'Simple',
                'Example',
              ],
            ],
          ],
          'slots' => [],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
          'css' => [
            'original' => '.test { display: none; }',
            'compiled' => '.test{display:none;}',
          ],
          'dataDependencies' => [],
        ],
        [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function providerInvalidMachineNameCharacters(): array {
    return [
      'INVALID: space separated' => ['space separated', FALSE],
      'INVALID: period separated' => ['period.separated', FALSE],
      'VALID: dash separated' => ['dash-separated', TRUE],
      'VALID: underscore separated' => ['underscore_separated', TRUE],
      'VALID: contains uppercase' => ['containsUppercase', TRUE],
      'INVALID: starts uppercase' => ['StartsUppercase', FALSE],
      'VALID: contains number' => ['number1', TRUE],
      'INVALID: starts with number' => ['10th_birthday', FALSE],
    ];
  }

  public function testInvalidSlotIdentifiedByConfigSchema(): void {
    $original_test_slot = $this->entity->get('slots')['test-slot'];
    $this->entity->set('slots', [
      '0-slot' => $original_test_slot,
    ]);
    // @todo This test case should have validation errors because '0-slot' is not a valid slot name.
    //   But currently we can not use the 'patternProperties' until
    //   https://www.drupal.org/i/3471064 is fixed.
    $this->assertValidationErrors([]);

    unset($original_test_slot['title']);
    $this->entity->set('slots', [
      'test-slot' => $original_test_slot,
    ]);
    $this->assertValidationErrors([
      '' => 'Slot "test-slot" must have title',
      'slots.test-slot' => "'title' is a required key.",
    ]);
  }

  public function testCollisionBetweenPropsAndSlots(): void {
    $prop_colliding_with_slot = [
      'test-slot' => [
        'title' => 'contrived example',
        'type' => 'string',
        'examples' => ['foo'],
      ],
    ];
    $this->entity->set('props', $prop_colliding_with_slot);
    $this->assertValidationErrors([
      '' => 'The component "canvas:test" declared [test-slot] both as a prop and as a slot. Make sure to use different names.',
    ]);

    // Verify that if there's a lower-level problem, that both the low-level and
    // this high-level consistency validation error appear.
    unset($prop_colliding_with_slot['test-slot']['examples']);
    $this->entity->set('props', $prop_colliding_with_slot);
    $this->assertValidationErrors([
      '' => 'The component "canvas:test" declared [test-slot] both as a prop and as a slot. Make sure to use different names.',
    ]);
  }

  /**
   * @testWith [{}, []]
   *           [{"something": []}, {"dataDependencies.something": "'something' is not a supported key."}]
   *           [{"drupalSettings": []}, {"dataDependencies.drupalSettings": "This value should not be blank."}]
   *           [{"drupalSettings": ["v0.pageTitle", "foo"]}, {"dataDependencies.drupalSettings.1": "The value you selected is not a valid choice."}]
   *           [{"drupalSettings": ["v0.pageTitle", "v0.branding"]}, []]
   *           [{"urls": []}, {"dataDependencies.urls": "This value should not be blank."}]
   *           [{"urls": ["https://www.drupal.org/jsonapi"]}, []]
   */
  public function testDataDependencies(array $test, array $expected_errors): void {
    $this->entity->set('dataDependencies', $test);
    $this->assertValidationErrors($expected_errors);
  }

  protected function assertValidationErrors(array $expected_messages): void {
    // JsComponentHasValidAndSupportedSdcMetadata adds additional validation, but
    // \Drupal\KernelTests\Core\Config\ConfigEntityValidationTestBase::testInvalidMachineNameCharacters()
    // does not provide a way to add additional errors when the machine name is
    // invalid.
    $invalid_id_messages = [
      'machineName' => 'The <em class="placeholder">&quot;' . $this->entity->id() . '&quot;</em> machine name is not valid.',
      '' => "The 'machineName' property cannot be changed.",
    ];
    // 'dash-separated' is valid machine name for component but not for config
    // entity.
    if ($this->entity->id() !== 'dash-separated' && $expected_messages === $invalid_id_messages) {
      $expected_messages[''] = [
        "[id] Does not match the regex pattern ^[a-z]([a-zA-Z0-9_-]*[a-zA-Z0-9])*:[a-z]([a-zA-Z0-9_-]*[a-zA-Z0-9])*$\n[machineName] Does not match the regex pattern ^[a-z]([a-zA-Z0-9_-]*[a-zA-Z0-9])*$",
        $expected_messages[''],
      ];
    }
    parent::assertValidationErrors($expected_messages);
  }

}
