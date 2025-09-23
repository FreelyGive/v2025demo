<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

/**
 * Tests for the CreateFieldContent function call plugin.
 *
 * @group canvas_ai
 */
final class CreateFieldContentTest extends KernelTestBase {

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $functionCallManager;

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
    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
  }

  /**
   * Test creating field content successfully.
   */
  public function testCreateFieldContent(): void {
    $tool = $this->functionCallManager->createInstance('ai_agent:create_field_content');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('field_content', 'Hello World!');
    $tool->setContextValue('field_name', 'field_title');
    $tool->execute();
    $result = $tool->getReadableOutput();
    $this->assertIsString($result);

    $parsed_result = Yaml::parse($result);

    $this->assertArrayHasKey('created_content', $parsed_result);
    $this->assertEquals('Hello World!', $parsed_result['created_content']);
    $this->assertEquals('field_title', $parsed_result['field_name']);
  }

}
