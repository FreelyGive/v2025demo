<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

/**
 * Provides data for testing simulated Block Component schema update.
 */
trait BlockComponentTreeSchemaUpdateTestTrait {

  private const string UUID_INPUT_NONE = 'e38884f8-d169-48d0-b503-251cacb610c1';
  private const string UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE = '4776b493-a863-467c-ba39-7b6cf3dab47d';
  private const string UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO = '350b3ea8-85e6-4c6b-86d2-e4869d3c35ab';

  /**
   * The method provides 3 values on each item.
   *
   *   - The ComponentTree to test.
   *   - The new `inputs` value for each component instance to be done after the schema update.
   *   - The expected values [violations and text] for each component instance.
   */
  public static function getValidTreesForASchemaUpdate(): \Generator {
    yield 'tree with no blocks with update' => [
      [
        [
          'uuid' => self::UUID_INPUT_NONE,
          'component_id' => 'block.canvas_test_block_input_none',
          'component_version' => 'f91f8d4aff4aba7c',
          'inputs' => [
            'label' => 'Test block with no settings.',
            'label_display' => '',
          ],
        ],
      ],
      [
        self::UUID_INPUT_NONE => 'Hello bob, from Canvas!',
      ],
      [
        self::UUID_INPUT_NONE => 'Hello bob, from Canvas!',
      ],
      [
        self::UUID_INPUT_NONE => 'Hello bob, from Canvas!',
      ],
      [
        self::UUID_INPUT_NONE => 'Hello bob, from Canvas!',
      ],
      [],
      [
        [
          'uuid' => self::UUID_INPUT_NONE,
          'component_id' => 'block.canvas_test_block_input_none',
          'component_version' => 'f91f8d4aff4aba7c',
          'inputs' => [
            'label' => 'Test block with no settings.',
            'label_display' => '',
          ],
        ],
      ],
    ];

    yield 'tree with double block with update' => [
      [
        [
          'uuid' => self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE,
          'component_id' => 'block.canvas_test_block_input_schema_change_poc',
          'component_version' => '7cc894b85e93a7d8',
          'inputs' => [
            'label' => 'Block schema change POC 1.',
            'label_display' => '',
            'foo' => 'bar',
          ],
        ],
        [
          'uuid' => self::UUID_INPUT_NONE,
          'component_id' => 'block.canvas_test_block_input_none',
          'component_version' => 'f91f8d4aff4aba7c',
          'inputs' => [
            'label' => 'Test block with no settings.',
            'label_display' => '',
          ],
        ],
        [
          'uuid' => self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO,
          'component_id' => 'block.canvas_test_block_input_schema_change_poc',
          'component_version' => '7cc894b85e93a7d8',
          'inputs' => [
            'label' => 'Block schema change POC 2.',
            'label_display' => '',
            'foo' => 'baz',
          ],
        ],
      ],
      [
        self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE => 'Current foo value: bar',
        self::UUID_INPUT_NONE => 'Hello bob, from Canvas!',
        self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO => 'Current foo value: baz',
      ],
      [
        self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE => 'Modified block! Current foo value: bar. Change … is scary.',
        self::UUID_INPUT_NONE => 'Hello bob, from Canvas!',
        self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO => 'Modified block! Current foo value: baz. Change … is scary.',
      ],
      [
        self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE => 'Oops, something went wrong! Site admins have been notified.',
        self::UUID_INPUT_NONE => 'Hello bob, from Canvas!',
        self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO => 'Oops, something went wrong! Site admins have been notified.',
      ],
      [
        self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE => 'Modified block! Current foo value: 2. Change … is necessary.',
        self::UUID_INPUT_NONE => 'Hello bob, from Canvas!',
        self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO => 'Modified block! Current foo value: 1. Change … is necessary.',
      ],
      [
        '0.inputs.' . self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE . '.' => "'change' is a required key.",
        '0.inputs.' . self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE . '.foo' => [
          'The value you selected is not a valid choice.',
          'This value should be of the correct primitive type.',
        ],
        '2.inputs.' . self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO . '.' => "'change' is a required key.",
        '2.inputs.' . self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO . '.foo' => [
          'The value you selected is not a valid choice.',
          'This value should be of the correct primitive type.',
        ],
      ],
      [
        [
          'uuid' => self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_ONE,
          'component_id' => 'block.canvas_test_block_input_schema_change_poc',
          'component_version' => '88c370526c14d185',
          'inputs' => [
            'label' => 'Block schema change POC 1.',
            'label_display' => '',
            // @see \Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource\ComponentInputsEvolutionTest::blockUpdatePathSampleForCoreIssue3521221()
            'foo' => 2,
            'change' => 'is necessary',
          ],
        ],
        [
          'uuid' => self::UUID_INPUT_NONE,
          'component_id' => 'block.canvas_test_block_input_none',
          'component_version' => 'f91f8d4aff4aba7c',
          'inputs' => [
            'label' => 'Test block with no settings.',
            'label_display' => '',
          ],
        ],
        [
          'uuid' => self::UUID_INPUT_SCHEMA_CHANGE_POSSIBLE_VALUE_TWO,
          'component_id' => 'block.canvas_test_block_input_schema_change_poc',
          'component_version' => '88c370526c14d185',
          'inputs' => [
            'label' => 'Block schema change POC 2.',
            'label_display' => '',
            // @see \Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource\ComponentInputsEvolutionTest::blockUpdatePathSampleForCoreIssue3521221()
            'foo' => 1,
            'change' => 'is necessary',
          ],
        ],
      ],
    ];
  }

}
