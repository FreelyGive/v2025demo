<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * @group canvas
 */
class ConfigWithComponentTreeTestBase extends KernelTestBase {

  use ConstraintViolationsTestTrait;
  use GenerateComponentConfigTrait;

  /**
   * The config entity with a component tree being tested.
   */
  protected ComponentTreeEntityInterface&ConfigEntityInterface $entity;

  /**
   * @var array<string, string>
   */
  protected static $expectedViolations = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The two only modules Drupal truly requires.
    'system',
    'user',
    // The module being tested.
    'canvas',
    // Modules providing used Components (and their ComponentSource plugins).
    'block',
    'canvas_test_sdc',
    // Canvas's dependencies (modules providing field types + widgets).
    'field',
    'file',
    'image',
    'link',
    'media',
    'node',
    'options',
    'text',
    'filter',
    'ckeditor5',
    'editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->generateComponentConfig();
  }

  #[TestWith([
    [
      [
        'uuid' => 'b7e2cf39-d62f-4ee8-99b2-27a89f1ac196',
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'component_version' => '95f4f1d5ee47663b',
        'parent_uuid' => '3a76bf4f-9306-43e6-ba8f-cb4b5b6459df',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Two layers deep.',
        ],
      ],
      [
        'uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'inputs' => [
          'heading' => 'Hello, world!',
        ],
      ],
      [
        'uuid' => '5f1c5361-5658-467e-9c53-b0015d57945d',
        'component_id' => 'block.system_powered_by_block',
        'component_version' => '3332388cade78d20',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_footer',
        'inputs' => [
          'label' => '',
          'label_display' => FALSE,
        ],
      ],
      [
        'uuid' => '3a76bf4f-9306-43e6-ba8f-cb4b5b6459df',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Hello from the top of the body',
        ],
      ],
      [
        'uuid' => '5f71027b-d9d3-4f3d-8990-a6502c0ba676',
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'component_version' => '95f4f1d5ee47663b',
        'inputs' => [
          'heading' => 'two layers deep',
        ],
      ],
      [
        'uuid' => '93af433a-8ab0-4dd9-912a-73a99c882347',
        'component_id' => 'block.system_branding_block',
        'component_version' => '247a23298360adb2',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_body',
        'inputs' => [
          'use_site_logo' => TRUE,
          'use_site_name' => TRUE,
          'use_site_slogan' => TRUE,
          'label' => '',
          'label_display' => FALSE,
        ],
      ],
    ],
    [
      '0' => [
        'uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'inputs' => [
          'heading' => 'Hello, world!',
        ],
      ],
      '0:the_body:0' => [
        'uuid' => '3a76bf4f-9306-43e6-ba8f-cb4b5b6459df',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Hello from the top of the body',
        ],
      ],
      '0:the_body:0:the_body:0' => [
        'uuid' => 'b7e2cf39-d62f-4ee8-99b2-27a89f1ac196',
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'component_version' => '95f4f1d5ee47663b',
        'parent_uuid' => '3a76bf4f-9306-43e6-ba8f-cb4b5b6459df',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Two layers deep.',
        ],
      ],
      '0:the_body:1' => [
        'uuid' => '93af433a-8ab0-4dd9-912a-73a99c882347',
        'component_id' => 'block.system_branding_block',
        'component_version' => '247a23298360adb2',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_body',
        'inputs' => [
          'use_site_logo' => TRUE,
          'use_site_name' => TRUE,
          'use_site_slogan' => TRUE,
          'label' => '',
          'label_display' => FALSE,
        ],
      ],
      '0:the_footer:0' => [
        'uuid' => '5f1c5361-5658-467e-9c53-b0015d57945d',
        'component_id' => 'block.system_powered_by_block',
        'component_version' => '3332388cade78d20',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_footer',
        'inputs' => [
          'label' => '',
          'label_display' => FALSE,
        ],
      ],
      '1' => [
        'uuid' => '5f71027b-d9d3-4f3d-8990-a6502c0ba676',
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'component_version' => '95f4f1d5ee47663b',
        'inputs' => [
          'heading' => 'two layers deep',
        ],
      ],
    ],
  ], 'Simple case')]
  #[TestWith([
    [
      [
        'uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'inputs' => [
          'heading' => 'Outer slot',
        ],
      ],
      [
        'uuid' => '33a67161-a77b-4192-a575-d9d96635399c',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Level 1 slot',
        ],
      ],
      [
        'uuid' => '1955e628-73ae-4334-a354-06fcbda376d6',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '33a67161-a77b-4192-a575-d9d96635399c',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Level 2 slot',
        ],
      ],
      [
        'uuid' => '5f1c5361-5658-467e-9c53-b0015d57945d',
        'component_id' => 'block.system_powered_by_block',
        'component_version' => '3332388cade78d20',
        'parent_uuid' => '1955e628-73ae-4334-a354-06fcbda376d6',
        'slot' => 'the_body',
        'inputs' => [
          'label' => '',
          'label_display' => FALSE,
        ],
      ],
      [
        'uuid' => '3a76bf4f-9306-43e6-ba8f-cb4b5b6459df',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '1955e628-73ae-4334-a354-06fcbda376d6',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Just after the powered by block',
        ],
      ],
      [
        'uuid' => 'b16e28d2-ec29-480c-9944-ca72eac5d16f',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '1955e628-73ae-4334-a354-06fcbda376d6',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Last one in the body in level 2 slot',
        ],
      ],
      [
        'uuid' => '5a039deb-db16-42fd-a91d-8b5a189afbc3',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Level 1 slot #2',
        ],
      ],
      [
        'uuid' => '8dc67694-59c6-4efe-92e9-d8e3f9d03f51',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '1 of 6 in the footer',
        ],
      ],
      [
        'uuid' => 'b6e8eba3-7f41-4115-9d24-67223909dcd4',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '2 of 6 in the footer',
        ],
      ],
      [
        'uuid' => '36b6338a-12b4-485f-a4f6-209f438e6804',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '3 of 6 in the footer',
        ],
      ],
      [
        'uuid' => 'ac1e278a-2f0f-4166-a98d-1d390b3d0aa8',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '4 of 6 in the footer',
        ],
      ],
      [
        'uuid' => '09309f76-377f-456c-ab29-b5a10eecab48',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '5 of 6 in the footer',
        ],
      ],
      [
        'uuid' => '294a32af-0bcc-4e45-9044-ac51d9b9a7df',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '6 of 6 in the footer',
        ],
      ],
      // Note this is the parent slot of the preceding items, but should be
      // sorted above them.
      [
        'uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '5a039deb-db16-42fd-a91d-8b5a189afbc3',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Level 2 slot #2',
        ],
      ],
    ],
    [
      '0' => [
        'uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'inputs' => [
          'heading' => 'Outer slot',
        ],
      ],
      '0:the_body:0' => [
        'uuid' => '33a67161-a77b-4192-a575-d9d96635399c',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Level 1 slot',
        ],
      ],
      '0:the_body:0:the_body:0' => [
        'uuid' => '1955e628-73ae-4334-a354-06fcbda376d6',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '33a67161-a77b-4192-a575-d9d96635399c',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Level 2 slot',
        ],
      ],
      '0:the_body:0:the_body:0:the_body:0' => [
        'uuid' => '5f1c5361-5658-467e-9c53-b0015d57945d',
        'component_id' => 'block.system_powered_by_block',
        'component_version' => '3332388cade78d20',
        'parent_uuid' => '1955e628-73ae-4334-a354-06fcbda376d6',
        'slot' => 'the_body',
        'inputs' => [
          'label' => '',
          'label_display' => FALSE,
        ],
      ],
      '0:the_body:0:the_body:0:the_body:1' => [
        'uuid' => '3a76bf4f-9306-43e6-ba8f-cb4b5b6459df',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '1955e628-73ae-4334-a354-06fcbda376d6',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Just after the powered by block',
        ],
      ],
      '0:the_body:0:the_body:0:the_body:2' => [
        'uuid' => 'b16e28d2-ec29-480c-9944-ca72eac5d16f',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '1955e628-73ae-4334-a354-06fcbda376d6',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Last one in the body in level 2 slot',
        ],
      ],
      '0:the_body:1' => [
        'uuid' => '5a039deb-db16-42fd-a91d-8b5a189afbc3',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '4f785025-9bd9-4752-9dd6-068b957b03ee',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Level 1 slot #2',
        ],
      ],
      '0:the_body:1:the_body:0' => [
        'uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '5a039deb-db16-42fd-a91d-8b5a189afbc3',
        'slot' => 'the_body',
        'inputs' => [
          'heading' => 'Level 2 slot #2',
        ],
      ],
      '0:the_body:1:the_body:0:the_footer:0' => [
        'uuid' => '8dc67694-59c6-4efe-92e9-d8e3f9d03f51',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '1 of 6 in the footer',
        ],
      ],
      '0:the_body:1:the_body:0:the_footer:1' => [
        'uuid' => 'b6e8eba3-7f41-4115-9d24-67223909dcd4',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '2 of 6 in the footer',
        ],
      ],
      '0:the_body:1:the_body:0:the_footer:2' => [
        'uuid' => '36b6338a-12b4-485f-a4f6-209f438e6804',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '3 of 6 in the footer',
        ],
      ],
      '0:the_body:1:the_body:0:the_footer:3' => [
        'uuid' => 'ac1e278a-2f0f-4166-a98d-1d390b3d0aa8',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '4 of 6 in the footer',
        ],
      ],
      '0:the_body:1:the_body:0:the_footer:4' => [
        'uuid' => '09309f76-377f-456c-ab29-b5a10eecab48',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '5 of 6 in the footer',
        ],
      ],
      '0:the_body:1:the_body:0:the_footer:5' => [
        'uuid' => '294a32af-0bcc-4e45-9044-ac51d9b9a7df',
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'parent_uuid' => '83e58222-88ff-40d7-ad70-4d0efa5b9172',
        'slot' => 'the_footer',
        'inputs' => [
          'heading' => '6 of 6 in the footer',
        ],
      ],
    ],
  ], 'Complex nesting')]
  public function testComponentTreeKeyOrder(array $tree_input, array $expected_sorted_output): void {
    $this->entity->setComponentTree($tree_input);
    $tree_output = $this->entity->get('component_tree');
    self::assertEquals(\count($tree_input), \count($tree_output));
    self::assertSame($expected_sorted_output, $tree_output);
    // Sanity-check that the test entity is valid.
    $violations = $this->entity->getTypedData()->validate();
    self::assertSame(static::$expectedViolations, self::violationsToArray($violations));
  }

}
