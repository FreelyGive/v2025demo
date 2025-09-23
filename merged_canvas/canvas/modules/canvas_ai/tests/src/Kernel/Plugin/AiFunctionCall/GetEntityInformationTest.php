<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Yaml\Yaml;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

/**
 * Tests for the GetEntityInformation function call plugin.
 *
 * @group canvas_ai
 */
final class GetEntityInformationTest extends KernelTestBase {

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
   * Test getting entity information with valid information.
   */
  public function testGetEntityInformation(): void {
    $tool = $this->functionCallManager->createInstance('ai_agent:get_entity_information');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $entity_type = 'node';
    $entity_id = 42;
    $selected_component = 'Hero component';
    $layout = 'The layout for the page';
    $tool->setContextValue('entity_type', $entity_type);
    $tool->setContextValue('entity_id', $entity_id);
    $tool->setContextValue('selected_component', $selected_component);
    $tool->setContextValue('layout', $layout);
    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertIsString($result);
    $parsed_result = Yaml::parse($result);

    $this->assertEquals($entity_type, $parsed_result['entity_type']);
    $this->assertEquals($entity_id, $parsed_result['entity_id']);
    $this->assertEquals($selected_component, $parsed_result['selected_component']);
    $this->assertEquals($layout, $parsed_result['layout']);
  }

}
