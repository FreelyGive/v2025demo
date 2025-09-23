<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Controller;

use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Entity\Pattern;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\canvas\Kernel\Traits\PageTrait;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\Kernel\Traits\CanvasUiAssertionsTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the Drupal Canvas UI mount for various entity types.
 *
 * @group canvas
 */
final class CanvasControllerTest extends KernelTestBase {

  use PageTrait;
  use RequestTrait;
  use UserCreationTrait;
  use CanvasUiAssertionsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'entity_test',
    ...self::PAGE_TEST_MODULES,
    'block',
    'node',
    // Canvas's dependencies (modules providing field types + widgets).
    'text',
    'datetime',
    'file',
    'image',
    'media',
    'options',
    'path',
    'link',
    'system',
    'user',
  ];

  protected function setUp(): void {
    parent::setUp();
    // Needed for date formats.
    $this->installConfig(['system']);
    $this->installConfig(['node']);
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('node_type');
    $this->installEntitySchema('node');

    NodeType::create([
      'name' => 'Amazing article',
      'type' => 'article',
    ])->save();
    $field_storage = FieldStorageConfig::create([
      'type' => 'component_tree',
      'entity_type' => 'node',
      'field_name' => 'field_canvas_tree',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
    ])->save();
  }

  /**
   * Tests controller output when adding or editing an entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param array $permissions
   *   The permissions.
   * @param array $values
   *   The values.
   * @param null|string $expected_exception_message
   *   Consider removing in https://www.drupal.org/i/3498525.
   *
   * @dataProvider entityData
   */
  public function testController(string $entity_type, array $permissions, array $values, ?string $expected_exception_message = NULL): void {
    $this->installEntitySchema($entity_type);

    $this->setUpCurrentUser([], $permissions);

    $storage = $this->container->get('entity_type.manager')->getStorage($entity_type);
    $sut = $storage->create($values);
    $sut->save();

    $edit_url = Url::fromRoute('canvas.boot.entity', [
      'entity_type' => $entity_type,
      'entity' => $sut->id(),
    ])->toString();
    self::assertEquals("/canvas/editor/$entity_type/{$sut->id()}", $edit_url);

    if ($expected_exception_message) {
      $this->expectException(CacheableAccessDeniedHttpException::class);
      $this->expectExceptionMessage($expected_exception_message);
    }

    /** @var \Drupal\Core\Render\HtmlResponse $response */
    $response = $this->request(Request::create($edit_url));

    self::assertSame([
      'user.permissions',
      'languages:language_interface',
      'theme',
    ], $response->getCacheableMetadata()->getCacheContexts());
    self::assertSame([
      'config:system.site',
      'http_response',
    ], $response->getCacheableMetadata()->getCacheTags());

    $this->assertCanvasMount();
  }

  public static function entityData(): array {
    return [
      'page' => [
        Page::ENTITY_TYPE_ID,
        [Page::CREATE_PERMISSION, Page::EDIT_PERMISSION],
        [
          'title' => 'Test page',
          'description' => 'This is a test page.',
          'components' => [],
        ],
      ],
      'entity_test' => [
        'entity_test',
        ['administer entity_test content', 'access content'],
        [
          'name' => 'Test entity',
        ],
        'Requires >=1 content entity type with an Canvas field that can be created or edited.',
      ],
    ];
  }

  /**
   * Tests controller exposed permissions.
   *
   * @param array $permissions
   *   The permissions.
   * @param array $expectedPermissionFlags
   *   The expected flags.
   *
   * @dataProvider permissionsData
   */
  public function testControllerExposedPermissions(array $permissions, array $expectedPermissionFlags): void {
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);

    $this->setUpCurrentUser([], $permissions);

    $canvas_url = Url::fromRoute('canvas.boot.empty', [
      'entity_type' => '',
      'entity' => '',
    ])->toString();
    self::assertEquals("/canvas", $canvas_url);

    /** @var \Drupal\Core\Render\HtmlResponse $response */
    $response = $this->request(Request::create($canvas_url));

    $this->assertSame($expectedPermissionFlags, $this->drupalSettings['canvas']['permissions']);
    self::assertSame([
      'user.permissions',
      'languages:language_interface',
      'theme',
    ], $response->getCacheableMetadata()->getCacheContexts());
    self::assertSame([
      'config:system.site',
      'http_response',
    ], $response->getCacheableMetadata()->getCacheTags());
  }

  public static function permissionsData(): array {
    // @see \Drupal\canvas\Entity\PageAccessControlHandler
    $page_permissions = [
      'access content',
      Page::CREATE_PERMISSION,
      Page::EDIT_PERMISSION,
      Page::DELETE_PERMISSION,
    ];

    return [
      [
        [
          ...$page_permissions,
        ],
        [
          'globalRegions' => FALSE,
          'patterns' => FALSE,
          'codeComponents' => FALSE,
          'contentTemplates' => FALSE,
          'publishChanges' => FALSE,
        ],
      ],
      [
        [
          ...$page_permissions,
          JavaScriptComponent::ADMIN_PERMISSION,
          AutoSaveManager::PUBLISH_PERMISSION,
        ],
        [
          'globalRegions' => FALSE,
          'patterns' => FALSE,
          'codeComponents' => TRUE,
          'contentTemplates' => FALSE,
          'publishChanges' => TRUE,
        ],
      ],
      [
        [
          ...$page_permissions,
          Pattern::ADMIN_PERMISSION,
          PageRegion::ADMIN_PERMISSION,
        ],
        [
          'globalRegions' => TRUE,
          'patterns' => TRUE,
          'codeComponents' => FALSE,
          'contentTemplates' => FALSE,
          'publishChanges' => FALSE,
        ],
      ],
      [
        [
          ...$page_permissions,
          Pattern::ADMIN_PERMISSION,
          PageRegion::ADMIN_PERMISSION,
          JavaScriptComponent::ADMIN_PERMISSION,
        ],
        [
          'globalRegions' => TRUE,
          'patterns' => TRUE,
          'codeComponents' => TRUE,
          'contentTemplates' => FALSE,
          'publishChanges' => FALSE,
        ],
      ],
      [
        [
          ...$page_permissions,
          Pattern::ADMIN_PERMISSION,
          PageRegion::ADMIN_PERMISSION,
          JavaScriptComponent::ADMIN_PERMISSION,
          ContentTemplate::ADMIN_PERMISSION,
          AutoSaveManager::PUBLISH_PERMISSION,
        ],
        [
          'globalRegions' => TRUE,
          'patterns' => TRUE,
          'codeComponents' => TRUE,
          'contentTemplates' => TRUE,
          'publishChanges' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Tests controller exposed content entity create operations.
   *
   * @param array $permissions
   *   The permissions.
   * @param array $expectedCreateOperations
   *   The expected create operations array.
   *
   * @dataProvider createOperationsData
   */
  public function testControllerExposedContentEntityCreateOperations(array $permissions, array $expectedCreateOperations): void {
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);

    $this->setUpCurrentUser([], $permissions);

    $canvas_url = Url::fromRoute('canvas.boot.empty', [
      'entity_type' => '',
      'entity' => '',
    ])->toString();
    self::assertEquals("/canvas", $canvas_url);

    /** @var \Drupal\Core\Render\HtmlResponse $response */
    $response = $this->request(Request::create($canvas_url));

    $this->assertSame($expectedCreateOperations, $this->drupalSettings['canvas']['contentEntityCreateOperations']);
    self::assertSame([
      'user.permissions',
      'languages:language_interface',
      'theme',
    ], $response->getCacheableMetadata()->getCacheContexts());
    self::assertSame([
      'config:system.site',
      'http_response',
    ], $response->getCacheableMetadata()->getCacheTags());
  }

  public static function createOperationsData(): array {
    return [
      [
        [
          'access content',
          Page::CREATE_PERMISSION,
        ],
        [
          'canvas_page' => [
            'canvas_page' => 'Page',
          ],
        ],
      ],
      [
        [
          'access content',
          Page::CREATE_PERMISSION,
          'create article content',
        ],
        [
          'canvas_page' => [
            'canvas_page' => 'Page',
          ],
          'node' => [
            'article' => 'Amazing article',
          ],
        ],
      ],
    ];
  }

  /**
   * Tests controller feature flags.
   *
   * @param array $modules
   *   The modules to enable.
   * @param array $expectedFeatureFlags
   *   The expected feature flags values.
   *
   * @dataProvider featureFlagsData
   */
  public function testControllerExposedFeatureFlags(array $modules, array $expectedFeatureFlags): void {
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $permissions = [
      'access content',
      Page::CREATE_PERMISSION,
      Page::EDIT_PERMISSION,
      Page::DELETE_PERMISSION,
    ];
    if (!empty($modules)) {
      $this->enableModules($modules);
    }

    $this->setUpCurrentUser([], $permissions);

    $canvas_url = Url::fromRoute('canvas.boot.empty', [
      'entity_type' => '',
      'entity' => '',
    ])->toString();
    self::assertEquals("/canvas", $canvas_url);

    /** @var \Drupal\Core\Render\HtmlResponse $response */
    $response = $this->request(Request::create($canvas_url));

    foreach ($expectedFeatureFlags as $featureFlag => $featureFlagValue) {
      $this->assertSame($featureFlagValue, $this->drupalSettings['canvas'][$featureFlag]);
    }

    self::assertSame([
      'user.permissions',
      'languages:language_interface',
      'theme',
    ], $response->getCacheableMetadata()->getCacheContexts());
    self::assertSame([
      'config:system.site',
      'http_response',
    ], $response->getCacheableMetadata()->getCacheTags());

  }

  public static function featureFlagsData(): \Generator {
    yield 'none' => [
      [],
      [
        'aiExtensionAvailable' => FALSE,
        'personalizationExtensionAvailable' => FALSE,
      ],
    ];
    yield 'ai' => [
      ['canvas_ai'],
      [
        'aiExtensionAvailable' => TRUE,
        'personalizationExtensionAvailable' => FALSE,
      ],
    ];
    yield 'personalization' => [
      ['canvas_personalization'],
      [
        'aiExtensionAvailable' => FALSE,
        'personalizationExtensionAvailable' => TRUE,
      ],
    ];
    yield 'all' => [
      ['canvas_ai', 'canvas_personalization'],
      [
        'aiExtensionAvailable' => TRUE,
        'personalizationExtensionAvailable' => TRUE,
      ],
    ];
  }

}
