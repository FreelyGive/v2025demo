<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

/**
 * Any test using these test cases must install the `canvas_test_block` module.
 */
trait BlockComponentTreeTestTrait {

  public static function getValidTreeTestCases(): array {
    return [
      'block input none' => [
        [
          [
            'uuid' => 'block-input-none',
            'component_id' => 'block.canvas_test_block_input_none',
            'inputs' => [
              'label' => 'Test block with no settings.',
              'label_display' => '',
            ],
          ],
        ],
      ],

      'block input validatable' => [
        [
          [
            'uuid' => 'block-input-validatable',
            'component_id' => 'block.canvas_test_block_input_validatable',
            'inputs' => [
              'label' => 'Test Block for testing.',
              'label_display' => '',
              'name' => 'Component',
            ],
          ],
        ],
      ],
    ];
  }

}
