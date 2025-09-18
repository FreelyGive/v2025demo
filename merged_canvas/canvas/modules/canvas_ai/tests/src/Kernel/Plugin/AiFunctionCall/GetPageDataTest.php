<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Kernel\Plugin\AiFunctionCall;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for the GetPageData function call plugin.
 *
 * @group canvas_ai
 */
final class GetPageDataTest extends KernelTestBase {

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
    'system',
    'user',
    'ai',
    'ai_agents',
    'canvas',
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
   * Tests fetching a field from an entity.
   */
  public function testPageData(): void {
    $tool = $this->functionCallManager->createInstance('ai_agent:get_page_data');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    $tool->setContextValue('page_title', 'Title of the page');
    $tool->setContextValue('page_description', 'Description of the page');
    $tool->execute();

    $result = $tool->getReadableOutput();
    $parsed_result = Yaml::parse($result);

    $this->assertArrayHasKey('page_title', $parsed_result);
    $this->assertEquals('Title of the page', $parsed_result['page_title']);

    $this->assertArrayHasKey('page_description', $parsed_result);
    $this->assertEquals('Description of the page', $parsed_result['page_description']);
  }

}
