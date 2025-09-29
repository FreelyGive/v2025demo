<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldTypeOverride\ImageItemOverride;
use Drupal\canvas\Storage\ComponentTreeLoader;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;
use Drupal\Tests\canvas\Traits\AutoSaveRequestTestTrait;
use Drupal\Tests\canvas\Traits\CanvasFieldTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @covers \Drupal\canvas\Controller\ApiLayoutController::patch()
 * @group canvas
 * @group #slow
 */
final class ApiLayoutControllerPatchTest extends ApiLayoutControllerTestBase {

  use CanvasFieldTrait;
  use AutoSaveRequestTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['system', 'block']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'stark')->save();

    (new CanvasTestSetup())->setup(TRUE);
    $this->setUpCurrentUser([], [
      'administer url aliases',
      PageRegion::ADMIN_PERMISSION,
      'edit any article content',
    ]);
  }

  /**
   * @dataProvider providerEntityTypes
   */
  public function testEntityAccessRequired(string $entity_type): void {
    $this->setUpCurrentUser([], [
      'administer url aliases',
    ]);

    $entity = $this->getTestEntity($entity_type);
    $admin_permission = self::getAdminPermission($entity);
    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage("The '$admin_permission' permission is required.");

    $this->request(Request::create($this->getLayoutUrl($entity)->toString(), method: 'PATCH', content: json_encode([
      'layout' => [
        [
          'nodeType' => 'region',
          'name' => 'Content',
          'components' => [],
          'id' => 'content',
        ],
      ],
    ] + $this->getPatchContentsDefaults([$entity]), JSON_THROW_ON_ERROR)));
  }

  public function testDynamicSourceUpdate(): void {
    $contentTemplate = $this->getTestEntity(ContentTemplate::ENTITY_TYPE_ID);
    \assert($contentTemplate instanceof ContentTemplate);
    \assert($this->previewEntity instanceof Node);
    $revision_log_message = 'I always add a log message.';
    $this->previewEntity->setRevisionLogMessage($revision_log_message);
    $preview_entity_title = 'My dynamic title';
    $this->previewEntity->set('title', $preview_entity_title);
    $this->previewEntity->save();
    $this->setUpCurrentUser([], [self::getAdminPermission($contentTemplate)]);

    $uuid1 = '5f71027b-d9d3-4f3d-8990-a6502c0ba676';
    $uuid2 = 'e8c95423-4f22-4210-8707-08bade75ff22';
    $components = [
      [
        // Add a component with only static property sources.
        'uuid' => $uuid1,
        'component_id' => 'sdc.canvas_test_sdc.my-hero',
        'component_version' => '888412021fbcc837',
        'inputs' => [
          'heading' => [
            'sourceType' => 'static:field_item:string',
            'value' => 'hello, world!',
            'expression' => 'ℹ︎string␟value',
          ],
          'subheading' => [
            'sourceType' => 'static:field_item:string',
            'value' => 'this is a subheading',
            'expression' => 'ℹ︎string␟value',
          ],
          'cta1href' => [
            'sourceType' => 'static:field_item:uri',
            'value' => 'https://drupal.org',
            'expression' => 'ℹ︎uri␟value',
          ],
        ],
      ],
      // Add a component with a pre-existing dynamic property source to ensure
      // it also is rendered and resolved correctly.
      [
        'uuid' => $uuid2,
        'component_id' => 'sdc.canvas_test_sdc.heading',
        'component_version' => '8dd7b865998f53b0',
        'inputs' => [
          'text' => [
            'sourceType' => 'dynamic',
            'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
          ],
          'element' => 'h1',
        ],
      ],
    ];
    $contentTemplate->setComponentTree($components)->save();
    // @todo Remove this in favor of using ContribStrictConfigSchemaTestTrait in https://www.drupal.org/project/canvas/issues/3531679
    self::assertCount(0, $contentTemplate->getTypedData()->validate());

    $assertResponse = function (Response $response, string $expected_heading, $expected_subheading, $expected_text) use ($uuid1, $uuid2) {
      $data = $this->decodeResponse($response);
      self::assertEquals($expected_heading, $data['model'][$uuid1]['resolved']['heading']);
      self::assertEquals($expected_subheading, $data['model'][$uuid1]['resolved']['subheading']);
      self::assertEquals('https://drupal.org', $data['model'][$uuid1]['resolved']['cta1href']);
      self::assertSame($expected_text, $data['model'][$uuid2]['resolved']['text']);
      self::assertCount(1, $this->cssSelect('[data-component-id="canvas_test_sdc:my-hero"]'));
      self::assertSame($expected_heading, (string) $this->cssSelect('[data-component-id="canvas_test_sdc:my-hero"] h1')[0]);
      self::assertSame($expected_subheading, (string) $this->cssSelect('[data-component-id="canvas_test_sdc:my-hero"] p')[0]);
      self::assertCount(1, $this->cssSelect('h1[data-component-id="canvas_test_sdc:heading"]'));
      self::assertSame($expected_text, (string) $this->cssSelect('h1[data-component-id="canvas_test_sdc:heading"]')[0]);
    };
    $url = $this->getLayoutUrl($contentTemplate)->toString();
    $response = $this->request(Request::create($url));
    $assertResponse($response, 'hello, world!', 'this is a subheading', $preview_entity_title);

    $preview_entity_title = 'New title for the article';
    \assert($this->previewEntity instanceof Node);
    $this->previewEntity->set('title', $preview_entity_title)->save();

    // PATCH the model updating 2 of the 3 properties to dynamic sources.
    $data = $this->decodeResponse($response);
    self::assertArrayHasKey('model', $data);
    self::assertIsArray($data['model']);
    $new_model = $data['model'][$uuid1];
    $new_model['source']['heading'] = [
      'sourceType' => 'dynamic',
      'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
    ];
    $new_model['source']['subheading'] = [
      'sourceType' => 'dynamic',
      'expression' => 'ℹ︎␜entity:node:article␝revision_log␞␟value',
    ];
    // The client should set the `resolved` value of a dynamic prop sources to
    // NULL because it cannot resolve them.
    $new_model['resolved']['subheading'] = NULL;
    $new_model['resolved']['heading'] = NULL;
    $updatedHeroClientData = [
      'model' => $new_model,
      'componentType' => 'sdc.canvas_test_sdc.my-hero@888412021fbcc837',
      'componentInstanceUuid' => $uuid1,
    ] + $this->getPatchContentsDefaults([$contentTemplate]);
    $response = $this->request(Request::create($url, method: 'PATCH', content: \json_encode($updatedHeroClientData, JSON_THROW_ON_ERROR)));
    $assertResponse($response, $preview_entity_title, $revision_log_message, $preview_entity_title);

    // Ensure the correct values are returned from a GET request after the PATCH
    // request.
    $url = $this->getLayoutUrl($contentTemplate)->toString();
    $response = $this->request(Request::create($url));
    $assertResponse($response, $preview_entity_title, $revision_log_message, $preview_entity_title);

    // PATCH the subheading to use a different dynamic source.
    $data = $this->decodeResponse($response);
    self::assertArrayHasKey('model', $data);
    self::assertIsArray($data['model']);
    $new_model = $data['model'][$uuid1];
    $new_model['source']['subheading'] = [
      'sourceType' => 'dynamic',
      'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
    ];
    $new_model['resolved']['subheading'] = NULL;
    $new_model['resolved']['heading'] = NULL;
    $updatedHeroClientData = [
      'model' => $new_model,
      'componentType' => 'sdc.canvas_test_sdc.my-hero@888412021fbcc837',
      'componentInstanceUuid' => $uuid1,
    ] + $this->getPatchContentsDefaults([$contentTemplate]);
    $response = $this->request(Request::create($url, method: 'PATCH', content: \json_encode($updatedHeroClientData, JSON_THROW_ON_ERROR)));
    $assertResponse($response, $preview_entity_title, $preview_entity_title, $preview_entity_title);
  }

  /**
   * @param class-string<\Throwable> $exception
   * @dataProvider providerInvalid
   */
  public function testInvalid(string $message, string $exception, array $content): void {
    $this->expectException($exception);
    $this->expectExceptionMessage($message);
    if (isset($content['autoSaves'])) {
      unset($content['autoSaves']);
      $content += $this->getClientAutoSaves([Node::load(1)]);
    }
    $this->parentRequest(Request::create('/canvas/api/v0/layout/node/1', method: 'PATCH', server: [
      'CONTENT_TYPE' => 'application/json',
      'HTTP_X_NO_OPENAPI_VALIDATION' => 'turned off because we want to validate the prod response here',
    ], content: \json_encode($content, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR)));
  }

  public static function providerInvalid(): iterable {
    yield 'no component instance uuid' => [
      'Missing componentInstanceUuid',
      BadRequestHttpException::class,
      [],
    ];
    yield 'no component type' => [
      'Missing componentType',
      BadRequestHttpException::class,
      [
        'componentInstanceUuid' => 'e8c95423-4f22-4210-8707-08bade75ff22',
      ],
    ];
    yield 'no model' => [
      'Missing model',
      BadRequestHttpException::class,
      [
        'componentInstanceUuid' => 'e8c95423-4f22-4210-8707-08bade75ff22',
        'componentType' => 'sdc.canvas_test_sdc.image@cc9b97c9370aabdf',
      ],
    ];
    yield 'No such component in model' => [
      'No such component in model: e8c95423-4f22-4210-8707-08bade75ff22',
      NotFoundHttpException::class,
      [
        'componentInstanceUuid' => 'e8c95423-4f22-4210-8707-08bade75ff22',
        'componentType' => 'sdc.canvas_test_sdc.image@cc9b97c9370aabdf',
        'model' => [],
        'autoSaves' => [],
        'clientInstanceId' => 'sample-client-id',
      ],
    ];
    yield 'No such component' => [
      'No such component: garry_sensible_jeans',
      NotFoundHttpException::class,
      [
        'componentInstanceUuid' => CanvasTestSetup::UUID_STATIC_IMAGE,
        'componentType' => 'garry_sensible_jeans@jean_shorts',
        'model' => [],
        'autoSaves' => [],
        'clientInstanceId' => 'sample-client-id',
      ],
    ];
    yield 'No version provided' => [
      'Missing version for component sdc.canvas_test_sdc.image',
      NotFoundHttpException::class,
      [
        'componentInstanceUuid' => CanvasTestSetup::UUID_STATIC_IMAGE,
        'componentType' => 'sdc.canvas_test_sdc.image',
        'model' => [],
        'autoSaves' => [],
        'clientInstanceId' => 'sample-client-id',
      ],
    ];
    yield 'Invalid version provided' => [
      'No such version hamster for component sdc.canvas_test_sdc.image',
      NotFoundHttpException::class,
      [
        'componentInstanceUuid' => CanvasTestSetup::UUID_STATIC_IMAGE,
        'componentType' => 'sdc.canvas_test_sdc.image@hamster',
        'model' => [],
        'autoSaves' => [],
        'clientInstanceId' => 'sample-client-id',
      ],
    ];
  }

  /**
   * @dataProvider providerValid
   */
  public function test(string $entity_type, bool $withAutoSave = FALSE, bool $withGlobal = FALSE): void {
    $entity = $this->getTestEntity($entity_type);
    $url = $this->getLayoutUrl($entity)->toString();
    $this->setUpCurrentUser([], [
      'administer url aliases',
      PageRegion::ADMIN_PERMISSION,
      self::getAdminPermission($entity),
    ]);
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    $regions = [];
    if ($withGlobal) {
      $regions = PageRegion::createFromBlockLayout('stark');
      foreach ($regions as $region) {
        $region->save();
      }
    }

    // Setup additional nesting of components.
    $tree_loader = $this->container->get(ComponentTreeLoader::class);
    \assert($tree_loader instanceof ComponentTreeLoader);
    $tree = $tree_loader->load($entity);
    $static_image = $tree->getComponentTreeItemByUuid(CanvasTestSetup::UUID_STATIC_IMAGE);
    \assert($static_image instanceof ComponentTreeItem);
    $static_image->set('parent_uuid', CanvasTestSetup::UUID_ALL_SLOTS_EMPTY);
    $static_image->set('slot', 'content');
    // We need to make sure the delta order reflects that parents come before
    // children otherwise this will happen on POST and create an auto-save entry.
    $image_delta = $tree->getComponentTreeDeltaByUuid(CanvasTestSetup::UUID_STATIC_IMAGE);
    $parent_delta = $tree->getComponentTreeDeltaByUuid(CanvasTestSetup::UUID_ALL_SLOTS_EMPTY);
    \assert($image_delta !== NULL);
    \assert($parent_delta !== NULL);
    $values = $tree->getValue();
    $values = [
      ...\array_slice($values, 0, $image_delta),
      ...\array_slice($values, $image_delta + 1, $parent_delta - $image_delta),
      ...\array_slice($values, $image_delta, 1),
      ...\array_slice($values, $parent_delta + 1),
    ];
    if ($entity instanceof FieldableEntityInterface) {
      $tree->setValue($values);
    }
    else {
      $entity->setComponentTree($values);
    }

    $entity->save();

    // Load the test data from the layout controller.
    $response = $this->parentRequest(Request::create($url));
    $this->assertResponseAutoSaves($response, [$entity], $withGlobal);
    $content = $response->getContent();
    self::assertIsString($content);
    $data = $this->decodeResponse($response);
    if ($entity instanceof Node) {
      // Check that the client only receives field data they have access to.
      // @see ApiLayoutController::filterFormValues()
      $this->assertSame([
        'changed',
        'field_hero[0][target_id]',
        'field_hero[0][alt]',
        'field_hero[0][width]',
        'field_hero[0][height]',
        'field_hero[0][fids][0]',
        'field_hero[0][display]',
        'field_hero[0][description]',
        'field_hero[0][upload]',
        'media_image_field[media_library_selection]',
        'path[0][alias]',
        'path[0][source]',
        'path[0][langcode]',
        'title[0][value]',
        'langcode[0][value]',
        'revision',
      ], array_keys($data['entity_form_fields']));
    }

    $model = $data['model'];

    if ($withAutoSave) {
      // Perform a POST first to trigger the auto-save manager being called.
      // This will not result in an auto-save entry because the content is the
      // same as the saved version.
      $response = $this->request(Request::create($url, method: 'POST', content: $this->filterLayoutForPost($content)));
      $this->assertResponseAutoSaves($response, [$entity], $withGlobal);
      self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
      self::assertTrue($autoSave->getAutoSaveEntity($entity)->isEmpty());
      foreach ($regions as $region) {
        self::assertTrue($autoSave->getAutoSaveEntity($region)->isEmpty());
      }
    }

    // Update the image.
    $media = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['name' => 'Hero image']);
    self::assertCount(1, $media);
    $media = reset($media);
    \assert($media instanceof MediaInterface);

    // Make sure the current value isn't the same media ID.
    self::assertNotEmpty($model[CanvasTestSetup::UUID_STATIC_IMAGE]['resolved']['image']);
    self::assertNotEquals($media->id(), $model[CanvasTestSetup::UUID_STATIC_IMAGE]['resolved']['image']);

    // Now patch the layout.
    $new_model = $model[CanvasTestSetup::UUID_STATIC_IMAGE];
    // Reference a new media entity.
    $new_model['source']['image']['value'] = $media->id();
    $updateImageClientData = [
      'model' => $new_model,
      'componentType' => 'sdc.canvas_test_sdc.image@cc9b97c9370aabdf',
      'componentInstanceUuid' => CanvasTestSetup::UUID_STATIC_IMAGE,
    ] + $this->getPatchContentsDefaults([$entity]);
    $response = $this->request(Request::create($url, method: 'PATCH', content: \json_encode($updateImageClientData, JSON_THROW_ON_ERROR)));

    // The new model should contain the updated value.
    $data = self::decodeResponse($response);
    $this->assertResponseAutoSaves($response, [$entity], $withGlobal);
    // The updated preview should reference the new image.
    $file = $media->get('field_media_image')->entity;
    \assert($file instanceof FileInterface);
    $fileUri = $file->getFileUri();
    \assert(is_string($fileUri));
    $image = $media->get('field_media_image')->get(0);
    \assert($image instanceof ImageItemOverride);
    $image_url = $image->get('src_with_alternate_widths')->getValue();
    self::assertEquals($image_url, $data['model'][CanvasTestSetup::UUID_STATIC_IMAGE]['resolved']['image']['src']);

    self::assertFalse($autoSave->getAutoSaveEntity($entity)->isEmpty());
    foreach ($regions as $region) {
      self::assertTrue($autoSave->getAutoSaveEntity($region)->isEmpty());
    }

    // Check that each level is structured correctly.
    $content = $this->getRegion('content');
    self::assertNotNull($content);
    $globalElements = [];
    if ($withGlobal) {
      $sidebar_first = $this->getRegion('sidebar_first');
      self::assertNotNull($sidebar_first);
      $globalElements = $this->getComponentInstances($sidebar_first);

      $highlighted = $this->getRegion('highlighted');
      self::assertNotNull($highlighted);
      $highlightedElements = $this->getComponentInstances($highlighted);
      $globalElements = [...$globalElements, ...$highlightedElements];
    }
    $contentElements = $this->getComponentInstances($content);
    self::assertCount($withGlobal ? 10 : 8, \array_merge($contentElements, $globalElements));
    if ($withGlobal) {
      self::assertSame(\array_keys($model), \array_merge($contentElements, $globalElements));
    }

    // There should be two images, one should reference the media item direct
    // (static-image-udf7d) and one should reference the thumbnail style
    // (static-image-static-imageStyle-something7d) because it uses an adapter.
    // @see \Drupal\canvas\Plugin\Adapter\ImageAndStyleAdapter
    $images = (new Crawler($data['html']))->filter('img')->extract(['src']);
    $thumbnail = ImageStyle::load('thumbnail');
    \assert($thumbnail instanceof ImageStyleInterface);
    self::assertCount(2, $images);
    self::assertEquals([
      $image_url,
      $thumbnail->buildUrl($fileUri),
    ], $images);

    unset($updateImageClientData['clientInstanceId']);
    $updateImageClientData += $this->getPatchContentsDefaults([$entity]);
    $this->assertRequestAutoSaveConflict(Request::create($url, method: 'PATCH', content: \json_encode($updateImageClientData, JSON_THROW_ON_ERROR)));

    if ($withGlobal) {
      $new_label = $this->randomMachineName();
      // Patch a global component.
      $globalComponentUuid = reset($globalElements);
      $updateRegionClientData = [
        'model' => [
          'resolved' => [
            'label' => $new_label,
            'label_display' => '',
          ],
        ],
        'componentType' => 'block.system_messages_block@b92f802cf68eb83e',
        'componentInstanceUuid' => $globalComponentUuid,
      ] + $this->getPatchContentsDefaults([$entity]);
      $response = $this->request(Request::create($url, method: 'PATCH', content: \json_encode($updateRegionClientData, JSON_THROW_ON_ERROR)));

      // The new model should contain the updated value.
      $data = self::decodeResponse($response);
      self::assertEquals($new_label, $data['model'][$globalComponentUuid]['resolved']['label']);

      self::assertFalse($autoSave->getAutoSaveEntity($entity)->isEmpty());
      $sidebarFirstRegion = NULL;
      foreach ($regions as $region) {
        // The updated component is in sidebar_first and so auto-save should not
        // be empty.
        self::assertEquals($region->get('region') !== 'sidebar_first', $autoSave->getAutoSaveEntity($region)->isEmpty());
        if ($region->get('region') === 'sidebar_first') {
          $sidebarFirstRegion = $region;
          $this->assertResponseAutoSaves($response, [$entity], $withGlobal);
        }
      }
      $this->assertNotNull($sidebarFirstRegion);

      // Trying to post the same data again should throw a conflict exception
      // because it does not contain the auto-save hash of the region.
      $updateRegionClientData['clientInstanceId'] .= '-new-client';
      $this->assertRequestAutoSaveConflict(Request::create($url, method: 'PATCH', content: \json_encode($updateRegionClientData, JSON_THROW_ON_ERROR)));

      unset($updateRegionClientData['autoSaves']);
      $updateRegionClientData['clientInstanceId'] .= '-new-client2';
      $updateRegionClientData += $this->getClientAutoSaves([$entity], $withGlobal);
      $response = $this->request(Request::create($url, method: 'PATCH', content: \json_encode($updateRegionClientData, JSON_THROW_ON_ERROR)));
      $this->assertSame(200, $response->getStatusCode());
    }
  }

  public static function providerValid(): iterable {
    foreach (['node', ContentTemplate::ENTITY_TYPE_ID] as $entity_type) {
      yield "$entity_type: fresh state, no global" => [$entity_type];
      yield "$entity_type: fresh state, global" => [$entity_type, FALSE, TRUE];
      yield "$entity_type: existing auto-save, no global" => [$entity_type, TRUE, FALSE];
      yield "$entity_type: existing auto-save, global" => [$entity_type, TRUE, TRUE];
    }
  }

  /**
   * @dataProvider providerEntityTypes
   */
  public function testWithoutPageRegionPermission(string $entity_type): void {
    $entity = $this->getTestEntity($entity_type);
    $this->setUpCurrentUser([], [
      'administer url aliases',
      self::getAdminPermission($entity),
    ]);

    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    $regions = PageRegion::createFromBlockLayout('stark');
    foreach ($regions as $region) {
      $region->save();
    }
    // Load the test data from the layout controller.
    $url = $this->getLayoutUrl($entity)->toString();
    $this->request(Request::create($url))->getContent();

    // Check that content region exist and is wrapped.
    $contentRegion = $this->getRegion('content');
    $this->assertNotNull($contentRegion);
    // But not the highlighted region, as we don't have access to it.
    $highlighted = $this->getRegion('highlighted');
    self::assertNull($highlighted);

    $new_label = $this->randomMachineName();
    // Patch a component instance in a ("global") region.
    // We need to use the APIs to get the UUID of a valid component instance in a region.
    $component_tree_values = $regions['stark.highlighted']->getComponentTree()->getValue();
    $globalComponentUuids = \array_column($component_tree_values, 'uuid');
    // There is only one block, the title, in the highlighted region.
    $this->assertCount(1, $globalComponentUuids);
    $globalComponentUuid = $globalComponentUuids[0];

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Access denied for region highlighted');

    $this->request(Request::create($url, method: 'PATCH', content: \json_encode([
      'model' => [
        'resolved' => [
          'label' => $new_label,
          'label_display' => '',
        ],
      ],
      'componentType' => 'block.system_messages_block@b92f802cf68eb83e',
      'componentInstanceUuid' => $globalComponentUuid,
    ] + $this->getPatchContentsDefaults([$entity], FALSE), JSON_THROW_ON_ERROR)));
  }

}
