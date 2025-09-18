<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\canvas_ai\Traits\FunctionalCallTestTrait;
use Drupal\user\Entity\User;
use Drupal\canvas_ai\CanvasAiPermissions;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests for the SetAIGeneratedComponentStructure function call plugin.
 *
 * @group canvas_ai
 */
final class SetAIGeneratedComponentStructureTest extends KernelTestBase {

  use FunctionalCallTestTrait;
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
    $this->container->get(ModuleInstallerInterface::class)->install(['canvas_test_sdc']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', 'stark')
      ->save();
  }

  /**
   * Tests setting component structure with proper permissions and valid data.
   */
  public function testSetComponentStructureWithPermissionsAndValidData(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    $valid_yaml = <<<YAML
reference_nodepath: [0, 0, 1, 0]
placement: 'below'
components:
  - sdc.canvas_test_sdc.heading:
      props:
        text: 'Some text'
        element: 'h1'
YAML;

    $expected_output = [
      'operations' => [
        [
          'operation' => 'ADD',
          'components' => [
            [
              'id' => 'sdc.canvas_test_sdc.heading',
              'nodePath' => [0, 0, 1, 1],
              'fieldValues' => [
                'text' => 'Some text',
                'element' => 'h1',
              ],
            ],
          ],
        ],
      ],
      'message' => 'The changes have been made.',
    ];

    $result = $this->getComponentToolOutput($valid_yaml);
    $this->assertEquals(Yaml::dump($expected_output), $result);
  }

  /**
   * Tests setting component structure without proper permissions.
   */
  public function testSetComponentStructureWithoutPermissions(): void {
    $this->container->get('current_user')->setAccount($this->unprivilegedUser);

    $tool = $this->functionCallManager->createInstance('canvas_ai:set_component_structure');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    // Expect an exception to be thrown.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The current user does not have the right permissions to run this tool.');

    $tool->setContextValue('component_structure', 'test: value');
    $tool->execute();
  }

  /**
   * Tests setting component structure with invalid reference nodepath.
   */
  public function testSetComponentStructureWithInvalidReferenceNodepath(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    $invalid_yaml = <<<YAML
reference_nodepath: [0, 1, 2]  # Invalid because it has an odd number of elements
placement: 'below'
components:
  - id: 'sdc.canvas_test_sdc.card'
    name: 'Card'
YAML;

    $result = $this->getComponentToolOutput($invalid_yaml);
    $this->assertStringContainsString('Failed to process layout data:', $result);
    $this->assertStringContainsString('is incomplete and missing elements', $result);
  }

  /**
   * Tests setting component structure with invalid YAML.
   */
  public function testSetComponentStructureWithInvalidYaml(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    $invalid_yaml = <<<YAML
reference_nodepath: [0, 0]
components:
  - id: 'sdc.canvas_test_sdc.card'
    name: 'Card'
    props:
      title: 'Test Card'
      content: [invalid: yaml: structure
YAML;

    $result = $this->getComponentToolOutput($invalid_yaml);
    $this->assertStringContainsString('Failed to process layout data:', $result);
  }

  /**
   * Tests setting component structure with invalid component validation.
   */
  public function testSetComponentStructureWithInvalidComponents(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    $valid_yaml = <<<YAML
reference_nodepath: [1, 0, 1, 0]
placement: 'above'
components:
  - invalid.component.id:
      props:
        title: 'Invalid Component'
YAML;

    $result = $this->getComponentToolOutput($valid_yaml);
    $this->assertSame("Failed to process layout data: Component validation errors: components.0.[invalid.component.id]: The 'canvas.component.invalid.component.id' config does not exist.", self::normalizeErrorString($result));

    $invalid_nested_component = <<<YAML
reference_nodepath: [0, 4, 0, 3, 1, 0]
placement: above
components:
  - sdc.canvas_test_sdc.two_column:
      props:
        width: 50
      slots:
        column_one:
          - sdc.canvas_test_sdc.invalid_component:
              props:
                heading: 'My Hero'
                subheading: 'SubSnub'
                cta1href: 'https://example.com'
                cta1: 'View it!'
                cta2: 'Click it!'
YAML;
    $result = $this->getComponentToolOutput($invalid_nested_component);
    $this->assertSame("Failed to process layout data: Component validation errors: components.0.[sdc.canvas_test_sdc.two_column].slots.column_one.0.[sdc.canvas_test_sdc.invalid_component]: The 'canvas.component.sdc.canvas_test_sdc.invalid_component' config does not exist.", self::normalizeErrorString($result));
  }

  public function testValidateComponent(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    $invalid_yaml = <<<YAML
reference_nodepath: [1, 0, 1, 0]
placement: 'above'
components:
  - sdc.canvas_test_sdc.my-hero:
      props:
        subheading: 'SubSnub'
        cta1: 'View it!'
        cta1href: 'https://canvas-example.com'
        cta2: 'Click it!'
YAML;

    $result = $this->getComponentToolOutput($invalid_yaml);
    $this->assertSame("Failed to process layout data: Component validation errors: components.0.[sdc.canvas_test_sdc.my-hero].props.heading: The property heading is required.", self::normalizeErrorString($result));
    // Ensure we gracefully 'props' not being set.
    $decoded = Yaml::parse($invalid_yaml);
    unset($decoded['components'][0]['sdc.canvas_test_sdc.my-hero']['props']);
    $result = $this->getComponentToolOutput(Yaml::dump($decoded));
    $this->assertSame('Failed to process layout data: Component validation errors: components.0.[sdc.canvas_test_sdc.my-hero].props.heading: The property heading is required. components.0.[sdc.canvas_test_sdc.my-hero].props.cta1href: The property cta1href is required.', self::normalizeErrorString($result));

    $invalid_nested_yaml = <<<YAML
reference_nodepath: [0, 4, 0, 3, 1, 0]
placement: above
components:
  - sdc.canvas_test_sdc.two_column:
      props:
        width: 50
      slots:
        column_one:
          - sdc.canvas_test_sdc.my-hero:
              props:
                heading: 'My Hero'
                subheading: 'SubSnub'
                cta1: 'View it!'
                cta2: 'Click it!'
YAML;
    $result = $this->getComponentToolOutput($invalid_nested_yaml);
    $this->assertSame('Failed to process layout data: Component validation errors: components.0.[sdc.canvas_test_sdc.two_column].slots.column_one.0.[sdc.canvas_test_sdc.my-hero].props.cta1href: The property cta1href is required.', self::normalizeErrorString($result));

    // Ensure we error on invalid slot names.
    $decoded = Yaml::parse($invalid_nested_yaml);
    $decoded['components'][0]['sdc.canvas_test_sdc.two_column']['slots']['not_real_slot'] = $decoded['components'][0]['sdc.canvas_test_sdc.two_column']['slots']['column_one'];
    $invalid_slot_name_yaml = Yaml::dump($decoded);
    $result = $this->getComponentToolOutput($invalid_slot_name_yaml);
    $this->assertSame('Failed to process layout data: Component validation errors: components.0.[sdc.canvas_test_sdc.two_column]: Invalid component subtree. This component subtree contains an invalid slot name for component <em class="placeholder">sdc.canvas_test_sdc.two_column</em>: <em class="placeholder">not_real_slot</em>. Valid slot names are: <em class="placeholder">column_one, column_two</em>. components.0.[sdc.canvas_test_sdc.two_column].slots.column_one.0.[sdc.canvas_test_sdc.my-hero].props.cta1href: The property cta1href is required. components.0.[sdc.canvas_test_sdc.two_column].slots.not_real_slot.0.[sdc.canvas_test_sdc.my-hero].props.cta1href: The property cta1href is required.', self::normalizeErrorString($result));
  }

  /**
   * Tests component structure with nested slots.
   */
  public function testComponentStructureWithNestedSlots(): void {
    $this->container->get('current_user')->setAccount($this->privilegedUser);

    $nested_yaml = <<<YAML
reference_nodepath: [0, 4, 0, 3, 1, 0]
placement: above
components:
  - sdc.canvas_test_sdc.two_column:
      props:
        width: 50
      slots:
        column_one:
          - sdc.canvas_test_sdc.heading:
              props:
                text: 'Some text'
                element: 'h1'
YAML;

    $expected_output = [
      'operations' => [
        [
          'operation' => 'ADD',
          'components' => [
            [
              'id' => 'sdc.canvas_test_sdc.two_column',
              'nodePath' => [0, 4, 0, 3, 1, 0],
              'fieldValues' => ['width' => 50],
            ],
            [
              'id' => 'sdc.canvas_test_sdc.heading',
              'nodePath' => [0, 4, 0, 3, 1, 0, 0, 0],
              'fieldValues' => ['text' => 'Some text', 'element' => 'h1'],
            ],
          ],
        ],
      ],
      'message' => 'The changes have been made.',
    ];
    $result = $this->getComponentToolOutput($nested_yaml);
    $this->assertEquals(Yaml::dump($expected_output), $result);
  }

  private function getComponentToolOutput(string $yaml): string {
    return $this->getToolOutput('canvas_ai:set_component_structure', ['component_structure' => $yaml]);
  }

}
