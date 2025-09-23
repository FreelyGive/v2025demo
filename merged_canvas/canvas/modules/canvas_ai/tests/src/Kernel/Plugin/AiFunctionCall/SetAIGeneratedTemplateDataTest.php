<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\canvas_ai\CanvasAiTempStore;

/**
 * Tests for the SetAIGeneratedTemplateData function call plugin.
 *
 * @group canvas_ai
 */
final class SetAIGeneratedTemplateDataTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $functionCallManager;

  /**
   * A test user with AI permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $privilegedUser;

  /**
   * A test user without AI permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $unprivilegedUser;

  /**
   * The Canvas AI temp store service mock.
   *
   * @var \Drupal\canvas_ai\CanvasAiTempStore|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $mockTempStore;

  /**
   * The mock page builder helper.
   *
   * @var \Drupal\canvas_ai\CanvasAiPageBuilderHelper
   */
  protected $pageBuilderHelper;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_agents',
    'experience_builder',
    'system',
    'user',
    'canvas_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');

    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
    $privileged_user = $this->createUser(['use experience builder ai']);
    $unprivileged_user = $this->createUser();
    if (!$privileged_user instanceof User || !$unprivileged_user instanceof User) {
      throw new \Exception('Failed to create test users');
    }
    $this->privilegedUser = $privileged_user;
    $this->unprivilegedUser = $unprivileged_user;

    $this->mockTempStore = $this->createMock(CanvasAiTempStore::class);
    $this->container->set('canvas_ai.tempstore', $this->mockTempStore);

    $this->pageBuilderHelper = $this->createPageBuilderMock();
    $this->container->set('canvas_ai.page_builder_helper', $this->pageBuilderHelper);
  }

  /**
   * Creates a mock PageBuilderHelper.
   */
  protected function createPageBuilderMock(): CanvasAiPageBuilderHelper {
    $mock_helper = $this->getMockBuilder(CanvasAiPageBuilderHelper::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getComponentContextForAi'])
      ->getMock();

    // Mock component context data.
    $component_context_yaml = <<<YAML
sdc.xb_test_sdc.card:
  id: sdc.xb_test_sdc.card
  name: Card
  description: Card component
  group: ''
  props:
    title:
      name: Title
      description: Card title
      type: string
      default: ''
    content:
      name: Content
      description: Card content
      type: string
      default: ''
sdc.xb_test_sdc.text:
  id: sdc.xb_test_sdc.text
  name: Text
  description: Text component
  group: ''
  props:
    text:
      name: Text
      description: Text content
      type: string
      default: ''
YAML;

    $mock_helper->expects($this->any())
      ->method('getComponentContextForAi')
      ->willReturn($component_context_yaml);

    return $mock_helper;
  }

  /**
   * Tests the tool output.
   */
  public function testSetTemplateDataTool(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    $valid_yaml = <<<YAML
content:
  - sdc.xb_test_sdc.card:
      props:
        title: 'Test Card'
        content: 'Test content'
sidebar:
  - sdc.xb_test_sdc.text:
      props:
        text: 'Sidebar text'
YAML;

    $mock_layout = [
      "layout" => [
        "content" => [
          "nodePathPrefix" => [0],
          "components" => [],
        ],
        "sidebar" => [
          "nodePathPrefix" => [1],
          "components" => [],
        ],
      ],
    ];
    $layout_json = \json_encode($mock_layout);

    $this->mockTempStore->expects($this->once())
      ->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn($layout_json);

    $expected_output = [
      'operations' => [
        [
          'operation' => 'ADD',
          'components' => [
            [
              'id' => 'sdc.xb_test_sdc.card',
              'nodePath' => [0, 0],
              'fieldValues' => [
                'title' => 'Test Card',
                'content' => 'Test content',
              ],
            ],
            [
              'id' => 'sdc.xb_test_sdc.text',
              'nodePath' => [1, 0],
              'fieldValues' => [
                'text' => 'Sidebar text',
              ],
            ],
          ],
        ],
      ],
    ];

    $tool = $this->functionCallManager->createInstance('canvas_ai:set_template_data');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('component_structure', $valid_yaml);
    $tool->execute();

    $result = $tool->getReadableOutput();
    $this->assertEquals(
      json_encode($expected_output),
      $result,
      "The component structure was not processed correctly"
    );
  }

  /**
   * Tests the tool output when reference nodepath is given.
   */
  public function testSetTemplateDataToolWithReferenceNodepath(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    $valid_yaml = <<<YAML
content:
  - sdc.xb_test_sdc.card:
      props:
        title: 'Test Card'
        content: 'Test content'
  - sdc.xb_test_sdc.text:
      props:
        text: 'Sidebar text'
YAML;

    $mock_layout = [
      "layout" => [
        "content" => [
          "nodePathPrefix" => [0],
          "components" => [
            [
              "name" => "sdc.xb_test_sdc.card",
              "uuid" => "9cadf75e-7116-444a-9d05-e3c86483d178",
              "nodePath" => [0, 0],
            ],
            [
              "name" => "sdc.xb_test_sdc.card",
              "uuid" => "ab9b70d7-554d-421a-8303-725f4f6a6b9c",
              "nodePath" => [0, 1],
            ],
          ],
        ],
      ],
    ];

    $this->mockTempStore->expects($this->once())
      ->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn(\json_encode($mock_layout));

    $expected_output = [
      'operations' => [
        [
          'operation' => 'ADD',
          'components' => [
            [
              'id' => 'sdc.xb_test_sdc.card',
              'nodePath' => [0, 2],
              'fieldValues' => [
                'title' => 'Test Card',
                'content' => 'Test content',
              ],
            ],
            [
              'id' => 'sdc.xb_test_sdc.text',
              'nodePath' => [0, 3],
              'fieldValues' => [
                'text' => 'Sidebar text',
              ],
            ],
          ],
        ],
      ],
    ];

    $tool = $this->functionCallManager->createInstance('canvas_ai:set_template_data');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('component_structure', $valid_yaml);
    // Set the reference nodepath.
    $tool->setContextValue('reference_component_nodepath', [0, 1]);
    $tool->execute();

    $result = $tool->getReadableOutput();
    $this->assertEquals(\json_encode($expected_output), $result);
  }

}
