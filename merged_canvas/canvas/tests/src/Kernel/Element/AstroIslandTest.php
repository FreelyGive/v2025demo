<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Element;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Cache\CacheCollectorInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\canvas\Element\AstroIsland;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Render\ImportMapResponseAttachmentsProcessor;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\CrawlerTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests Island.
 *
 * @covers \Drupal\canvas\Element\AstroIsland
 * @group JavaScriptComponents
 * @group canvas
 */
final class AstroIslandTest extends KernelTestBase {

  use CrawlerTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['canvas', 'user', 'system', 'media'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system']);
  }

  /**
   * Covers AstroIsland.
   */
  public function testAstroIsland(): void {
    $css = '.test{display:none;}';
    $js = 'console.log("Test")';
    $component = JavaScriptComponent::create([
      'machineName' => $this->randomMachineName(),
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => TRUE,
      'props' => [
        'text' => [
          'type' => 'string',
          'title' => 'Title',
          'examples' => ['Press', 'Submit now'],
        ],
        'count' => [
          'type' => 'integer',
          'title' => 'Count',
          'examples' => [1, 2],
        ],
      ],
      'slots' => [
        'default' => [
          'title' => 'result',
          'description' => 'Result',
          'examples' => [
            'You win a pony 🐴!',
            'Have a pony! 🐴',
          ],
        ],
        'error' => [
          'title' => 'error',
          'description' => 'Error',
          'examples' => [
            'Oh no Dave, no ponies for you',
            'Not with those shoes mate',
          ],
        ],
      ],
      'js' => [
        'original' => $js,
        'compiled' => $js,
      ],
      'css' => [
        'original' => '.test { display: none; }',
        'compiled' => $css,
      ],
      'dataDependencies' => [],
    ]);
    $component->save();

    $css_hash = Crypt::hmacBase64($css, $component->uuid());
    $js_hash = Crypt::hmacBase64($js, $component->uuid());
    $discovery = \Drupal::service(LibraryDiscoveryInterface::class);
    assert($discovery instanceof CacheCollectorInterface);
    self::assertArrayHasKey('astro_island.' . $component->id(), $discovery->get('canvas'));
    self::assertStringEqualsFile('assets://astro-island/' . $css_hash . '.css', $css);
    self::assertStringEqualsFile('assets://astro-island/' . $js_hash . '.js', $js);

    $uid = $this->randomMachineName();
    $props = [
      'text' => 'Win a pony',
      'count' => '3',
    ];
    $component_url = \sprintf('%s/%s.js', $this->randomMachineName(), $this->randomMachineName());
    $island = [
      '#type' => AstroIsland::PLUGIN_ID,
      '#uuid' => $uid,
      '#component_url' => $component_url,
      '#name' => $component->label(),
      '#props' => $props,
      '#import_maps' => [
        ImportMapResponseAttachmentsProcessor::SCOPED_IMPORTS => [
          $component_url => ['some' => 'import/map.js'],
        ],
      ],
      '#slots' => [
        'default' => ['#markup' => '<em>3 ponies won this week!</em>'],
        'error' => 'No pony for you!',
      ],
      '#framework' => 'preact',
    ];
    $original_island = $island;

    $crawler = $this->crawlerForRenderArray($island);
    $element = $crawler->filter('canvas-island');
    self::assertEquals([
      ImportMapResponseAttachmentsProcessor::SCOPED_IMPORTS => [$component_url => ['some' => 'import/map.js']],
    ], $island['#attached']['import_maps']);
    self::assertCount(1, $element);

    self::assertEquals($uid, $element->attr('uid'));
    self::assertEquals('default', $element->attr('component-export'));
    self::assertEquals('', $element->attr('ssr'));
    self::assertEquals('only', $element->attr('client'));
    self::assertJsonStringEqualsJsonString(Json::encode([
      'text' => ['raw', 'Win a pony'],
      'count' => ['raw', '3'],
    ]), $element->attr('props') ?? '');
    self::assertJsonStringEqualsJsonString(Json::encode([
      'name' => $component->label(),
      'value' => $island['#framework'],
    ]), $element->attr('opts') ?? '');

    self::assertEquals($component_url, $element->attr('component-url'));

    $canvas_directory = $this->container->get(ExtensionPathResolver::class)->getPath('module', 'canvas');
    self::assertEquals(\sprintf('/%s/ui/lib/astro-hydration/dist/client.js', $canvas_directory), $element->attr('renderer-url'));

    $slots = $element->filter('template[data-astro-template]');
    self::assertCount(2, $slots);

    $default_slot = $slots->first();
    self::assertSame('', $default_slot->attr('data-astro-template'));
    $em = $default_slot->filter('em');
    self::assertCount(1, $em);
    self::assertEquals('3 ponies won this week!', $em->text());

    $error_slot = $slots->last();
    self::assertEquals('error', $error_slot->attr('data-astro-template'));
    self::assertEquals('No pony for you!', $error_slot->text());

    // Should still work without slots, props, framework and UUID.
    $island = $original_island;
    unset($island['#slots'], $island['#props'], $island['#uuid'], $island['#framework']);
    $crawler = $this->crawlerForRenderArray($island);
    $element = $crawler->filter('canvas-island');
    self::assertCount(1, $element);
    self::assertNotNull($element->attr('uid'));
    self::assertJsonStringEqualsJsonString('{}', $element->attr('props') ?? '');
    self::assertCount(0, $element->filter('template[data-astro-template]'));
    self::assertJsonStringEqualsJsonString(Json::encode([
      'name' => $component->label(),
      'value' => 'preact',
    ]), $element->attr('opts') ?? '');
  }

  /**
   * Ensure no library is created or attached if no CSS is present.
   */
  public function testEmptyCss(): void {
    $component = JavaScriptComponent::create([
      'machineName' => $this->randomMachineName(),
      'name' => $this->getRandomGenerator()->sentences(5),
      'status' => TRUE,
      'props' => [],
      'slots' => [],
      'js' => ['original' => '', 'compiled' => ''],
      'css' => [
        'original' => '',
        // Whitespace only CSS should be ignored.
        'compiled' => "\n  \n",
      ],
      'dataDependencies' => [],
    ]);
    $component->save();

    $discovery = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.1',
      fn () => \Drupal::service(LibraryDiscoveryInterface::class),
      // @phpstan-ignore-next-line
      fn () => \Drupal::service('library.discovery.collector'),
    );
    assert($discovery instanceof CacheCollectorInterface);
    self::assertArrayHasKey('astro_island.' . $component->id(), $discovery->get('canvas'));

    $island = [
      '#type' => AstroIsland::PLUGIN_ID,
      '#component_url' => '/lorem/ipsum.js',
      '#name' => 'placeholder',
    ];
    $this->crawlerForRenderArray($island);
    self::assertSame([
      'canvas/astro.hydration',
    ], $island['#attached']['library']);
  }

  /**
   * Covers AstroIsland.
   */
  public function testInvalidElement(): void {
    // Missing key.
    $island = [
      '#type' => AstroIsland::PLUGIN_ID,
    ];
    $crawler = $this->crawlerForRenderArray($island);
    self::assertEquals('You must pass a #component_url for an element of #type astro_island', $crawler->text());

    // No component name.
    $island = [
      '#type' => AstroIsland::PLUGIN_ID,
      '#component_url' => 'zero_sum',
    ];
    $crawler = $this->crawlerForRenderArray($island);
    self::assertEquals('You must pass a #name for an element of #type astro_island', $crawler->text());
  }

}
