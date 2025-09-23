<?php

declare(strict_types=1);

namespace Drupal\canvas\Render;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\AttachmentsInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;

/**
 * Defines a html attachments processor decorator that can handle import maps.
 *
 * Import maps can be attached to any render element using the 'import_maps' key
 * under '#attached'.
 * The array can take two keys 'imports' and 'scopes'. These correspond to the
 * two constants available on this class
 * self::GLOBAL_IMPORTS and self::SCOPED_IMPORTS.
 *
 * The entries under 'scopes' should be an array with a key of the scope and the
 * import map entries.
 *
 * The entries under 'imports' should be an array with the key of the import
 * name and the path it corresponds to.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/script/type/importmap
 *
 * @code
 *  use Drupal\canvas\Render\ImportMapResponseAttachmentsProcessor;
 *  $scope = '/modules/mymodule/js/file.js';
 *  $build['i_can_haz_imports'] = [
 *    '#type' => 'container',
 *    'foo' => ['#markup' => 'bar'],
 *    '#attached' => [
 *      'import_maps' => [
 *        ImportMapResponseAttachmentsProcessor::SCOPED_IMPORTS => [
 *          $scope => [
 *            'preact' => '/path/to/preact.js',
 *          ],
 *        ],
 *       ImportMapResponseAttachmentsProcessor::GLOBAL_IMPORTS => [
 *         'donkey-town' => '/path/to/donkey-town.js',
 *       ],
 *      ],
 *    ],
 *  ];
 * @endcode
 */
final class ImportMapResponseAttachmentsProcessor implements AttachmentsResponseProcessorInterface {

  public const string GLOBAL_IMPORTS = 'imports';
  public const string SCOPED_IMPORTS = 'scopes';

  public function __construct(
    private AttachmentsResponseProcessorInterface $htmlResponseAttachmentsProcessor,
  ) {

  }

  private static function flattenPaths(string|array $path): string {
    // Drupal will merge duplicate imports and cast them into an array. i.e. if
    // two elements have the same global import, rather than the result being a
    // single path, it will be an array of paths, one entry for each time it is
    // attached, we need to post-process this to turn it back into the format
    // expected by the import map spec. We give precedence to the first path
    // attached. If a component is rendered twice, its scoped imports will be
    // the same, but duplicated so picking the first one is fine. If global
    // imports are duplicated, we can only pick the first one. If components
    // need their own version of a library they should use a scoped import.
    if (\is_string($path)) {
      return $path;
    }
    return \reset($path);
  }

  /**
   * {@inheritdoc}
   */
  public function processAttachments(AttachmentsInterface $response) {
    $original_attachments = $response->getAttachments();
    $import_maps = $original_attachments['import_maps'] ?? [];
    $import_urls = \array_map(self::flattenPaths(...), $import_maps[self::GLOBAL_IMPORTS] ?? []);
    foreach ($import_maps[self::SCOPED_IMPORTS] ?? [] as $import) {
      $import_urls = \array_unique(\array_merge($import_urls, \array_map(self::flattenPaths(...), $import)));
    }

    foreach ($import_urls as $import_url) {
      $original_attachments['html_head_link'][] = [
        [
          'rel' => 'modulepreload',
          'fetchpriority' => 'high',
          'href' => $import_url,
        ],
      ];
    }

    $import_map = self::buildHtmlTagForAttachedImportMaps($response);
    if ($import_map) {
      unset($original_attachments['import_maps']);
      $original_attachments['html_head'][] = [$import_map, 'canvas_import_map'];
      // Set the attachments with the new script tag and without the import_maps
      // entry.
      $response->setAttachments($original_attachments);
    }
    // Call the decorated attachments processor.
    return $this->htmlResponseAttachmentsProcessor->processAttachments($response);
  }

  public static function buildHtmlTagForAttachedImportMaps(AttachmentsInterface $something): ?array {
    $import_maps = $something->getAttachments()['import_maps'] ?? [];

    if (\count($import_maps) === 0) {
      return NULL;
    }

    $import_maps[self::GLOBAL_IMPORTS] = \array_map(self::flattenPaths(...), $import_maps[self::GLOBAL_IMPORTS] ?? []);
    $import_maps[self::SCOPED_IMPORTS] = \array_map(static fn (array $entries): array => \array_map(self::flattenPaths(...), $entries), $import_maps[self::SCOPED_IMPORTS] ?? []);

    // Transform it into a standard <script> tag.
    $import_map = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => Json::encode(\array_filter($import_maps)),
      '#attributes' => ['type' => 'importmap'],
    ];
    return $import_map;
  }

}
