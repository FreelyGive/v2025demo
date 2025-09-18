<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Drupal\canvas_ai\CanvasAiPageBuilderHelper;
use Drupal\canvas_ai\CanvasAiPermissions;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for the GetComponentContext function call plugin.
 *
 * @group canvas_ai
 */
final class GetComponentContextTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_agents',
    'canvas',
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
    $privileged_user = $this->createUser([CanvasAiPermissions::USE_CANVAS_AI]);
    $unprivileged_user = $this->createUser();
    if (!$privileged_user instanceof User || !$unprivileged_user instanceof User) {
      throw new \Exception('Failed to create test users');
    }
    $this->privilegedUser = $privileged_user;
    $this->unprivilegedUser = $unprivileged_user;
  }

  /**
   * Tests getting component context with proper permissions.
   */
  public function testGetComponentContextWithPermissions(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);
    $mock_helper = $this->createMock(CanvasAiPageBuilderHelper::class);
    $test_data = [
      'test_component' => [
        'name' => 'Test Component',
        'description' => 'A test component for testing',
        'id' => 'test_component',
      ],
    ];
    $expected_yaml = Yaml::dump($test_data, 4, 2);
    $mock_helper->expects($this->once())
      ->method('getComponentContextForAi')
      ->willReturn($expected_yaml);
    $this->container->set('canvas_ai.page_builder_helper', $mock_helper);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_component_context');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->execute();
    $result = $tool->getReadableOutput();
    $this->assertEquals($expected_yaml, $result);
  }

  /**
   * Tests getting component context without proper permissions.
   */
  public function testGetComponentContextWithoutPermissions(): void {
    $this->container->get('current_user')->setAccount($this->unprivilegedUser);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_component_context');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    // Expect an exception to be thrown.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The current user does not have the right permissions to run this tool.');
    $tool->execute();
  }

  /**
   * Tests that the tool calls the page builder helper service.
   */
  public function testPageBuilderHelperServiceCall(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    // The expectation is verified by the mock - if getComponentContextForAi
    // wasn't called exactly once, the test would fail.
    $mockHelper = $this->createMock(CanvasAiPageBuilderHelper::class);
    $mockHelper->expects($this->once())
      ->method('getComponentContextForAi')
      ->willReturn(Yaml::dump(['test' => 'data'], 4, 2));

    $this->container->set('canvas_ai.page_builder_helper', $mockHelper);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_component_context');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->execute();
  }

}
