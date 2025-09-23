<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\Tests\canvas_ai\Traits\FunctionalCallTestTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for the CreateComponent function call plugin.
 *
 * @group canvas_ai
 */
final class CreateComponentTest extends KernelTestBase {

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
  }

  /**
   * Test creating a new component successfully.
   */
  public function testCreateNewComponent(): void {
    $tool = $this->functionCallManager->createInstance('ai_agent:create_component');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $component_name = 'Test Component';
    $javascript = 'console.log("Hello World");';
    $css = '.test { color: red; }';
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

    $tool->setContextValue('component_name', $component_name);
    $tool->setContextValue('js_structure', $javascript);
    $tool->setContextValue('css_structure', $css);
    $tool->setContextValue('props_metadata', $props_metadata);
    $tool->execute();
    $result = $tool->getReadableOutput();
    $this->assertIsString($result);
    $parsed_result = Yaml::parse($result);

    $this->assertArrayHasKey('component_structure', $parsed_result);
    $component_structure = $parsed_result['component_structure'];
    $this->assertEquals($component_name, $component_structure['name']);
    $this->assertEquals('test_component', $component_structure['machineName']);
    $this->assertFalse($component_structure['status']);
    $this->assertEquals($javascript, $component_structure['sourceCodeJs']);
    $this->assertEquals($css, $component_structure['sourceCodeCss']);
    $this->assertEquals('', $component_structure['compiledJs']);
    $this->assertEquals('', $component_structure['compiledCss']);
    $this->assertEquals([], $component_structure['importedJsComponents']);
    $this->assertEquals([], $component_structure['dataDependencies']);

    $expected_props = [
      'title' => [
        'title' => 'Title',
        'type' => 'string',
        'examples' => ['Sample Title'],
      ],
      'count' => [
        'title' => 'Count',
        'type' => 'number',
        'examples' => [5],
      ],
    ];
    $this->assertEquals($expected_props, $component_structure['props']);
  }

  /**
   * Test that attempting to create a component with an existing name fails.
   */
  public function testCreateExistingComponentFails(): void {
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

    self::assertYamlError(
      $this->getToolOutput('ai_agent:create_component', ['component_name' => $js_component->id()]),
      'The component with same name already exists.'
    );
  }

  public function testComponentValidation(): void {
    $component_name = 'Invalid Component';
    $javascript = 'console.log("Hello World");';
    $css = '.test { color: red; }';
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
      'ai_agent:create_component',
      [
        'component_name' => $component_name,
        'js_structure' => $javascript,
        'css_structure' => $css,
        'props_metadata' => $props_metadata,
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
