<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\Tests\canvas\Traits\BetterConfigDependencyManagerTrait;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\TestTools\Random;

/**
 * @group canvas
 */
class PageRegionValidationTest extends BetterConfigEntityValidationTestBase {

  use BetterConfigDependencyManagerTrait;
  use GenerateComponentConfigTrait;
  use ConstraintViolationsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected bool $hasLabel = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'canvas',
    'canvas_test_sdc',
    // Canvas's dependencies (modules providing field types + widgets).
    'datetime',
    'file',
    'image',
    'options',
    'path',
    'link',
  ];

  /**
   * An empty tree is allowed.
   *
   * @var array|string[]
   */
  protected static array $propertiesWithOptionalValues = ['component_tree'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->generateComponentConfig();
    $generate_static_prop_source = function (string $label): array {
      return [
        'sourceType' => 'static:field_item:string',
        'value' => "Hello, $label!",
        'expression' => 'ℹ︎string␟value',
      ];
    };
    $this->entity = PageRegion::create([
      'theme' => 'stark',
      'region' => 'sidebar_first',
      'component_tree' => [
        [
          'uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => [
            'heading' => $generate_static_prop_source('world'),
          ],
          'label' => Random::string(255),
        ],
        [
          'uuid' => '3a76bf4f-9306-43e6-ba8f-cb4b5b6459df',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => [
            'heading' => $generate_static_prop_source('another world'),
          ],
        ],
        [
          'uuid' => '93af433a-8ab0-4dd9-912a-73a99c882347',
          'component_id' => 'block.page_title_block',
          'component_version' => '62af221149ae4887',
          'inputs' => [
            'label' => '',
            'label_display' => FALSE,
          ],
        ],
        [
          'uuid' => '5f1c5361-5658-467e-9c53-b0015d57945d',
          'component_id' => 'block.system_messages_block',
          'component_version' => 'b92f802cf68eb83e',
          'inputs' => [
            'label' => '',
            'label_display' => FALSE,
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

    $this->assertSame('stark.sidebar_first', $this->entity->id());

    // Also validate config dependencies are computed correctly.
    $this->assertSame(
      [
        'config' => [
          'canvas.component.block.page_title_block',
          'canvas.component.block.system_messages_block',
          'canvas.component.sdc.canvas_test_sdc.props-no-slots',
        ],
        'theme' => ['stark'],
      ],
      $this->entity->getDependencies()
    );
    $this->assertSame([
      'config' => [
        'canvas.component.block.page_title_block',
        'canvas.component.block.system_messages_block',
        'canvas.component.sdc.canvas_test_sdc.props-no-slots',
      ],
      'module' => [
        'canvas',
        'system',
        'canvas_test_sdc',
      ],
      'theme' => ['stark'],
    ], $this->getAllDependencies($this->entity));
  }

  /**
   * {@inheritdoc}
   */
  public function testInvalidTheme(): void {
    $this->entity->set('theme', 'non_existent_theme');
    $this->assertValidationErrors([
      '' => "The 'theme' property cannot be changed.",
      'theme' => "Theme 'non_existent_theme' is not installed.",
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testInvalidRegion(): void {
    $this->entity->set('region', 'non_existent_region');
    $this->assertValidationErrors([
      '' => "The 'region' property cannot be changed.",
      'region' => "Region 'non_existent_region' does not exist in theme 'stark'.",
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testImmutableProperties(array $valid_values = []): void {
    $this->container->get(ThemeInstallerInterface::class)->install([
      'olivero',
    ]);
    $valid_values = [
      'theme' => 'olivero',
      'region' => 'page_top',
    ];
    $additional_validation_errors = [
      'id' => [],
      'theme' => [
        'region' => "Region 'sidebar_first' does not exist in theme 'olivero'.",
      ],
      'region' => [],
    ];

    // @todo Update parent method to accept a `$additional_validation_errors` parameter in addition to `$valid_values`, and uncomment the next line, remove all lines after it.
    // parent::testImmutableProperties($valid_values);
    $constraints = $this->entity->getEntityType()->getConstraints();
    $this->assertNotEmpty($constraints['ImmutableProperties'], 'All config entities should have at least one immutable ID property.');

    foreach ($constraints['ImmutableProperties'] as $property_name) {
      $original_value = $this->entity->get($property_name);
      $this->entity->set($property_name, $valid_values[$property_name] ?? $this->randomMachineName());
      $this->assertValidationErrors([
        '' => "The '$property_name' property cannot be changed.",
      ] + $additional_validation_errors[$property_name]);
      $this->entity->set($property_name, $original_value);
    }
  }

  /**
   * @dataProvider providerInvalidComponentTree
   */
  public function testInvalidComponentTree(array $component_tree, array $expected_messages): void {
    \assert($this->entity instanceof PageRegion);
    $this->entity->setComponentTree($component_tree);
    $this->assertValidationErrors($expected_messages);
  }

  public static function providerInvalidComponentTree(): \Generator {
    yield "using DynamicPropSource" => [
      'component_tree' => [
        [
          'uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree' => "The 'dynamic' prop source type must be absent.",
      ],
    ];

    yield "not a uuid" => [
      'component_tree' => [
        [
          'uuid' => 'you-are-a-wizard-harry',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => "Ghosts crowd the young child's",
          ],
        ],
        [
          'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => 'Fragile eggshell mind',
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.0.uuid' => 'This is not a valid UUID.',
      ],
    ];

    yield "invalid parent" => [
      'component_tree' => [
        [
          'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => 'And we laugh like soft, mad children',
          ],
        ],
        [
          'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
          'slot' => 'the_body',
          'parent_uuid' => '6381352f-5b0a-4ca1-960d-a5505b37b27c',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => ' Smug in the wooly cotton brains of infancy',
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.1.parent_uuid' => 'Invalid component tree item with UUID <em class="placeholder">e303dd88-9409-4dc7-8a8b-a31602884a94</em> references an invalid parent <em class="placeholder">6381352f-5b0a-4ca1-960d-a5505b37b27c</em>.',
      ],
    ];

    yield "invalid slot" => [
      'component_tree' => [
        [
          'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => 'And we laugh like soft, mad children',
          ],
        ],
        [
          'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
          'slot' => 'banana',
          'parent_uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => ' Smug in the wooly cotton brains of infancy',
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.1.slot' => 'Invalid component subtree. This component subtree contains an invalid slot name for component <em class="placeholder">sdc.canvas_test_sdc.props-slots</em>: <em class="placeholder">banana</em>. Valid slot names are: <em class="placeholder">the_body, the_footer, the_colophon</em>.',
      ],
    ];

    yield "invalid label" => [
      'component_tree' => [
        [
          'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => 'And we laugh like soft, mad children',
          ],
          'label' => Random::string(256),
        ],
      ],
      'expected_messages' => [
        'component_tree.0.label' => 'This value is too long. It should have <em class="placeholder">255</em> characters or less.',
      ],
    ];

    yield "not collapsed" => [
      'component_tree' => [
        [
          'uuid' => 'e303dd88-9409-4dc7-8a8b-a31602884a94',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'ab4d3ddce315cf64',
          'inputs' => [
            'heading' => [
              'sourceType' => 'static:field_item:string',
              'value' => 'And we laugh like soft, mad children',
              'expression' => 'ℹ︎string␟value',
            ],
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.0.inputs.e303dd88-9409-4dc7-8a8b-a31602884a94' => 'When using the default static prop source for a component input, you must use the collapsed input syntax.',
      ],
    ];

    yield "invalid version" => [
      'component_tree' => [
        [
          'uuid' => 'fa9ff0a8-e23a-492a-ab14-5460611fa2c1',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'component_version' => 'abc',
          'inputs' => [
            'heading' => 'And we laugh like soft, mad children',
          ],
        ],
      ],
      'expected_messages' => [
        'component_tree.0.component_version' => "'abc' is not a version that exists on component config entity 'sdc.canvas_test_sdc.props-slots'. Available versions: 'ab4d3ddce315cf64'.",
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function testRequiredPropertyValuesMissing(?array $additional_expected_validation_errors_when_missing = NULL): void {
    // @phpstan-ignore-next-line
    parent::testRequiredPropertyValuesMissing([
      'theme' => [
        'id' => 'This validation constraint is configured to inspect the properties <em class="placeholder">%parent.theme, %parent.region</em>, but some do not exist: <em class="placeholder">%parent.theme</em>.',
      ],
      'region' => [
        'id' => 'This validation constraint is configured to inspect the properties <em class="placeholder">%parent.theme, %parent.region</em>, but some do not exist: <em class="placeholder">%parent.region</em>.',
      ],
    ]);
  }

  /**
   * @dataProvider providerForAutoSaveData
   */
  public function testForAutoSaveData(array $autoSaveData, array $expected_errors): void {
    try {
      assert($this->entity instanceof PageRegion);
      $this->entity->forAutoSaveData($autoSaveData, validate: TRUE);
      $this->assertSame([], $expected_errors);
    }
    catch (ConstraintViolationException $e) {
      $this->assertSame($expected_errors, self::violationsToArray($e->getConstraintViolationList()));
    }
  }

  public static function providerForAutoSaveData(): iterable {
    yield 'INVALID: missing component type' => [
      [
        'layout' => [
          [
            "nodeType" => "component",
            "slots" => [],
            "uuid" => "c3f3c22c-c22e-4bb6-ad16-635f069148e4",
          ],
        ],
        'model' => [],
      ],
      [
        'layout.children.0.component_id' => 'This value should not be blank.',
        'layout.children.0.component_version' => 'This value should not be blank.',
      ],
    ];
    yield 'INVALID: missing component' => [
      [
        'layout' => [
          [
            "nodeType" => "component",
            "slots" => [],
            "type" => "block.page_title_block",
          ],
        ],
        'model' => [],
      ],
      [
        'layout.children.0.uuid' => 'This value should not be blank.',
        'layout.children.0.component_version' => 'This value should not be blank.',
      ],
    ];
    yield 'VALID: single valid region node; other regions missing — these are restored automatically from the stored Page Regions' => [
      [
        'layout' => [
          [
            "nodeType" => "component",
            "slots" => [],
            "type" => "block.page_title_block@62af221149ae4887",
            "uuid" => "c3f3c22c-c22e-4bb6-ad16-635f069148e4",
          ],
        ],
        'model' => [
          'c3f3c22c-c22e-4bb6-ad16-635f069148e4' => [
            'label' => '',
            'label_display' => FALSE,
          ],
        ],
      ],
      [],
    ];
  }

}
