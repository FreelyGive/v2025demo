<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for the AddMetadata function call plugin.
 *
 * @group canvas_ai
 */
class AddMetadataTest extends KernelTestBase {

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
   * Test generating metadata successfully.
   */
  public function testAddMetadata(): void {
    $tool = $this->functionCallManager->createInstance('ai_agent:add_metadata');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $generated_metadata = 'This is metatag description';
    $expected_result = [
      'metadata' => [
        'metatag_description' => $generated_metadata,
      ],
    ];
    $tool->setContextValue('metadata', $generated_metadata);
    $tool->execute();
    $result = $tool->getReadableOutput();
    $this->assertIsString($result);

    $parsed_result = Yaml::parse($result);
    $this->assertArrayHasKey('metadata', $parsed_result);
    $this->assertEquals($expected_result, $parsed_result);
  }

}
