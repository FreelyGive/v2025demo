<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Plugin\Validation;

use Drupal\Core\Validation\BasicRecursiveValidatorFactory;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemListInstantiatorTrait;
use Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraint;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;

/**
 * @coversDefaultClass \Drupal\canvas\Plugin\Validation\Constraint\ComponentTreeStructureConstraintValidator
 * @group canvas
 */
final class ComponentTreeStructureConstraintValidatorTest extends KernelTestBase {

  use ConstraintViolationsTestTrait;
  use GenerateComponentConfigTrait;
  use ComponentTreeItemListInstantiatorTrait;

  protected static $modules = [
    'canvas',
    'canvas_test_sdc',
    'block',
    // Canvas's dependencies (modules providing field types + widgets).
    'datetime',
    'file',
    'image',
    'media',
    'options',
    'path',
    'link',
    'system',
    'user',
  ];

  /**
   * @dataProvider providerValidation
   */
  public function testValidation(array $items, array $expected_violations): void {
    $this->generateComponentConfig();
    $validator = \Drupal::service(BasicRecursiveValidatorFactory::class)->createValidator();
    $violations = $validator->validate($items, new ComponentTreeStructureConstraint(['basePropertyPath' => 'layout']));
    $this->assertSame($expected_violations, self::violationsToArray($violations));
  }

  /**
   * @dataProvider providerValidationItemList
   */
  public function testValidationItemList(array $items, array $expected_violations): void {
    $this->generateComponentConfig();
    $item_list = $this->createDanglingComponentTreeItemList();
    $item_list->setValue($items);
    $validator = \Drupal::service(BasicRecursiveValidatorFactory::class)->createValidator();
    $violations = $validator->validate($item_list, new ComponentTreeStructureConstraint(['basePropertyPath' => 'layout']));
    $this->assertSame($expected_violations, self::violationsToArray($violations));
  }

  public static function providerValidationItemList(): array {
    $cases = self::providerValidation();
    // Setting these very invalid cases into a field item causes some
    // manipulation to match the defined properties so the error messages are
    // slightly different.
    $cases['INVALID: component instance keys wrong, string instead of arrays'][1] = [
      'layout.0.uuid' => 'This value should not be blank.',
      'layout.1.uuid' => 'This value should not be blank.',
      'layout.1.component_id' => 'This value should not be blank.',
      'layout.1.component_version' => 'This value should not be blank.',
      'layout.2.uuid' => 'This value should not be blank.',
      'layout.2.component_id' => 'This value should not be blank.',
      'layout.2.component_version' => 'This value should not be blank.',
      'layout.3.uuid' => 'This value should not be blank.',
      'layout.3.component_id' => 'This value should not be blank.',
      'layout.3.component_version' => 'This value should not be blank.',
    ];
    $cases['INVALID: no uuid, version or component_id'][1] = [
      'layout.0.uuid' => 'This value should not be blank.',
      'layout.0.component_id' => 'This value should not be blank.',
      'layout.0.component_version' => 'This value should not be blank.',
    ];
    return $cases;
  }

  public static function providerValidation(): array {
    return [
      'INVALID: component instance keys wrong, string instead of arrays' => [
        [
            ['component_id' => 'sdc.canvas_test_sdc.props-slots'],
            ['wrong-key' => 'a value'],
          "string",
          'uuid-in-root' => [
            'the_body' => [
                ['wrong-key' => 'a value'],
              "string",
            ],
            "string",
          ],
        ],
        [
          'layout.0.uuid' => 'This field is missing.',
          'layout.0.component_version' => 'This field is missing.',
          'layout.1.uuid' => 'This field is missing.',
          'layout.1.component_id' => 'This field is missing.',
          'layout.1.component_version' => 'This field is missing.',
          // TRICKY: this is due to a bug in \Drupal\Core\Validation\DrupalTranslator::trans() — it should replace `{{ … }}` in the message.
          'layout.2' => 'This value should be of type {{ type }}.',
          'layout.uuid-in-root.uuid' => 'This field is missing.',
          'layout.uuid-in-root.component_id' => 'This field is missing.',
          'layout.uuid-in-root.component_version' => 'This field is missing.',
        ],
      ],
      'INVALID: no uuid, version or component_id' => [
        ['other-uuid' => []],
        [
          'layout.other-uuid.uuid' => 'This field is missing.',
          'layout.other-uuid.component_id' => 'This field is missing.',
          'layout.other-uuid.component_version' => 'This field is missing.',
        ],
      ],
      'VALID: only root' => [
        [],
        [],
      ],
      'VALID: valid tree, only root, component has slots but empty' => [
        [
          [
            'uuid' => '2886421e-4ede-4bfb-956c-8afcd4ee8103',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
        ],
        [],
      ],
      'VALID: valid tree, with top level, component has slots, slots have correct names' => [
        [
          [
            'uuid' => '2886421e-4ede-4bfb-956c-8afcd4ee8103',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'parent_uuid' => '2886421e-4ede-4bfb-956c-8afcd4ee8103',
            'slot' => 'the_body',
            'uuid' => '80bf49ec-3d3f-4e76-98ed-2ce147397643',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'component_version' => '95f4f1d5ee47663b',
          ],
        ],
        [],
      ],
      'INVALID: valid tree, with top level, component has slots, used 3x, 2x with slots have wrong names' => [
        [
          [
            'uuid' => '80bf49ec-3d3f-4e76-98ed-2ce147397643',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => 'bcf003b2-a81b-48b6-bb4c-772814edaa2a',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],

          [
            'uuid' => '5067ea49-f893-4d9a-8587-6586e459bd6c',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'parent_uuid' => '50330afa-a840-4527-bc37-5921d99addf1',
            'slot' => 'the_body',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => '9b654898-2e58-4d3a-a160-bfde52796a11',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'parent_uuid' => 'bcf003b2-a81b-48b6-bb4c-772814edaa2a',
            'slot' => 'slot1',
            'component_version' => '95f4f1d5ee47663b',
          ],
          [
            'uuid' => 'e685308a-0d0f-44dd-830d-1ec7731810e7',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'parent_uuid' => 'bcf003b2-a81b-48b6-bb4c-772814edaa2a',
            'slot' => 'slot2',
            'component_version' => '95f4f1d5ee47663b',
          ],
          [
            'uuid' => '8bc0f436-1930-4a25-b891-632e55d07e27',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'parent_uuid' => 'bcf003b2-a81b-48b6-bb4c-772814edaa2a',
            'slot' => 'the_body',
            'component_version' => '95f4f1d5ee47663b',
          ],
          [
            'uuid' => '0df965c3-dda3-44a0-b3bb-b3dcd62a6817',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'parent_uuid' => '8bc0f436-1930-4a25-b891-632e55d07e27',
            'slot' => 'slot3',
            'component_version' => '95f4f1d5ee47663b',
          ],
        ],
        [
          'layout.2.parent_uuid' => 'Invalid component tree item with UUID <em class="placeholder">5067ea49-f893-4d9a-8587-6586e459bd6c</em> references an invalid parent <em class="placeholder">50330afa-a840-4527-bc37-5921d99addf1</em>.',
          'layout.3.slot' => 'Invalid component subtree. This component subtree contains an invalid slot name for component <em class="placeholder">sdc.canvas_test_sdc.props-slots</em>: <em class="placeholder">slot1</em>. Valid slot names are: <em class="placeholder">the_body, the_footer, the_colophon</em>.',
          'layout.4.slot' => 'Invalid component subtree. This component subtree contains an invalid slot name for component <em class="placeholder">sdc.canvas_test_sdc.props-slots</em>: <em class="placeholder">slot2</em>. Valid slot names are: <em class="placeholder">the_body, the_footer, the_colophon</em>.',
          'layout.6.parent_uuid' => 'Invalid component subtree. A component subtree must only exist for components with >=1 slot, but the component <em class="placeholder">sdc.canvas_test_sdc.props-no-slots</em> has no slots, yet a subtree exists for the instance with UUID <em class="placeholder">8bc0f436-1930-4a25-b891-632e55d07e27</em>.',
        ],
      ],
      'INVALID: valid tree, with top level, under own branch' => [
        [
          [
            'uuid' => 'ad51078a-d1d5-4385-8693-2beaefcf30bf',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'component_version' => '95f4f1d5ee47663b',
          ],
          [
            'uuid' => 'f67147cb-be50-459a-915d-34d8646012f4',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'parent_uuid' => 'f67147cb-be50-459a-915d-34d8646012f4',
            'slot' => 'the_body',
            'component_version' => 'ab4d3ddce315cf64',
          ],
        ],
        [
          'layout.1.parent_uuid' => 'Invalid component tree item with UUID <em class="placeholder">f67147cb-be50-459a-915d-34d8646012f4</em> claims to be parent of itself.',
        ],
      ],
      'VALID: valid tree, multiple levels' => [
        [
          [
            'uuid' => '8d2e68e5-fd4a-47dc-a641-06062723525d',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => 'a022682d-d94b-4f66-bfad-034f0eba5906',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'parent_uuid' => '8d2e68e5-fd4a-47dc-a641-06062723525d',
            'slot' => 'the_body',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => 'ffa4aa03-2bba-4d9b-81d7-37a412836838',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'parent_uuid' => '8d2e68e5-fd4a-47dc-a641-06062723525d',
            'slot' => 'the_body',
            'component_version' => '95f4f1d5ee47663b',
          ],
        ],
        [],
      ],
      'INVALID: duplicate UUID' => [
        [
          [
            'uuid' => '8d2e68e5-fd4a-47dc-a641-06062723525d',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => '8d2e68e5-fd4a-47dc-a641-06062723525d',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
        ],
        [
          'layout' => 'Not all component instance UUIDs in this component tree are unique.',
        ],
      ],
      'INVALID: valid tree, with unknown parent' => [
        [
          [
            'uuid' => '01703ce1-3eaa-4171-91d9-5b6fe22da2af',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'component_version' => '95f4f1d5ee47663b',
          ],
          [
            'uuid' => 'cffc81cb-df7e-4481-83eb-d3ea71bba987',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => 'd823d3c9-be9f-4053-8bc9-ad36914c345c',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'parent_uuid' => 'cffc81cb-df7e-4481-83eb-d3ea71bba987',
            'slot' => 'the_body',
            'component_version' => 'ab4d3ddce315cf64',
          ],
          [
            'uuid' => '357963ff-2eed-4e34-b768-0517cfb52207',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'parent_uuid' => 'd823d3c9-be9f-4053-8bc9-ad36914c345c',
            'slot' => 'the_body',
            'component_version' => '95f4f1d5ee47663b',
          ],
          [
            'uuid' => 'aa595654-57c9-463b-ad33-61f47dc7049b',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'parent_uuid' => '7e090562-0f3b-4bec-8e43-f19e7408a4d9',
            'slot' => 'the_body',
            'component_version' => '95f4f1d5ee47663b',
          ],
        ],
        [
          'layout.4.parent_uuid' => 'Invalid component tree item with UUID <em class="placeholder">aa595654-57c9-463b-ad33-61f47dc7049b</em> references an invalid parent <em class="placeholder">7e090562-0f3b-4bec-8e43-f19e7408a4d9</em>.',
        ],
      ],
      'INVALID: valid tree, with parent but not slot' => [
        [
          [
            'uuid' => '01703ce1-3eaa-4171-91d9-5b6fe22da2af',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'component_version' => '95f4f1d5ee47663b',
          ],
          [
            'uuid' => 'd823d3c9-be9f-4053-8bc9-ad36914c345c',
            'component_id' => 'sdc.canvas_test_sdc.props-slots',
            'parent_uuid' => '01703ce1-3eaa-4171-91d9-5b6fe22da2af',
            'component_version' => 'ab4d3ddce315cf64',
          ],
        ],
        [
          'layout.1.slot' => 'Invalid component tree item with UUID <em class="placeholder">d823d3c9-be9f-4053-8bc9-ad36914c345c</em>. A slot name must be present if a parent uuid is provided.',
        ],
      ],
      'INVALID: valid tree, with unknown components' => [
        [
          [
            'uuid' => '80bf49ec-3d3f-4e76-98ed-2ce147397643',
            'component_id' => 'sdc.canvas_test_sdc.missing-component-1',
            'component_version' => 'irrelevant',
          ],
          [
            'uuid' => 'bcf003b2-a81b-48b6-bb4c-772814edaa2a',
            'component_id' => 'sdc.canvas_test_sdc.missing-component-1',
            'component_version' => 'irrelevant',
          ],
          [
            'uuid' => '50330afa-a840-4527-bc37-5921d99addf1-3',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'component_version' => '95f4f1d5ee47663b',
          ],
          [
            'uuid' => '9b654898-2e58-4d3a-a160-bfde52796a11',
            'component_id' => 'sdc.canvas_test_sdc.missing-component-1',
            'parent_uuid' => '1be63e02-d343-4d67-a1fe-7fa533fba2c6',
            'slot' => 'the_body',
            'component_version' => 'irrelevant',
          ],
          [
            'uuid' => 'e685308a-0d0f-44dd-830d-1ec7731810e7',
            'component_id' => 'sdc.canvas_test_sdc.missing-component-2',
            'parent_uuid' => '1be63e02-d343-4d67-a1fe-7fa533fba2c6',
            'slot' => 'the_body',
            'component_version' => 'irrelevant',
          ],
          [
            'uuid' => '8bc0f436-1930-4a25-b891-632e55d07e27',
            'component_id' => 'sdc.canvas_test_sdc.missing-component-2',
            'parent_uuid' => '1be63e02-d343-4d67-a1fe-7fa533fba2c6',
            'slot' => 'the_body',
            'component_version' => 'irrelevant',
          ],
          [
            'uuid' => '9b6a4cf9-e707-48a1-babf-cb726b86726a',
            'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
            'parent_uuid' => '1be63e02-d343-4d67-a1fe-7fa533fba2c6',
            'slot' => 'the_body',
            'component_version' => '95f4f1d5ee47663b',
          ],
        ],
        [
          'layout.0.component_id' => "The 'canvas.component.sdc.canvas_test_sdc.missing-component-1' config does not exist.",
          'layout.1.component_id' => "The 'canvas.component.sdc.canvas_test_sdc.missing-component-1' config does not exist.",
          'layout.2.uuid' => 'This is not a valid UUID.',
          'layout.3.component_id' => "The 'canvas.component.sdc.canvas_test_sdc.missing-component-1' config does not exist.",
          'layout.4.component_id' => "The 'canvas.component.sdc.canvas_test_sdc.missing-component-2' config does not exist.",
          'layout.5.component_id' => "The 'canvas.component.sdc.canvas_test_sdc.missing-component-2' config does not exist.",
          'layout.6.parent_uuid' => 'Invalid component tree item with UUID <em class="placeholder">9b6a4cf9-e707-48a1-babf-cb726b86726a</em> references an invalid parent <em class="placeholder">1be63e02-d343-4d67-a1fe-7fa533fba2c6</em>.',
        ],
      ],
    ];
  }

}
