<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\Tests\TestFileCreationTrait;

trait CanvasFieldTrait {

  use AutoSaveManagerTestTrait;
  use TestFileCreationTrait;

  private const TEST_HEADING_UUID = '8f1971f7-68e0-442f-98f2-c541bb071046';
  private const TEST_IMAGE_UUID = '13ad853b-7a5a-4bd7-a33e-559d7a07579d';
  private const TEST_BLOCK = '4a03b39a-daea-424e-8507-09e182aafa31';

  private File $referencedImage;
  private File $unreferencedImage;
  private Media $mediaEntity;

  protected function getValidConvertedInputs(bool $dynamic_image = TRUE): array {
    return [
      self::TEST_HEADING_UUID => [
        'text' => 'This is a random heading.',
        'style' => 'primary',
        'element' => 'h1',
      ],
      self::TEST_IMAGE_UUID => $dynamic_image ? [
        'image' => [
          'sourceType' => 'dynamic',
          'expression' => 'ℹ︎␜entity:node:article␝field_hero␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
        ],
      ] : [
        'image' => [
          'target_id' => (int) $this->mediaEntity->id(),
        ],
      ],
      self::TEST_BLOCK => [
        'use_site_logo' => TRUE,
        'use_site_name' => TRUE,
        'use_site_slogan' => FALSE,
        'label' => '',
        'label_display' => FALSE,
      ],
    ];
  }

  private function setUpImages(): void {
    $test_image_files = $this->getTestFiles('image');
    // Start with the second image because
    // \Drupal\Tests\canvas\TestSite\CanvasTestSetup::setup() already
    // creates a media image that references the first image.
    $this->referencedImage = $this->createFileEntity($test_image_files[1]);
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'The bones are their money',
      'field_media_image' => [
        [
          'target_id' => (string) $this->referencedImage->id(),
          'alt' => 'The bones equal dollars',
          'title' => 'Bones are the skeletons money',
        ],
      ],
    ]);
    $media->save();
    assert($media instanceof Media);
    $this->mediaEntity = $media;
    $this->unreferencedImage = $this->createFileEntity($test_image_files[3]);
  }

  private static function createFileEntity(object $test_image): File {
    // @phpstan-ignore-next-line
    $uri = $test_image->uri;
    $file = File::create(['uri' => $uri]);
    $file->save();
    assert($file instanceof File);
    return $file;
  }

  private function assertNodeValues(Node $node, array $expected_component_ids, array $expected_inputs, array $expected_field_values): void {
    $nid = $node->id();
    assert(is_string($nid));
    // Reset the node to ensure we're not getting a cached version.
    $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->resetCache([$nid]);
    $node = Node::load($nid);
    $this->assertInstanceOf(Node::class, $node);
    foreach ($expected_field_values as $field_name => $value) {
      $this->assertSame($value, $node->get($field_name)->value);
    }
    $item = $node->get('field_canvas_demo');
    $values = $item->getValue();
    self::assertEqualsCanonicalizing($expected_component_ids, \array_unique(\array_column($values, 'component_id')));
    $inputs = \array_combine(
      \array_column($values, 'uuid'),
      \array_map(static fn (string $input): array => \json_decode($input, TRUE, \JSON_THROW_ON_ERROR), \array_column($values, 'inputs')),
    );
    // @todo Replace with a single call to
    //   `\PHPUnit\Framework\Assert::assertEqualsCanonicalizing` in
    //  https://drupal.org/i/3486414. Currently that does not work in all
    //  databases.
    self::recursiveKsort($inputs);
    self::recursiveKsort($expected_inputs);
    $this->assertSame($expected_inputs, $inputs);
  }

  private static function recursiveKsort(array &$array): void {
    ksort($array);
    foreach ($array as &$value) {
      if (is_array($value)) {
        self::recursiveKsort($value);
      }
    }
  }

  private function getValidClientJson(?EntityInterface $autoSaveEntity, bool $dynamic_image = TRUE): array {
    return [
      'layout' => [
        [
          'nodeType' => 'region',
          'name' => 'Content',
          'id' => 'content',
          'components' => [
            [
              'nodeType' => 'component',
              'uuid' => self::TEST_HEADING_UUID,
              'type' => 'sdc.canvas_test_sdc.heading@8dd7b865998f53b0',
              'slots' => [],
            ],
            [
              'nodeType' => 'component',
              'uuid' => self::TEST_IMAGE_UUID,
              'type' => 'sdc.canvas_test_sdc.image@cc9b97c9370aabdf',
              'slots' => [],
            ],
            [
              'nodeType' => 'component',
              'uuid' => self::TEST_BLOCK,
              'type' => 'block.system_branding_block@247a23298360adb2',
              'slots' => [],
            ],
          ],
        ],
      ],
      'model' => [
        self::TEST_HEADING_UUID => [
          'resolved' => [
            'text' => 'This is a random heading.',
            'style' => 'primary',
            'element' => 'h1',
          ],
          'source' => [
            'text' => [
              'sourceType' => 'static:field_item:string',
              'expression' => 'ℹ︎string␟value',
            ],
            'style' => [
              'sourceType' => 'static:field_item:list_string',
              'expression' => 'ℹ︎list_string␟value',
              'sourceTypeSettings' => [
                'storage' => [
                  'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
                ],
              ],
            ],
            'element' => [
              'sourceType' => 'static:field_item:list_string',
              'expression' => 'ℹ︎list_string␟value',
              'sourceTypeSettings' => [
                'storage' => [
                  'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
                ],
              ],
            ],
          ],
        ],
        self::TEST_BLOCK => [
          'resolved' => [
            'block_branding' => [
              'use_site_logo' => TRUE,
              'use_site_name' => TRUE,
              'use_site_slogan' => FALSE,
            ],
            'label' => '',
            'label_display' => FALSE,
            // The 'provider' key is here to test that it is correctly removed.
            // @see BlockComponent::clientModelToInput()
            'provider' => 'system',
          ],
        ],
        self::TEST_IMAGE_UUID => ($dynamic_image ? [
          'resolved' => [
            'image' => [
              'src' => $this->getSrcPropertyFromFile($this->referencedImage),
              'alt' => 'This is a random image.',
              'width' => 100,
              'height' => 100,
            ],
          ],
          'source' => [
            'image' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_hero␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
            ],
          ],
        ] : [
          'resolved' => [
            'image' => [
              'src' => $this->getSrcPropertyFromFile($this->referencedImage),
              'alt' => 'This is a random image.',
              'width' => 100,
              'height' => 100,
            ],
          ],
          'source' => [
            'image' => [
              'value' => (int) $this->mediaEntity->id(),
              'sourceType' => 'static:field_item:entity_reference',
              'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
              'sourceTypeSettings' => [
                'storage' => ['target_type' => 'media'],
                'instance' => [
                  'handler' => 'default:media',
                  'handler_settings' => [
                    'target_bundles' => ['image' => 'image'],
                  ],
                ],
              ],
            ],
          ],
        ]),
      ],
      'entity_form_fields' => [
        'title[0][value]' => 'The updated title.',
      ],
    ] + ($autoSaveEntity === NULL ? [] : $this->getPatchContentsDefaults([$autoSaveEntity]));
  }

  protected function getPostContentsDefaults(EntityInterface $autoSaveEntity): array {
    static $clientInstanceId = 1;
    return [
      'model' => [],
      'entity_form_fields' => [],
      'clientInstanceId' => (string) ++$clientInstanceId,
    ] + $this->getClientAutoSaves([$autoSaveEntity]);
  }

  protected function getPatchContentsDefaults(array $autoSaveEntities, bool $addRegions = TRUE): array {
    static $clientInstanceId = 1;
    return [
      'model' => [],
      'clientInstanceId' => (string) ++$clientInstanceId,
    ] + $this->getClientAutoSaves($autoSaveEntities, $addRegions);
  }

  private static function getSrcPropertyFromFile(File $file): string {
    $src = str_replace(base_path(), '/', $file->createFileUrl());
    assert(is_string($src));
    return $src;
  }

  private function assertValidJsonUpdateNode(Node $node, bool $dynamic_image = TRUE): void {
    // Ensure the field has been updated.
    $this->assertNodeValues(
      $node,
      [
        'sdc.canvas_test_sdc.heading',
        'sdc.canvas_test_sdc.image',
        'block.system_branding_block',
      ],
      $this->getValidConvertedInputs($dynamic_image),
      [
        'title' => 'The updated title.',
        'status' => '1',
      ]
    );

  }

}
