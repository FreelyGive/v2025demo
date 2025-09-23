<?php

declare(strict_types=1);

namespace Drupal\canvas\Element;

use Drupal\Component\Utility\Html;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element for an Astro island web component.
 *
 * @see https://docs.astro.build/en/concepts/islands/
 *
 * Properties:
 * - #uuid: A unique ID for this island.
 * - #component_url: URL of component to hydrate. This will be a JavaScript
 *   file.
 * - #name: A name for the component.
 * - #props: Array of properties for the JavaScript component where the keys are
 *   the prop names and the values are the prop values. Only values that can be
 *   serialized to JSON are supported - such as scalar values or objects that
 *   implement \JsonSerializable.
 *   #slots: Array of child slots for the JavaScript component. The slots are
 *   keyed by their name. In the case of frameworks like React and Preact that
 *   only support a single child slot, this slot should be named 'default'. The
 *   values represent the content to be rendered into the slot and should be
 *   valid render arrays or a string. String values will be treated as plain
 *   text.
 * - #preview: A boolean to indicate whether the rendered component should use
 *   the draft version. Defaults to FALSE.
 * - #framework: Name of the framework to use when rehydrating. Only 'preact' is
 *   supported at present.
 * - #import_maps: Keyed array of importmap entries where the keys are the bare
 *   import names and the values are the resolved URL.
 *
 * @see \Drupal\canvas\Render\ImportMapResponseAttachmentsProcessor::processAttachments
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/script/type/importmap
 *
 * Usage example:
 * @code
 * $build['recital_final'] = [
 *   '#type' => 'astro_island',
 *   '#uuid' => 'da6bf2a2-3d4b-42a2-bb05-03a0e33a2d79',
 *   '#name' => 'Jazz Hands (elite)',
 *   '#component_url' => '/uri/to/jazz-hands-elite.js',
 *   '#props' => [
 *     'oscillation_size' => 'extremely_animated',
 *     'oscillations' => 12,
 *     'finale_routine' => ['jump:large', 'splits:full', 'fist_pump'],
 *    ],
 *   '#slots' => [
 *     'default' => "We're off to the regionals Janet!',
 *    ],
 *   '#import_maps' => [
 *     'preact' => '/path/to/preact.js',
 *     'emoji' => '/path/to/emoji.js',
 *   ],
 * ];
 * @endcode
 */
#[RenderElement(self::PLUGIN_ID)]
final class AstroIsland extends RenderElementBase {

  public const PLUGIN_ID = 'astro_island';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#pre_render' => [
        [static::class, 'preRenderIsland'],
      ],
      '#slots' => [],
      '#props' => [],
      '#framework' => 'preact',
      '#preview' => FALSE,
    ];
  }

  /**
   * Pre-render callback.
   */
  public static function preRenderIsland(array $element): array {
    $component_url = $element['#component_url'] ?? NULL;
    if ($component_url === NULL) {
      return ['#plain_text' => \sprintf('You must pass a #component_url for an element of #type %s', self::PLUGIN_ID)];
    }

    $component_name = $element['#name'] ?? NULL;
    if ($component_name === NULL) {
      return ['#plain_text' => \sprintf('You must pass a #name for an element of #type %s', self::PLUGIN_ID)];
    }

    $client = \Drupal::service(LibraryDiscoveryInterface::class)->getLibraryByName('canvas', 'astro.client');
    assert(isset($client['js'][0]['data']) && count($client['js']) === 1);
    $renderer_url = base_path() . $client['js'][0]['data'];

    $mapped_props = \array_map(static fn(mixed $prop_value): array => [
      'raw',
      $prop_value,
    ],
      \array_diff_key($element['#props'], \array_flip(['canvas_uuid', 'canvas_slot_ids', 'canvas_is_preview']))
    );
    $element['#attached']['library'][] = 'canvas/astro.hydration';
    if (\count($mapped_props) === 0) {
      // We must always represent props as an object in JSON notation. We can't
      // use the JSON_FORCE_OBJECT flag because that will force
      // ['raw', $prop_value] to be an object too. So in the case where there
      // are no props, we need to use \stdClass so that is represented as {}
      // instead of [] in JSON.
      $mapped_props = new \stdClass();
    }
    $build = [
      '#type' => 'inline_template',
      // Generate a template by turning slots into named variables.
      '#template' => self::generateTemplate(\array_keys($element['#slots'] ?? [])),
      '#context' => [
          // Prefix all context variables with __aie to avoid collisions with
          // slots.
        '__aie_uuid' => $element['#uuid'] ?? \Drupal::service(UuidInterface::class)
          ->generate(),
        '__aie_component_url' => $component_url,
        '__aie_renderer' => $renderer_url,
        '__aie_props' => \json_encode($mapped_props, JSON_THROW_ON_ERROR),
        '__aie_opts' => \json_encode([
          'name' => $component_name,
          'value' => $element['#framework'] ?? 'preact',
        ], JSON_THROW_ON_ERROR),
          // Add slots as named variables so the point they're printed can be
          // wrapped by CanvasWrapperNode and any passed meta props to enable
        // CanvasWrapperNode to wrap slots with HTML comments.
      ] + \array_map(static fn(array|string $slot) => \is_array($slot) ? $slot : ['#plain_text' => $slot], $element['#slots'] ?? []) +
      \array_intersect_key($element['#props'] ?? [], \array_flip(['canvas_uuid', 'canvas_slot_ids', 'canvas_is_preview'])),
    ];
    // Return this as a new child element so that process callbacks are executed
    // for the new render array.
    $element['inline-template'] = $build;
    // Scope any import-maps.
    if (\array_key_exists('#import_maps', $element)) {
      // Convert these to attachments that can be processed.
      // @see \Drupal\canvas\Render\ImportMapResponseAttachmentsProcessor::processAttachments
      $element['#attached']['import_maps'] = $element['#import_maps'];
    }
    $element['#attached']['html_head_link'][] = [
      [
        'rel' => 'modulepreload',
        'fetchpriority' => 'high',
        'href' => $component_url,
      ],
    ];
    return $element;
  }

  /**
   * Builds inline template.
   *
   * @param string[]|int[] $slot_names
   *   Slot names.
   *
   * @return string
   */
  protected static function generateTemplate(array $slot_names): string {
    $has_slots = !empty($slot_names);
    $template = '<canvas-island uid="{{ __aie_uuid }}"
      component-url="{{ __aie_component_url }}"
      component-export="default"'
      . ($has_slots ? ' await-children=""' : '') . '
      renderer-url="{{ __aie_renderer }}"
      props="{{ __aie_props }}"
      ssr="" client="only"
      opts="{{ __aie_opts }}">';

    // Reduce layout shift by blocking further document rendering until the
    // renderer-url and component-url scripts are loaded, so that fetching them
    // doesn't add delay between a rendering with the island blank and the
    // hydrated rendering. This doesn't eliminate layout shift entirely,
    // because with Astro's client="only" directive, Astro waits until the
    // entire page is loaded before hydrating islands.
    // @todo Investigate if it's possible to hydrate islands immediately
    //   after the <canvas-island> element is parsed rather than on page load.
    $template .= '<script type="module" src="{{ __aie_renderer }}" blocking="render"></script>';
    $template .= '<script type="module" src="{{ __aie_component_url }}" blocking="render"></script>';

    foreach ($slot_names as $slot_name) {
      // Prevent XSS via malicious render array.
      $escaped_slot_name = Html::escape((string) $slot_name);
      if ($slot_name === 'default') {
        $template .= \sprintf('<template data-astro-template>{{ %s }}</template>', $escaped_slot_name);
        continue;
      }
      $template .= \sprintf('<template data-astro-template="%s">{{ %s }}</template>', $escaped_slot_name, $escaped_slot_name);
    }
    $template .= '</canvas-island>';
    return $template;
  }

}
