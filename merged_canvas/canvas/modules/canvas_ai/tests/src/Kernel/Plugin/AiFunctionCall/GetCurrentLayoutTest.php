<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Drupal\canvas_ai\CanvasAiTempStore;
use Drupal\canvas_ai\CanvasAiPermissions;

/**
 * Tests for the GetCurrentLayout function call plugin.
 *
 * @group canvas_ai
 */
final class GetCurrentLayoutTest extends KernelTestBase {

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
   * Tests getting current layout with proper permissions when layout exists.
   */
  public function testGetCurrentLayoutWithPermissionsAndData(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_current_layout');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $mock_tempstore = $this->createMock(CanvasAiTempStore::class);
    $test_layout = '{ "layout": { "content": { "nodePathPrefix": [ 0 ], "components": [ { "name": "sdc.example.button", "uuid": "f35215d4-d9ff-45e9-a604-d3cd7d0328ea", "nodePath": [ 0, 0 ] }, { "name": "block.system_breadcrumb_block", "uuid": "6a3307f8-4c40-40f7-a26d-7b3098e2ab5b", "nodePath": [ 0, 1 ] }, { "name": "js.banner", "uuid": "f94bd8e6-c37e-4f34-a0f0-2968c0e7b871", "nodePath": [ 0, 2 ], "slots": { "f94bd8e6-c37e-4f34-a0f0-2968c0e7b871/slotA": { "components": [] } } } ] } } }';
    $mock_tempstore->expects($this->once())
      ->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn($test_layout);

    $this->container->set('canvas_ai.tempstore', $mock_tempstore);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_current_layout');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->execute();

    $result = $tool->getReadableOutput();
    $this->assertEquals($test_layout, $result);
  }

  /**
   * Tests getting current layout with proper permissions when no layout exists.
   */
  public function testGetCurrentLayoutWithPermissionsNoData(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_current_layout');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $mock_tempstore = $this->createMock(CanvasAiTempStore::class);
    $mock_tempstore->expects($this->once())
      ->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn(NULL);

    $this->container->set('canvas_ai.tempstore', $mock_tempstore);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_current_layout');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->execute();

    $result = $tool->getReadableOutput();
    $this->assertEquals('No layout currently stored.', $result);
  }

  /**
   * Tests getting current layout without proper permissions.
   */
  public function testGetCurrentLayoutWithoutPermissions(): void {
    $this->container->get('current_user')->setAccount($this->unprivilegedUser);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_current_layout');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Expect an exception to be thrown.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The current user does not have the right permissions to run this tool.');
    $tool->execute();
  }

  /**
   * Tests that the tool calls the tempstore service with correct key.
   */
  public function testTempstoreServiceCall(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    // The expectation is verified by the mock - if getData
    // wasn't called exactly once with the correct key, the test would fail.
    $mock_tempstore = $this->createMock(CanvasAiTempStore::class);
    $mock_tempstore->expects($this->once())
      ->method('getData')
      ->with(CanvasAiTempStore::CURRENT_LAYOUT_KEY)
      ->willReturn('test layout data');

    $this->container->set('canvas_ai.tempstore', $mock_tempstore);
    $tool = $this->functionCallManager->createInstance('canvas_ai:get_current_layout');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->execute();
  }

}
