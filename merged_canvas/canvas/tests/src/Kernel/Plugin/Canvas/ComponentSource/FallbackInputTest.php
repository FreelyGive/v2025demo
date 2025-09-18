<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource;

use Drupal\Core\File\FileExists;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\Url;
use Drupal\canvas\Controller\ApiAutoSaveController;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Plugin\ComponentPluginManager;
use Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\canvas\Kernel\ApiLayoutControllerTestBase;
use Drupal\Tests\canvas\Traits\AutoSaveManagerTestTrait;
use Drupal\Tests\canvas\Traits\CanvasFieldTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests that the fallback plugin retains recoverable user input.
 *
 * @coversDefaultClass \Drupal\canvas\Plugin\Canvas\ComponentSource\Fallback
 * @group canvas
 */
final class FallbackInputTest extends ApiLayoutControllerTestBase {

  use MediaTypeCreationTrait;
  use AutoSaveManagerTestTrait;
  use CanvasFieldTrait;

  protected static $modules = [
    // Required modules.
    'system',
    'user',
    'block',
    // Entity-types used by the page entity.
    'path_alias',
    'file',
    'media',
    'path',
    // Field types we need.
    'image',
    'link',
    'options',
    // Allow using media for image plugin.
    'media',
    'media_library',
    'views',
    'field',
    // Needed to install Canvas's default config.
    'filter',
    'ckeditor5',
    'editor',
    // Our module!
    'canvas',
    // Test components we can force fallback and recovery on.
    'canvas_test_sdc',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Install and configure the default theme.
    $this->container->get('theme_installer')->install(['stark']);
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'stark')->save();

    // Add some entity-types required by the page entity.
    $this->installConfig(['canvas']);
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('media');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);

    // Make sure the global asset library is created.
    $this->installConfig('canvas');

    // Login as someone who can edit the page layout.
    $this->setUpCurrentUser([], [
      'administer url aliases',
      Page::CREATE_PERMISSION,
      Page::EDIT_PERMISSION,
      'access content',
    ]);

    // Force generation of component config entities.
    $this->container->get(ComponentPluginManager::class)->getDefinitions();
  }

  /**
   * @covers ::requiresExplicitInput
   * @covers ::getExplicitInput
   * @covers ::inputToClientModel
   * @covers ::clientModelToInput
   *
   * @testWith [true]
   *           [false]
   */
  public function testFallbackInputCanBeRecovered(bool $publish = FALSE): void {
    $component_to_recover = Component::load('sdc.canvas_test_sdc.image');
    \assert($component_to_recover instanceof ComponentInterface);
    $component_to_edit = Component::load('sdc.canvas_test_sdc.heading');
    \assert($component_to_edit instanceof ComponentInterface);
    // Create a tree containing two components, one that will be forced to a
    // fallback and then be recovered. One that we will edit.
    $component_to_recover_uuid = '5821b0f4-162b-4a39-88b6-157b39b9b4f6';
    $component_to_edit_uuid = '20de2945-f515-49b6-b986-407d973860b9';
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_uri = 'public://image-2.jpg';
    if (!\file_exists($file_uri)) {
      $file_system->copy(\Drupal::root() . '/core/tests/fixtures/files/image-2.jpg', PublicStream::basePath(), FileExists::Replace);
    }
    $file = File::create([
      'uri' => $file_uri,
      'status' => 1,
    ]);
    $file->save();
    $image = Media::create([
      'bundle' => 'image',
      'name' => 'Amazing image',
      'field_media_image' => [
        [
          'target_id' => $file->id(),
          'alt' => 'An image so amazing that to gaze upon it would melt your face',
          'title' => 'This is an amazing image, just look at it and you will be amazed',
        ],
      ],
    ]);
    $image->save();
    $tree = [
      [
        'uuid' => $component_to_recover_uuid,
        'component_id' => $component_to_recover->id(),
        'inputs' => [
          'image' => [
            'sourceType' => 'static:field_item:entity_reference',
            'value' => ['target_id' => $image->id()],
            // This expression resolves `src` to the image's public URL.
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
      ],
      [
        'uuid' => $component_to_edit_uuid,
        'component_id' => $component_to_edit->id(),
        'inputs' => [
          'text' => [
            'sourceType' => 'static:field_item:string',
            'expression' => 'ℹ︎string␟value',
            'value' => 'Original heading text',
          ],
          'style' => [
            'value' => 'primary',
            'sourceType' => 'static:field_item:list_string',
            'expression' => 'ℹ︎list_string␟value',
            'sourceTypeSettings' => [
              'storage' => [
                'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
              ],
            ],
          ],
          'element' => [
            'value' => 'h2',
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
    ];
    // Create a test entity.
    $page = Page::create([
      'title' => $this->randomMachineName(),
      'components' => $tree,
    ]);
    $page->save();
    $api_endpoint_uri = \sprintf('/canvas/api/v0/layout/%s/%d', Page::ENTITY_TYPE_ID, $page->id());
    // Load the original data.
    $response = $this->parentRequest(Request::create($api_endpoint_uri));
    $data = self::decodeResponse($response);

    // Make sure our components are there both in the preview and in the model.
    $crawler = new Crawler($data['html']);
    self::assertCount(1, $crawler->filter('h2:contains("Original heading text")'));
    self::assertCount(1, $crawler->filter('img[alt="An image so amazing that to gaze upon it would melt your face"]'));
    self::assertCount(2, $data['model']);

    // Remove image media type to trigger the first component moving to the
    // fallback source.
    $type = MediaType::load('image');
    \assert($type instanceof MediaType);
    $type->delete();

    /** @var \Drupal\canvas\Entity\ComponentInterface $component_to_recover */
    $component_to_recover = Component::load($component_to_recover->id());
    self::assertEquals(ComponentInterface::FALLBACK_VERSION, $component_to_recover->getComponentSource()->getPluginId());

    // Load the fallback data.
    $response = $this->parentRequest(Request::create($api_endpoint_uri));
    $data = self::decodeResponse($response);

    // We should still see two items in the model (inputs).
    self::assertCount(2, $data['model']);

    // But only one of them should be in the preview now as the fallback inputs
    // have no outcome on the preview.
    $crawler = new Crawler($data['html']);
    self::assertCount(1, $crawler->filter('h2:contains("Original heading text")'));
    self::assertCount(0, $crawler->filter('img[alt="An image so amazing that to gaze upon it would melt your face"]'));

    // Now perform a patch update to the non fallback component.
    $new_model = $data['model'][$component_to_edit_uuid];
    $new_model['source']['text']['value'] = 'New heading text';
    $response = $this->request(Request::create($api_endpoint_uri, method: 'PATCH', content: \json_encode([
      'model' => $new_model,
      'componentType' => 'sdc.canvas_test_sdc.heading@8dd7b865998f53b0',
      'componentInstanceUuid' => $component_to_edit_uuid,
    ] + $this->getPatchContentsDefaults([$page]), JSON_THROW_ON_ERROR)));
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $data = self::decodeResponse($response);

    // We should still see two items in the model (inputs).
    self::assertCount(2, $data['model']);

    // We should see the updated property in the component preview.
    $crawler = new Crawler($data['html']);
    self::assertCount(1, $crawler->filter('h2:contains("New heading text")'));
    self::assertCount(0, $crawler->filter('img[alt="An image so amazing that to gaze upon it would melt your face"]'));

    if ($publish) {
      /** @var \Drupal\canvas\Controller\ApiAutoSaveController $auto_save_controller */
      $auto_save_controller = $this->container->get(ApiAutoSaveController::class);
      $data = $auto_save_controller->get();
      $content = $data->getContent();
      \assert(\is_string($content));
      $request = Request::create(
        Url::fromRoute('canvas.api.auto-save.post')->toString(),
        content: $content
      );
      $response = $auto_save_controller->post($request);
      self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    // Now recreate the image media type which should force a 'recovery' of the
    // fallback.
    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);

    // Rebuild component entities.
    $component_plugin_manager = $this->container->get(ComponentPluginManager::class);
    $component_plugin_manager->clearCachedDefinitions();
    $component_plugin_manager->getDefinitions();
    /** @var \Drupal\canvas\Entity\ComponentInterface $component_to_recover */
    $component_to_recover = Component::load($component_to_recover->id());
    self::assertFalse($component_to_recover->status());
    self::assertEquals(SingleDirectoryComponent::SOURCE_PLUGIN_ID, $component_to_recover->getComponentSource()->getPluginId());

    // Fetch the data again.
    $response = $this->parentRequest(Request::create($api_endpoint_uri));
    $data = self::decodeResponse($response);

    // Make sure our components are there both in the preview and in the model.
    $crawler = new Crawler($data['html']);
    self::assertCount(2, $data['model']);
    self::assertCount(1, $crawler->filter('h2:contains("New heading text")'));
    self::assertCount(1, $crawler->filter('img[alt="An image so amazing that to gaze upon it would melt your face"]'));
  }

}
