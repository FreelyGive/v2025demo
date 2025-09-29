<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Storage\ComponentTreeLoader;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Controller\ApiLayoutController;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @covers \Drupal\canvas\Controller\ApiLayoutController::get()
 * @group canvas
 * @group #slow
 */
class ApiLayoutControllerGetTest extends ApiLayoutControllerTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Allows format=uri to be stored using URI field type.
    'canvas_test_storage_prop_shape_alter',
    'sdc_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['system']);
    (new CanvasTestSetup())->setup(TRUE);
    $this->setUpCurrentUser([], ['edit any article content']);
  }

  /**
   * @dataProvider providerEntityTypes
   */
  public function testEmpty(string $entity_type): void {
    $entity = $this->getTestEntity($entity_type);
    $this->setUpCurrentUser([], [self::getAdminPermission($entity)]);
    // Enable global regions.
    $regions = $this->enableGlobalRegions();
    foreach ($regions as $region) {
      // But let's make sure none of them have a component tree so we have an
      // empty model.
      $region->setComponentTree([])->save();
    }
    $url = $this->getLayoutUrl($entity);
    $response = $this->request(Request::create($url->toString()));
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $this->assertResponseAutoSaves($response, [$entity]);
  }

  /**
   * @covers ::get
   * @see \Drupal\canvas\Entity\ContentTemplate
   */
  public function testRenderDynamic(): void {
    $contentTemplate = $this->getTestEntity(ContentTemplate::ENTITY_TYPE_ID);
    \assert($contentTemplate instanceof ContentTemplate);

    $top_level_component_uuid = '5f71027b-d9d3-4f3d-8990-a6502c0ba676';
    $nested_component_uuid = '8caf6e23-8fb4-4524-bdb6-f57a2a6e7859';
    // Add a heading populated by a dynamic prop source using the `title` field.
    $components = [
      [
        'uuid' => $top_level_component_uuid,
        'component_id' => 'sdc.canvas_test_sdc.props-slots',
        'component_version' => 'ab4d3ddce315cf64',
        'inputs' => [
          'heading' => [
            'sourceType' => 'dynamic',
            'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
          ],
        ],
      ],
      [
        'uuid' => $nested_component_uuid,
        'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
        'component_version' => '95f4f1d5ee47663b',
        'inputs' => [
          'heading' => [
            'sourceType' => 'dynamic',
            'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
          ],
        ],
        'slot' => 'the_body',
        'parent_uuid' => $top_level_component_uuid,
      ],
    ];
    $contentTemplate->setComponentTree($components)->save();
    // @todo Remove this in favor of using ContribStrictConfigSchemaTestTrait in https://www.drupal.org/project/canvas/issues/3531679
    self::assertCount(0, $contentTemplate->getTypedData()->validate(), (string) $contentTemplate->getTypedData()->validate());
    $get_layout_api_request = Request::create($this->getLayoutUrl($contentTemplate)->toString());
    $this->setUpCurrentUser([], [self::getAdminPermission($contentTemplate)]);

    // Local helper to check these are in sync/contain the expected title:
    // - entity label
    // - `model` in API response
    // - `html` in API
    $title_matches_resolved_and_html = function (string $expected_title, JsonResponse $response) use ($top_level_component_uuid, $nested_component_uuid) {
      // Current preview entity label MUST match the expected title.
      self::assertSame($expected_title, $this->previewEntity?->label());
      // The `model` of the layout API response MUST contain the expected title.
      self::assertSame($expected_title, static::decodeResponse($response)['model'][$top_level_component_uuid]['resolved']['heading']);
      self::assertSame($expected_title, static::decodeResponse($response)['model'][$nested_component_uuid]['resolved']['heading']);
      // The `html` of the layout API response MUST render the expected title in
      // the both the top-level and nested component.
      self::assertCount(1, $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"]'));
      // Make sure we match only the h1 that is direct the child of the component
      // so don't match the one in the nested component.
      self::assertSame($expected_title, (string) $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"] > h1')[0]);
      self::assertCount(1, $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"] [data-component-id="canvas_test_sdc:props-no-slots"]'));
      self::assertSame($expected_title, (string) $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"] [data-component-id="canvas_test_sdc:props-no-slots"] > h1')[0]);
    };

    // Assert the original resolved dynamic prop source + resulting HTML.
    $response = $this->request($get_layout_api_request);
    self::assertInstanceOf(JsonResponse::class, $response);
    $title_matches_resolved_and_html('Canvas Needs This For The Time Being', $response);

    // Updating the title of the preview entity must propagate throughout.
    assert($this->previewEntity instanceof Node);
    $this->previewEntity->set('title', 'New title for preview')->save();
    $response = $this->request($get_layout_api_request);
    self::assertInstanceOf(JsonResponse::class, $response);
    $title_matches_resolved_and_html('New title for preview', $response);
  }

  /**
   * @dataProvider providerEntityTypes
   */
  public function test(string $entity_type): void {
    // By default, there is only the "content" region in the client-side
    // representation.
    $entity = $this->getTestEntity($entity_type);
    $admin_permission = self::getAdminPermission($entity);
    $this->setUpCurrentUser([], [$admin_permission]);

    $this->assertRegions(1, $entity);
    /** @var \Drupal\canvas\AutoSave\AutoSaveManager $autoSave */
    $autoSave = $this->container->get(AutoSaveManager::class);
    self::assertTrue($autoSave->getAutoSaveEntity($entity)->isEmpty());
    $regions = $this->enableGlobalRegions();

    // … but the corresponding client-side representation contains only the
    // "content" region unless it has permissions to edit the global regions.
    $this->assertRegions(1, $entity);

    $this->setUpCurrentUser([], [$admin_permission, PageRegion::ADMIN_PERMISSION]);

    // … and the corresponding client-side representation contains all regions
    // plus one more (the "content" region) once it has the required permission.
    $this->assertRegions(12, $entity);

    // Disable a PageRegion to make it non-editable, and check that only 11
    // regions are present in the client-side representation.
    $regions['stark.highlighted']->disable()->save();
    $this->assertRegions(11, $entity);

    // Store a draft region in the auto-save manager and confirm that is returned.
    $regions['stark.highlighted']->enable()->save();
    $layoutData = [
      'layout' => [
        [
          "nodeType" => "component",
          "slots" => [],
          "type" => "block.page_title_block@62af221149ae4887",
          "uuid" => "c3f3c22c-c22e-4bb6-ad16-635f069148e4",
        ],
      ],
      'model' => [
        "c3f3c22c-c22e-4bb6-ad16-635f069148e4" => [
          "label" => "Page title",
          "label_display" => "0",
          "provider" => "core",
        ],
      ],
    ];
    $stark_highlighted = $regions['stark.highlighted']->forAutoSaveData($layoutData, validate: TRUE);
    $autoSave->saveEntity($stark_highlighted);

    $url = $this->getLayoutUrl($entity);

    // Draft of highlighted region in global template should be returned even if
    // there is no auto-save data for the node.
    $response = $this->request(Request::create($url->toString()));
    $expected_title = match(TRUE) {
      ContentTemplate::ENTITY_TYPE_ID === $entity_type && $this->previewEntity instanceof ContentEntityInterface => $this->previewEntity->label(),
      default => $entity->label(),
    };
    $this->assertTitle($expected_title . ' | Drupal');
    $this->assertResponseAutoSaves($response, [$entity], TRUE);
    $json = static::decodeResponse($response);
    self::assertArrayHasKey('layout', $json);
    $highlightedRegion = \array_filter($json['layout'], static fn (array $region) => ($region['id'] ?? NULL) === 'highlighted');
    self::assertCount(1, $highlightedRegion);
    self::assertArrayHasKey('model', $json);
    self::assertArrayHasKey('c3f3c22c-c22e-4bb6-ad16-635f069148e4', $json['model']);
    self::assertEquals('Page title', $json['model']['c3f3c22c-c22e-4bb6-ad16-635f069148e4']['resolved']['label']);
    self::assertEquals([
      [
        "nodeType" => "component",
        "slots" => [],
        "type" => "block.page_title_block@62af221149ae4887",
        "uuid" => "c3f3c22c-c22e-4bb6-ad16-635f069148e4",
        'name' => NULL,
      ],
    ], reset($highlightedRegion)['components']);

    $original_entity = $entity::load($entity->id());
    \assert($original_entity instanceof $entity);
    // Remove the adapted image.
    $tree_loader = $this->container->get(ComponentTreeLoader::class);
    \assert($tree_loader instanceof ComponentTreeLoader);
    $tree = $tree_loader->load($original_entity);
    $delta = $tree->getComponentTreeDeltaByUuid(CanvasTestSetup::UUID_ADAPTED_IMAGE);
    \assert($delta !== NULL);
    $tree->removeItem($delta);
    // Update the title.
    if ($original_entity instanceof Node) {
      $new_title = $this->getRandomGenerator()->sentences(10);
      $original_entity->setTitle($new_title);
      // Note we use a string here.
      $original_entity->set('status', '1');
    }
    else {
      \assert($original_entity instanceof ComponentTreeEntityInterface);
      $original_entity->setComponentTree($tree->getValue());
    }

    $autoSave->saveEntity($original_entity);
    $response = $this->request(Request::create($url->toString()));
    $this->assertResponseAutoSaves($response, [$original_entity], TRUE);

    // Extract HTML from JSON response for title assertion
    $expected_title = match(TRUE) {
      ContentTemplate::ENTITY_TYPE_ID === $entity_type && $this->previewEntity instanceof ContentEntityInterface => $this->previewEntity->label(),
      default => $original_entity->label(),
    };
    $this->assertTitle($expected_title . " | Drupal");

    $json = static::decodeResponse($response);
    self::assertArrayHasKey('layout', $json);
    $highlightedRegion = \array_filter($json['layout'], static fn (array $region) => ($region['id'] ?? NULL) === 'highlighted');
    self::assertCount(1, $highlightedRegion);
    self::assertArrayHasKey('model', $json);
    self::assertArrayHasKey('c3f3c22c-c22e-4bb6-ad16-635f069148e4', $json['model']);
    self::assertEquals('Page title', $json['model']['c3f3c22c-c22e-4bb6-ad16-635f069148e4']['resolved']['label']);
    self::assertEquals([
      [
        "nodeType" => "component",
        "slots" => [],
        "type" => "block.page_title_block@62af221149ae4887",
        "uuid" => "c3f3c22c-c22e-4bb6-ad16-635f069148e4",
        'name' => NULL,
      ],
    ], reset($highlightedRegion)['components']);
    if ($original_entity instanceof Node) {
      \assert(isset($new_title));
      self::assertEquals($new_title, $json['entity_form_fields']['title[0][value]']);
    }
    else {
      self::assertArrayNotHasKey('entity_form_fields', $json);
    }

    // Now let's remove the draft of the page region but retain that of the
    // node.
    $autoSave->delete($regions['stark.highlighted']);
    // We should still see the global regions.
    $response = $this->request(Request::create($url->toString()));
    $this->assertResponseAutoSaves($response, [$original_entity], TRUE);
    $json = static::decodeResponse($response);
    self::assertArrayHasKey('layout', $json);
    $highlightedRegion = \array_filter($json['layout'], static fn (array $region) => ($region['id'] ?? NULL) === 'highlighted');
    self::assertCount(1, $highlightedRegion);
    // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup::setup()
    self::assertEquals([
      [
        "nodeType" => "component",
        "slots" => [],
        "type" => "block.page_title_block@62af221149ae4887",
        'name' => NULL,
      ],
    ],
      // Filter out the UUID as that is added randomly by creating the block
      // in the setup class.
      \array_map(static fn(array $component) => \array_diff_key($component, \array_flip(['uuid'])), \current($highlightedRegion)['components']));

    // Test that saving the exact values as the stored/live node, no auto-saves
    // remain.
    $original_entity = $entity::load($entity->id());
    assert($original_entity instanceof $entity);
    $autoSave->saveEntity($original_entity);
    $response = $this->request(Request::create($url->toString()));
    $this->assertResponseAutoSaves($response, [$original_entity], TRUE);
  }

  protected function assertRegions(int $count, EntityInterface $entity): NodeInterface {
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    $url = $this->getLayoutUrl($entity);
    // Draft of highlighted region in global template should be returned even if
    // there is no auto-save data for the node.
    $response = $this->request(Request::create($url->toString()));
    $json = static::decodeResponse($response);
    $this->assertArrayHasKey('layout', $json);
    $this->assertCount($count, $json['layout']);
    self::assertArrayHasKey('html', $json);
    $content = $this->getRegion('content');
    $this->assertNotNull($content);

    foreach ($json['layout'] as $region) {
      $this->assertArrayHasKey('nodeType', $region);
      $this->assertSame('region', $region['nodeType']);
      $this->assertArrayHasKey('id', $region);
      $this->assertArrayHasKey('name', $region);
      $this->assertArrayHasKey('components', $region);

      if ($region['id'] === 'highlighted') {
        // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup::setup()
        $this->assertEquals([
          [
            "nodeType" => "component",
            'name' => NULL,
            "slots" => [],
            "type" => "block.page_title_block@62af221149ae4887",
          ],
        ],
          // Filter out the UUID as that is added randomly by creating the block
          // in the setup class.
          \array_map(static fn(array $component) => \array_diff_key($component, \array_flip(['uuid'])), $region['components']));
        continue;
      }
      if ($region['id'] === 'sidebar_first') {
        // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup::setup()
        // @see \Drupal\canvas\Entity\PageRegion::createFromBlockLayout()
        $this->assertSame([
          [
            "nodeType" => "component",
            "type" => "block.system_messages_block@b92f802cf68eb83e",
            'name' => NULL,
            "slots" => [],
          ],
        ],
          // Filter out the UUID as that is added randomly by creating the block
          // in the setup class.
          \array_map(static fn(array $component) => \array_diff_key($component, \array_flip(['uuid'])), $region['components']));
        continue;
      }
      if ($region['id'] !== CanvasPageVariant::MAIN_CONTENT_REGION) {
        $this->assertEmpty($region['components']);
        continue;
      }
      $this->assertSame('Content', $region['name']);
      $this->assertSame([
        [
          'uuid' => CanvasTestSetup::UUID_TWO_COLUMN_UUID,
          'nodeType' => 'component',
          'type' => 'sdc.canvas_test_sdc.two_column@b1ae1e286c75438e',
          'name' => NULL,
          'slots' => [
            [
              'id' => CanvasTestSetup::UUID_TWO_COLUMN_UUID . '/column_one',
              'name' => 'column_one',
              'nodeType' => 'slot',
              'components' => [
                [
                  'uuid' => CanvasTestSetup::UUID_STATIC_IMAGE,
                  'nodeType' => 'component',
                  'type' => 'sdc.canvas_test_sdc.image@cc9b97c9370aabdf',
                  'name' => NULL,
                  'slots' => [],
                ],
                [
                  'uuid' => CanvasTestSetup::UUID_STATIC_CARD1,
                  'nodeType' => 'component',
                  'type' => 'sdc.canvas_test_sdc.my-hero@888412021fbcc837',
                  'name' => NULL,
                  'slots' => [],
                ],
                [
                  'uuid' => CanvasTestSetup::UUID_CODE_COMPONENT,
                  'nodeType' => 'component',
                  'type' => 'js.test-code-component@b09d5da457224c5e',
                  'name' => NULL,
                  'slots' => [],
                ],
                [
                  'uuid' => CanvasTestSetup::UUID_ALL_SLOTS_EMPTY,
                  'nodeType' => 'component',
                  'type' => 'sdc.canvas_test_sdc.one_column@0555ab081a3c8721',
                  'name' => NULL,
                  'slots' => [
                    [
                      'id' => CanvasTestSetup::UUID_ALL_SLOTS_EMPTY . '/content',
                      'name' => 'content',
                      'nodeType' => 'slot',
                      'components' => [],
                    ],
                  ],
                ],
              ],
            ],
            [
              'id' => CanvasTestSetup::UUID_TWO_COLUMN_UUID . '/column_two',
              'name' => 'column_two',
              'nodeType' => 'slot',
              'components' => [
                [
                  'uuid' => CanvasTestSetup::UUID_STATIC_CARD2,
                  'nodeType' => 'component',
                  'type' => 'sdc.canvas_test_sdc.my-hero@888412021fbcc837',
                  'name' => NULL,
                  'slots' => [],
                ],
                [
                  'uuid' => CanvasTestSetup::UUID_STATIC_CARD3,
                  'nodeType' => 'component',
                  'type' => 'sdc.canvas_test_sdc.my-hero@888412021fbcc837',
                  'name' => NULL,
                  'slots' => [],
                ],
                [
                  'uuid' => CanvasTestSetup::UUID_ADAPTED_IMAGE,
                  'nodeType' => 'component',
                  'type' => 'sdc.canvas_test_sdc.image@cc9b97c9370aabdf',
                  'name' => 'Magnificent image!',
                  'slots' => [],
                ],
              ],
            ],
          ],
        ],
      ], $region['components']);
    }

    self::assertIsArray($json);
    if ($entity instanceof NodeInterface) {
      $this->assertArrayHasKey('entity_form_fields', $json);
      $this->assertSame($node->label(), $json['entity_form_fields']['title[0][value]']);
    }

    self::assertEquals([
      'resolved' => [
        'heading' => 'Canvas Needs This For The Time Being',
        'cta1href' => 'https://drupal.org',
      ],
      'source' => [
        'heading' => [
          'sourceType' => 'static:field_item:string',
          'expression' => 'ℹ︎string␟value',

        ],
        'cta1href' => [
          'sourceType' => 'static:field_item:uri',
          'expression' => 'ℹ︎uri␟value',
        ],
      ],
    ], $json['model'][CanvasTestSetup::UUID_STATIC_CARD2]);
    return $node;
  }

  public function testStatusFlags(): void {
    $this->setUpCurrentUser(permissions: [Page::CREATE_PERMISSION, Page::EDIT_PERMISSION]);

    $request = Request::create('/canvas/api/v0/content/canvas_page', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([], JSON_THROW_ON_ERROR));
    $content = $this->parentRequest($request)->getContent();

    self::assertIsString($content);
    $entity_id = (int) json_decode($content, TRUE)['entity_id'];
    $entity = Page::load($entity_id);
    self::assertInstanceOf(Page::class, $entity);
    $this->assertStatusFlags($entity, TRUE, FALSE);

    $entity->set('title', 'Here we go')->save();
    $this->assertStatusFlags($entity, FALSE, FALSE);

    $entity->setPublished()->save();
    $this->assertStatusFlags($entity, FALSE, TRUE);

    $contentTemplate = $this->getTestEntity(ContentTemplate::ENTITY_TYPE_ID);
    \assert($contentTemplate instanceof ContentTemplate);
    self::assertFalse($contentTemplate->status());
    $this->setUpCurrentUser([], [self::getAdminPermission($contentTemplate)]);
    $this->assertStatusFlags($contentTemplate, TRUE, NULL);

    $contentTemplate->setStatus(TRUE)->save();
    $this->assertStatusFlags($contentTemplate, FALSE, NULL);
  }

  private function assertStatusFlags(EntityInterface $entity, bool $isNew, ?bool $isPublished): void {
    $content = $this->parentRequest(Request::create($this->getLayoutUrl($entity)->toString()))->getContent();
    self::assertIsString($content);
    $json = json_decode($content, TRUE);
    self::assertSame($isNew, $json['isNew']);
    self::assertSame($isPublished, $json['isPublished'] ?? NULL);
  }

  /**
   * Tests that auto-save entries with inaccessible fields do not cause errors.
   *
   * @covers \Drupal\canvas\Controller\ApiLayoutController::buildPreviewRenderable
   */
  public function testInaccessibleFieldsInAutoSave(): void {
    // Create a node to work with.
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Node',
    ]);
    $node->save();

    // Set up the current user without access to path field.
    $authenticated_role = $this->createRole(['edit any article content']);
    $limited_user = $this->createUser([], NULL, FALSE, ['roles' => [$authenticated_role]]);
    assert($limited_user instanceof User);
    $this->setCurrentUser($limited_user);

    // Create an auto-save entry with a value for a field that the user doesn't have access to.
    $autoSave = $this->container->get(AutoSaveManager::class);
    assert($autoSave instanceof AutoSaveManager);

    $node->set('path', ['alias' => '/test-path']);
    $autoSave->saveEntity($node);

    $url = Url::fromRoute('canvas.api.layout.get', [
      'entity' => $node->id(),
      'entity_type' => 'node',
    ]);

    // This should not throw an exception even though the auto-save data
    // contains a value for path field that the user doesn't have access to.
    $response = $this->request(Request::create($url->toString()));

    // Verify that the response is successful.
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

    // Check that the response contains the correct title.
    $json = static::decodeResponse($response);
    self::assertArrayHasKey('entity_form_fields', $json);
    self::assertEquals('Test Node', $json['entity_form_fields']['title[0][value]']);
    $entity_form_fields = $json['entity_form_fields'];
    // Expand form values from their respective element name, e.g.
    // ['title[0][value]' => 'Node title'] becomes
    // ['title' => ['value' => 'Node title']].
    // @see \Drupal\canvas\Controller\ApiLayoutController::getEntityData
    \parse_str(\http_build_query($entity_form_fields), $entity_form_fields);
    self::assertArrayNotHasKey('path', $entity_form_fields);
  }

  public function testFieldException(): void {
    $page_type = NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $page_type->save();
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
    ]);
    $node->save();
    /** @var \Drupal\canvas\Controller\ApiLayoutController $controller */
    $controller = \Drupal::classResolver(ApiLayoutController::class);
    $this->expectException(\LogicException::class);
    // @todo Fix in https://drupal.org/i/3498525 for testing a bundle where a
    //   canvas field is not present.
    // @see \Drupal\canvas\Storage\ComponentTreeLoader::getCanvasFieldName
    $this->expectExceptionMessage('For now Canvas only works if the entity is a canvas_page! Other entity types and bundles must use content templates for now, see https://drupal.org/i/3498525');
    $controller->get($node);
  }

  /**
   * @return \Drupal\canvas\Entity\PageRegion[]
   */
  protected function enableGlobalRegions(string $theme = 'stark', int $expected_region_count = 11): array {
    $this->container->get('theme_installer')->install([$theme]);
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', $theme)
      ->save();
    $this->container->get('theme.manager')->resetActiveTheme();

    $regions = PageRegion::createFromBlockLayout($theme);
    // Check that all the theme regions get a corresponding PageRegion config
    // entity (except the "content" region).
    self::assertCount($expected_region_count, $regions);
    foreach ($regions as $region) {
      $region->save();
    }
    return $regions;
  }

  /**
   * Data provider for testFieldAccess.
   *
   * @return array[]
   *   Test data with permissions and expected results.
   */
  public static function fieldAccessProvider(): array {
    return [
      'no_permissions' => [
        'permissions' => ['access content'],
        'exception_message' => "The 'edit canvas_page' permission is required.",
      ],
      'entity_edit_only' => [
        'permissions' => [Page::EDIT_PERMISSION],
        'exception_message' => 'You do not have permission to edit this field.',
      ],
      'field_edit_only' => [
        // @see \canvas_test_field_access_entity_field_access()
        'permissions' => ['edit canvas page components'],
        'exception_message' => "The 'edit canvas_page' permission is required.",
      ],
      'both_permissions' => [
        'permissions' => [Page::EDIT_PERMISSION, 'edit canvas page components'],
        'exception_message' => NULL,
      ],
    ];
  }

  /**
   * Tests field access for the Drupal Canvas API layout.
   *
   * @dataProvider fieldAccessProvider
   */
  public function testFieldAccess(array $permissions, ?string $exception_message): void {
    $this->container->get('module_installer')->install(['canvas_test_field_access']);
    $this->setUpCurrentUser([], $permissions);

    // Test field access using URL/request approach rather than directly calling controller
    // to ensure proper route resolution and access checking.
    $page = Page::create([
      'title' => 'Test page',
      'description' => 'This is a test page.',
      'components' => [
        [
          'uuid' => CanvasTestSetup::UUID_COMPONENT_SDC,
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'inputs' => [
            'heading' => [
              'sourceType' => 'static:field_item:string',
              'value' => 'Welcome to the site!',
              'expression' => 'ℹ︎string␟value',
            ],
          ],
        ],
      ],
    ]);
    $page->save();

    $url = Url::fromRoute('canvas.api.layout.get', [
      'entity' => $page->id(),
      'entity_type' => Page::ENTITY_TYPE_ID,
    ]);

    if ($exception_message !== NULL) {
      $this->expectException(AccessDeniedHttpException::class);
      $this->expectExceptionMessage($exception_message);
      $this->parentRequest(Request::create($url->toString()));
    }
    else {
      $response = $this->parentRequest(Request::create($url->toString()));
      $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
  }

  /**
   * @covers \Drupal\canvas\Routing\ContentTemplatePreviewEntityConverter
   */
  public function testPreviewEntityValidation(): void {
    $this->setUpCurrentUser([], [ContentTemplate::ADMIN_PERMISSION]);
    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
    ]);
    self::assertCount(0, $node->validate());
    $node->save();
    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();
    $ineligible_preview_node = Node::create([
      'type' => 'page',
      'title' => $this->randomMachineName(),
    ]);
    self::assertCount(0, $ineligible_preview_node->validate());
    $ineligible_preview_node->save();
    $contentTemplate = $this->getTestEntity(ContentTemplate::ENTITY_TYPE_ID);

    // Existing node ID, but of invalid bundle.
    $bad_preview_url = Url::fromRoute('canvas.api.layout.get.content_template', [
      'entity' => $contentTemplate->id(),
      'preview_entity' => $ineligible_preview_node->id(),
    ]);
    try {
      $this->request(Request::create($bad_preview_url->toString()));
      $this->fail('Expected exception not thrown');
    }
    catch (ParamNotConvertedException $e) {
      self::assertSame('The "preview_entity" parameter was not converted because the `node` content entity with ID 5 is of the bundle `page`, should be `article`.', $e->getMessage());
    }

    // Non-existing node ID.
    $bad_preview_url = Url::fromRoute('canvas.api.layout.get.content_template', [
      'entity' => $contentTemplate->id(),
      'preview_entity' => 42,
    ]);
    try {
      $this->request(Request::create($bad_preview_url->toString()));
      $this->fail('Expected exception not thrown');
    }
    catch (ParamNotConvertedException $e) {
      self::assertSame('The "preview_entity" parameter was not converted because a `node` content entity with ID 42 does not exist.', $e->getMessage());
    }

    $url = Url::fromRoute('canvas.api.layout.get.content_template', [
      'entity' => $contentTemplate->id(),
      'entity_type' => ContentTemplate::ENTITY_TYPE_ID,
      'preview_entity' => $node->id(),
    ]);

    // Ensure that the user must have 'view' access to the preview entity.
    $node->setUnpublished()->save();
    try {
      $this->request(Request::create($url->toString()));
      $this->fail('Expected exception not thrown');
    }
    catch (CacheableAccessDeniedHttpException) {
    }

    $node->setPublished()->save();
    $this->container->get(EntityTypeManagerInterface::class)->getAccessControlHandler('node')->resetCache();
    $response = $this->request(Request::create($url->toString()));
    $this->assertEquals(200, $response->getStatusCode(), 'Response status code is 200 OK');
  }

}
