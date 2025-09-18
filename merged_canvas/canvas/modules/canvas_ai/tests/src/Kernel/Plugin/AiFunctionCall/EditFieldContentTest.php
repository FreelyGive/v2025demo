<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

/**
 * Tests for the EditFieldContentTest function call plugin.
 *
 * @group canvas_ai
 */
final class EditFieldContentTest extends KernelTestBase {

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
   * Test editing field content successfully.
   */
  public function testEditFieldContent(): void {
    $tool = $this->functionCallManager->createInstance('ai_agent:edit_field_content');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $refined_content = [
      'refined_text' => 'Hello World!',
      'field_name' => 'field_title',
    ];
    $tool->setContextValue('text_value', $refined_content['refined_text']);
    $tool->setContextValue('field_name', $refined_content['field_name']);
    $tool->execute();
    $result = $tool->getReadableOutput();
    $this->assertIsString($result);

    $parsed_result = Yaml::parse($result);

    $this->assertArrayHasKey('refined_text', $parsed_result);
    $this->assertArrayHasKey('field_name', $parsed_result);
    $this->assertEquals($refined_content, $parsed_result);
  }

}
