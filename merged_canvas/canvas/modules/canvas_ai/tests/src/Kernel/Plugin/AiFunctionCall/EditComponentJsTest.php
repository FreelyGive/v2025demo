<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\Component\Serialization\Json;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Tests\canvas_ai\Traits\FunctionalCallTestTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for the EditComponentJs function call plugin.
 *
 * @group canvas_ai
 */
final class EditComponentJsTest extends KernelTestBase {

  use FunctionalCallTestTrait;

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
    $js_component = JavaScriptComponent::create([
      'machineName' => 'existing_component',
      'name' => 'Existing Component',
      'status' => FALSE,
      'props' => [],
      'required' => [],
      'slots' => [],
      'js' => [
        'original' => 'console.log("hey");',
        'compiled' => 'console.log("hey");',
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => '.test { display: none; }',
      ],
      'dataDependencies' => [],
    ]);
    $js_component->save();
  }

  /**
   * Test editing component JavaScript successfully.
   */
  public function testEditComponentJs(): void {
    $tool = $this->functionCallManager->createInstance('ai_agent:edit_component_js');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $js_content = 'console.log("Hello World"); const component = { init: () => {} };';
    $props_metadata = Json::encode([
      [
        'id' => 'title',
        'name' => 'Title',
        'type' => 'string',
        'example' => 'Sample Title',
      ],
      [
        'id' => 'count',
        'name' => 'Count',
        'type' => 'number',
        'example' => 5,
      ],
    ]);

    $tool->setContextValue('javascript', $js_content);
    $tool->setContextValue('props_metadata', $props_metadata);
    $tool->setContextValue('component_machine_name', 'existing_component');
    $tool->execute();
    $result = $tool->getReadableOutput();

    $this->assertIsString($result);
    $parsed_result = Yaml::parse($result);

    $this->assertArrayHasKey('js_structure', $parsed_result);
    $this->assertArrayHasKey('props_metadata', $parsed_result);
    $this->assertEquals($js_content, $parsed_result['js_structure']);
    $this->assertEquals($props_metadata, $parsed_result['props_metadata']);
  }

  public function testComponentValidation(): void {
    $component_machine_name = 'existing_component';
    $javascript = 'console.log("Hello World");';
    $props_metadata = Json::encode([
      [
        'id' => 'title',
        'name' => 'Title',
        'type' => 'string',
        'example' => 1,
      ],
      [
        'id' => 'count',
        'name' => 'Count',
        'type' => 'integer',
        // 'example' will be transformed into 'examples' array.
        'example' => 'four',
      ],
    ]);
    $result = $this->getToolOutput(
      'ai_agent:edit_component_js',
      [
        'javascript' => $javascript,
        'props_metadata' => $props_metadata,
        'component_machine_name' => $component_machine_name,
      ]
    );
    self::assertYamlError($result, 'Component validation errors: component_structure.: Prop "title" has invalid example value: [] Integer value found, but a string or an object is required component_structure.: Prop "count" has invalid example value: [] String value found, but an integer or an object is required component_structure.props.count.examples.0: This value should be of the correct primitive type.');
  }

  /**
   * Asserts that the tool result contains a YAML error message.
   *
   * CanvasBuilder expects the tool result to always be a YAML parsable string.
   *
   * @param string $toolResult
   *   The tool result.
   * @param string $expectedError
   *   The expected error message.
   *
   * @return void
   *
   * @see \Drupal\canvas_ai\Controller\CanvasBuilder::render()
   */
  private function assertYamlError(string $toolResult, string $expectedError): void {
    $yaml = Yaml::parse($toolResult);
    self::assertIsArray($yaml);
    self::assertCount(1, $yaml);
    self::assertSame("Failed to process Javascript component data: $expectedError", $this->normalizeErrorString($yaml['error']));
  }

}
