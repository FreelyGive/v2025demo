<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Extension;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\canvas\Element\AstroIsland;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests CanvasTwigExtension.
 *
 * @group canvas
 * @group Twig
 */
final class CanvasTwigExtensionTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'canvas_test_sdc',
    'user',
    'system',
    'block',
    // Canvas's dependencies (modules providing field types + widgets).
    'datetime',
    'file',
    'image',
    'media',
    'options',
    'path',
    'link',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system']);
  }

  /**
   * @covers \Drupal\canvas\Extension\CanvasTwigExtension
   * @covers \Drupal\canvas\Extension\CanvasPropVisitor
   * @dataProvider providerComponents
   */
  public function testExtension(
    string $type,
    string $component_id,
    bool $props_handled_by_twig,
    string $slot_selector,
    array $render_array_additions = [],
    bool $is_preview = FALSE,
  ): void {
    $heading = $this->randomMachineName();
    $uuid = $this->container->get(UuidInterface::class)->generate();
    (match ($type) {
      AstroIsland::PLUGIN_ID => fn ($component_id) => JavaScriptComponent::create([
        'machineName' => $component_id,
        'name' => $this->getRandomGenerator()->sentences(5),
        'status' => TRUE,
        'props' => [
          'heading' => [
            'type' => 'string',
            'title' => 'Heading',
            'examples' => ['A heading'],
          ],
        ],
        'slots' => [
          'the_body' => [
            'title' => 'Body',
            'description' => 'Body content',
            'examples' => [
              'Lorem ipsum',
            ],
          ],
        ],
        'css' => [],
        'js' => [],
        'dataDependencies' => [],
      ])->save(),
      default => fn() => NULL,
    })($component_id);
    $body = $this->getRandomGenerator()->sentences(10);
    $build = [
      '#type' => $type,
      '#component' => $component_id,
      '#props' => [
        'heading' => $heading,
        'canvas_uuid' => $uuid,
        'canvas_slot_ids' => ['the_body'],
        'canvas_is_preview' => $is_preview,
      ],
      '#slots' => [
        'the_body' => [
          '#markup' => $body,
        ],
      ],
    ] + $render_array_additions;
    $out = (string) $this->container->get(RendererInterface::class)->renderInIsolation($build);
    $crawler = new Crawler($out);
    if ($props_handled_by_twig) {
      $h1 = $crawler->filter(\sprintf('h1:contains("%s")', $heading));
      self::assertCount(1, $h1);
      $h1Text = $h1->html();

      if ($is_preview) {
        self::assertMatchesRegularExpression('/^<!-- canvas-prop-start-(.*)\/heading -->/', $h1Text);
        self::assertMatchesRegularExpression('/canvas-prop-end-(.*)\/heading -->$/', $h1Text);
      }
      else {
        self::assertDoesNotMatchRegularExpression('/^<!-- canvas-prop-start-(.*)\/heading -->/', $h1Text);
        self::assertDoesNotMatchRegularExpression('/canvas-prop-end-(.*)\/heading -->$/', $h1Text);
      }
    }

    $bodySlot = $crawler->filter($slot_selector);
    self::assertCount(1, $bodySlot);
    // Normalize whitespace.
    $bodyHtml = \trim(\preg_replace('/\s+/', ' ', $bodySlot->html()) ?: '');
    self::assertStringContainsString($body, $bodyHtml);

    if ($is_preview) {
      self::assertMatchesRegularExpression('/^<!-- canvas-slot-start-(.*)\/the_body -->/', $bodyHtml);
      self::assertMatchesRegularExpression('/canvas-slot-end-(.*)\/the_body -->$/', $bodyHtml);
    }
    else {
      self::assertDoesNotMatchRegularExpression('/^<!-- canvas-slot-start-(.*)\/the_body -->/', $bodyHtml);
      self::assertDoesNotMatchRegularExpression('/canvas-slot-end-(.*)\/the_body -->$/', $bodyHtml);
    }
  }

  public static function providerComponents(): iterable {

    $sdc = [
      'component',
      'canvas_test_sdc:props-slots',
      TRUE,
      '.component--props-slots--body',
      [],
    ];

    yield 'SDC, preview' => [...$sdc, TRUE];
    yield 'SDC, live' => [...$sdc, FALSE];

    $js_component = [
      AstroIsland::PLUGIN_ID,
      'trousers',
      FALSE,
      'template[data-astro-template="the_body"]',
      ['#name' => 'trousers', '#component_url' => 'the/wrong/trousers.js'],
    ];

    yield 'JS Component, preview' => [...$js_component, TRUE];
    yield 'JS Component, live' => [...$js_component, FALSE];
  }

}
