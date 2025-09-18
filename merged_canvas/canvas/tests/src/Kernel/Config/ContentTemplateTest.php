<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\EntityHandlers\ContentTemplateAwareViewBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * @coversDefaultClass \Drupal\canvas\Entity\ContentTemplate
 * @group canvas
 */
final class ContentTemplateTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use ContentTypeCreationTrait;
  use GenerateComponentConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // The two only modules Drupal truly requires.
    'system',
    'user',
    // The module being tested.
    'canvas',
    // The content entity type being tested plus bundle fields.
    'node',
    'field',
    'text',
    // Test components.
    'canvas_test_sdc',
    'block',
    // Field types used by test components.
    'media',
    'image',
    'link',
    'file',
    'options',
    'filter',
    'ckeditor5',
    'editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['node', 'user']);
    NodeType::create(['type' => 'helpful', 'name' => 'Helpful'])->save();
  }

  /**
   * @covers ::label
   *
   * @testWith ["node.helpful.full", "Helpful content items — Full content view"]
   *           ["user.user.compact", "Users — Compact view"]
   */
  public function testLabel(string $id, string $expected_label): void {
    [$entity_type_id, $bundle, $view_mode] = explode('.', $id, 3);

    $template = ContentTemplate::create([
      'id' => $id,
      'content_entity_type_id' => $entity_type_id,
      'content_entity_type_bundle' => $bundle,
      'content_entity_type_view_mode' => $view_mode,
    ]);
    $this->assertSame($expected_label, (string) $template->label());
  }

  /**
   * @covers \Drupal\canvas\Hook\ContentTemplateHooks::entityTypeAlter()
   */
  public function testOnlyContentEntitiesCanUseTemplates(): void {
    $manager = \Drupal::entityTypeManager();
    $definition = $manager->getDefinition('node');
    assert($definition instanceof EntityTypeInterface);
    $this->assertTrue($definition->hasHandlerClass(ContentTemplateAwareViewBuilder::DECORATED_HANDLER_KEY));
    $this->assertSame(ContentTemplateAwareViewBuilder::class, $definition->getViewBuilderClass());

    // Config entities have no view builder and Canvas doesn't touch them.
    $definition = $manager->getDefinition('user_role');
    assert($definition instanceof EntityTypeInterface);
    $this->assertFalse($definition->hasViewBuilderClass());
    $this->assertFalse($definition->hasHandlerClass(ContentTemplateAwareViewBuilder::DECORATED_HANDLER_KEY));

    // Canvas pages are left alone despite being content entities.
    $definition = $manager->getDefinition(Page::ENTITY_TYPE_ID);
    assert($definition instanceof EntityTypeInterface);
    $this->assertFalse($definition->hasHandlerClass(ContentTemplateAwareViewBuilder::DECORATED_HANDLER_KEY));
  }

  public function testTreeKeyOrdering(): void {
    $this->installConfig('node');
    $this->createContentType(['type' => 'alpha']);
    $this->installConfig('canvas');
    $this->generateComponentConfig();
    $template = ContentTemplate::create([
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'alpha',
      'content_entity_type_view_mode' => 'full',
    ]);
    $template->setComponentTree([
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
          'heading' => [
            'sourceType' => 'dynamic',
            'expression' => 'ℹ︎␜entity:node:alpha␝title␞␟value',
          ],
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
    ]);
    self::assertSame(
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
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:alpha␝title␞␟value',
            ],
          ],
        ],
      ], $template->get('component_tree'),
    );
    // Sanity-check that the test template is valid.
    $violations = $template->getTypedData()->validate();
    self::assertCount(0, $violations, \implode(', ', \array_map(static fn (ConstraintViolationInterface $violation) => $violation->getMessage(), \iterator_to_array($violations))));
  }

}
