<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\CanvasUriDefinitions;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use GuzzleHttp\RequestOptions;

/**
 * @covers \Drupal\canvas\Controller\ApiContentControllers
 * @group canvas
 * @internal
 */
final class CanvasContentEntityHttpApiTest extends HttpApiTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Page::create([
      'title' => "Page 1",
      'status' => TRUE,
      'path' => ['alias' => "/page-1"],
    ])->save();
    Page::create([
      'title' => "Page 2",
      'status' => FALSE,
    ])->save();
    Page::create([
      'title' => "Page 3",
      'status' => TRUE,
      'path' => ['alias' => "/page-3"],
    ])->save();
    // Set the page 2 to be the homepage.
    $this->config('system.site')
      ->set('page.front', '/page/2')
      ->save();
  }

  public function testPost(): void {
    $url = Url::fromUri('base:/canvas/api/v0/content/canvas_page');
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::JSON => [],
    ];

    $this->assertAuthenticationAndAuthorization($url, 'POST');

    $request_options['headers']['X-CSRF-Token'] = $this->drupalGet('session/token');
    // Authenticated, authorized, with CSRF token: 201.
    Role::load('authenticated')?->grantPermission(Page::CREATE_PERMISSION)->save();
    $response = $this->makeApiRequest('POST', $url, $request_options);
    $this->assertSame(201, $response->getStatusCode());
    $this->assertSame(
      '{"entity_type":"canvas_page","entity_id":"4"}',
      (string) $response->getBody()
    );
  }

  public function testList(): void {
    $url = Url::fromUri('base:/canvas/api/v0/content/canvas_page');

    $this->assertAuthenticationAndAuthorization($url, 'GET');

    // Authenticated, authorized: 200.
    $user = $this->createUser([Page::EDIT_PERMISSION], 'administer_canvas_page_user');
    assert($user instanceof UserInterface);
    $this->drupalLogin($user);
    // We have a cache tag for page 2 as it's the homepage, set in system.site
    // config.
    $expected_tags = [
      AutoSaveManager::CACHE_TAG,
      'config:system.site',
      'http_response',
      'canvas_page:2',
      'canvas_page_list',
    ];
    $body = $this->assertExpectedResponse('GET', $url, [], 200, ['url.query_args:search', 'user.permissions'], $expected_tags, 'UNCACHEABLE (request policy)', 'MISS');
    $no_auto_save_expected_pages = [
      // Page 1 has a path alias.
      '1' => [
        'id' => 1,
        'title' => 'Page 1',
        'status' => TRUE,
        'path' => base_path() . 'page-1',
        'autoSaveLabel' => NULL,
        'autoSavePath' => NULL,
        'links' => [
          // @todo https://www.drupal.org/i/3498525 should standardize arguments.
          CanvasUriDefinitions::LINK_REL_EDIT => Url::fromUri('base:/canvas/editor/canvas_page/1')->toString(),
          CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => Url::fromUri('base:/canvas/editor/canvas_page/1')->toString(),
        ],
        'internalPath' => '/page/1',
      ],
      // Page 2 has no path alias.
      '2' => [
        'id' => 2,
        'title' => 'Page 2',
        'status' => FALSE,
        'path' => base_path() . 'page/2',
        'autoSaveLabel' => NULL,
        'autoSavePath' => NULL,
        'links' => [
          // @todo https://www.drupal.org/i/3498525 should standardize arguments.
          CanvasUriDefinitions::LINK_REL_EDIT => Url::fromUri('base:/canvas/editor/canvas_page/2')->toString(),
          CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => Url::fromUri('base:/canvas/editor/canvas_page/2')->toString(),
        ],
        'internalPath' => '/page/2',
      ],
      '3' => [
        'id' => 3,
        'title' => 'Page 3',
        'status' => TRUE,
        'path' => base_path() . 'page-3',
        'autoSaveLabel' => NULL,
        'autoSavePath' => NULL,
        'links' => [
          // @todo https://www.drupal.org/i/3498525 should standardize arguments.
          CanvasUriDefinitions::LINK_REL_EDIT => Url::fromUri('base:/canvas/editor/canvas_page/3')->toString(),
          CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => Url::fromUri('base:/canvas/editor/canvas_page/3')->toString(),
        ],
        'internalPath' => '/page/3',
      ],
    ];
    $this->assertEquals(
      $no_auto_save_expected_pages,
      $body
    );
    $this->assertExpectedResponse('GET', $url, [], 200, ['url.query_args:search', 'user.permissions'], $expected_tags, 'UNCACHEABLE (request policy)', 'HIT');

    // Test searching by query parameter
    $search_url = Url::fromUri('base:/canvas/api/v0/content/canvas_page', ['query' => ['search' => 'Page 1']]);
    // Because page 2 isn't in these results, we don't get its cache tag.
    $expected_tags_without_page_2 = \array_diff($expected_tags, ['canvas_page:2']);
    // Confirm that the cache is not hit when a different request is made with query parameter.
    $search_body = $this->assertExpectedResponse('GET', $search_url, [], 200, ['languages:' . LanguageInterface::TYPE_CONTENT, 'url.query_args:search', 'user.permissions'], $expected_tags_without_page_2, 'UNCACHEABLE (request policy)', 'MISS');
    $this->assertEquals(
      [
        '1' => [
          'id' => 1,
          'title' => 'Page 1',
          'status' => TRUE,
          'path' => base_path() . 'page-1',
          'autoSaveLabel' => NULL,
          'autoSavePath' => NULL,
          'links' => [
            // @todo https://www.drupal.org/i/3498525 should remove the hardcoded `canvas_page` from these.
            CanvasUriDefinitions::LINK_REL_EDIT => Url::fromUri('base:/canvas/editor/canvas_page/1')->toString(),
            CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => Url::fromUri('base:/canvas/editor/canvas_page/1')->toString(),
          ],
          'internalPath' => '/page/1',
        ],
      ],
      $search_body
    );
    // Confirm that the cache is hit when the same request is made again.
    $this->assertExpectedResponse('GET', $search_url, [], 200, ['languages:' . LanguageInterface::TYPE_CONTENT, 'url.query_args:search', 'user.permissions'], $expected_tags_without_page_2, 'UNCACHEABLE (request policy)', 'HIT');

    // Test searching by query parameter - substring match.
    $substring_search_url = Url::fromUri('base:/canvas/api/v0/content/canvas_page', ['query' => ['search' => 'age']]);
    $substring_search_body = $this->assertExpectedResponse('GET', $substring_search_url, [], 200, ['languages:' . LanguageInterface::TYPE_CONTENT, 'url.query_args:search', 'user.permissions'], $expected_tags, 'UNCACHEABLE (request policy)', 'MISS');
    $this->assertEquals($no_auto_save_expected_pages, $substring_search_body);

    $autoSaveManager = $this->container->get(AutoSaveManager::class);
    $page_1 = Page::load(1);
    $this->assertInstanceOf(Page::class, $page_1);
    $page_1->set('title', 'The updated title.');
    $page_1->set('path', ['alias' => "/the-updated-path"]);
    $autoSaveManager->saveEntity($page_1);

    $page_2 = Page::load(2);
    $this->assertInstanceOf(Page::class, $page_2);
    $page_2->set('title', 'The updated title2.');
    $page_2->set('path', ['alias' => "/the-new-path"]);
    $autoSaveManager->saveEntity($page_2);

    $body = $this->assertExpectedResponse('GET', $url, [], 200, ['url.query_args:search', 'user.permissions'], $expected_tags, 'UNCACHEABLE (request policy)', 'MISS');
    $auto_save_expected_pages = $no_auto_save_expected_pages;
    $auto_save_expected_pages['1']['autoSaveLabel'] = 'The updated title.';
    $auto_save_expected_pages['1']['autoSavePath'] = '/the-updated-path';
    $auto_save_expected_pages['2']['autoSaveLabel'] = 'The updated title2.';
    $auto_save_expected_pages['2']['autoSavePath'] = '/the-new-path';
    $this->assertEquals(
      $auto_save_expected_pages,
      $body
    );
    $this->assertExpectedResponse('GET', $url, [], 200, ['url.query_args:search', 'user.permissions'], $expected_tags, 'UNCACHEABLE (request policy)', 'HIT');

    // Confirm that if path alias is empty, the system path is used, not the
    // existing alias if set.
    $page_1->set('title', 'The updated title.');
    $page_1->set('path', NULL);
    $autoSaveManager->saveEntity($page_1);

    $page_2->set('title', 'The updated title2.');
    $page_2->set('path', NULL);
    $autoSaveManager->saveEntity($page_2);

    $body = $this->assertExpectedResponse('GET', $url, [], 200, ['url.query_args:search', 'user.permissions'], $expected_tags, 'UNCACHEABLE (request policy)', 'MISS');
    $auto_save_expected_pages['1']['autoSavePath'] = '/page/1';
    $auto_save_expected_pages['2']['autoSavePath'] = '/page/2';
    $this->assertEquals(
      $auto_save_expected_pages,
      $body
    );

    $autoSaveManager->delete($page_1);
    $autoSaveManager->delete($page_2);
    $body = $this->assertExpectedResponse('GET', $url, [], 200, ['url.query_args:search', 'user.permissions'], $expected_tags, 'UNCACHEABLE (request policy)', 'MISS');
    $this->assertEquals(
      $no_auto_save_expected_pages,
      $body
    );
    $this->assertExpectedResponse('GET', $url, [], 200, ['url.query_args:search', 'user.permissions'], $expected_tags, 'UNCACHEABLE (request policy)', 'HIT');
  }

  /**
   * @param list<string> $extraCacheContexts
   * @param list<string> $extraCacheTags
   * @dataProvider metaOperationsProvider
   */
  public function testListMetaOperations(array $permissions, array $expectedLinks, array $extraCacheContexts = [], array $extraCacheTags = []): void {
    $url = Url::fromUri('base:/canvas/api/v0/content/canvas_page');
    array_walk($expectedLinks, fn(&$value) => $value = Url::fromUri($value)->toString());
    // Enable canvas_test_access, which will disable view permission for page 1
    // and add extra cache contexts and cache tags.
    $this->container->get('module_installer')->install(['canvas_test_access']);
    \Drupal::keyValue('canvas_test_access')->set('cache_contexts', $extraCacheContexts);
    \Drupal::keyValue('canvas_test_access')->set('cache_tags', $extraCacheTags);

    $user = $this->createUser($permissions);
    assert($user instanceof UserInterface);
    $this->drupalLogin($user);
    // We have a cache tag for page 2 as it's the homepage, set in system.site
    // config.
    $body = $this->assertExpectedResponse('GET', $url, [], 200, Cache::mergeContexts(['url.query_args:search', 'user.permissions'], $extraCacheContexts), Cache::mergeTags([AutoSaveManager::CACHE_TAG, 'config:system.site', 'http_response', 'canvas_page:2', 'canvas_page_list'], $extraCacheTags), 'UNCACHEABLE (request policy)', 'MISS');
    assert(\is_array($body));
    assert(\array_key_exists('1', $body) && \array_key_exists('links', $body['1']));
    $this->assertEquals(
      $expectedLinks,
      $body['1']['links']
    );
  }

  public static function metaOperationsProvider(): array {
    // All of them require Page::EDIT_PERMISSION, that's a requirement for the
    // controller itself.
    return [
      'can edit' => [
        [Page::EDIT_PERMISSION],
        [
          CanvasUriDefinitions::LINK_REL_EDIT => 'base:/canvas/editor/canvas_page/1',
          CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => 'base:/canvas/editor/canvas_page/1',
        ],
      ],
      'can edit and delete' => [
        [Page::EDIT_PERMISSION, Page::DELETE_PERMISSION],
        [
          CanvasUriDefinitions::LINK_REL_EDIT => 'base:/canvas/editor/canvas_page/1',
          CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => 'base:/canvas/editor/canvas_page/1',
          CanvasUriDefinitions::LINK_REL_DELETE => 'base:/canvas/api/v0/content/canvas_page/1',
        ],
      ],
      'can create, edit and delete' => [
        [Page::CREATE_PERMISSION, Page::EDIT_PERMISSION, Page::DELETE_PERMISSION],
        [
          CanvasUriDefinitions::LINK_REL_EDIT => 'base:/canvas/editor/canvas_page/1',
          CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => 'base:/canvas/editor/canvas_page/1',
          CanvasUriDefinitions::LINK_REL_DUPLICATE => 'base:/canvas/api/v0/content/canvas_page',
          CanvasUriDefinitions::LINK_REL_DELETE => 'base:/canvas/api/v0/content/canvas_page/1',
        ],
      ],
      'can create and edit, with extra cache metadata' => [
        [Page::CREATE_PERMISSION, Page::EDIT_PERMISSION],
        [
          CanvasUriDefinitions::LINK_REL_EDIT => 'base:/canvas/editor/canvas_page/1',
          CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => 'base:/canvas/editor/canvas_page/1',
          CanvasUriDefinitions::LINK_REL_DUPLICATE => 'base:/canvas/api/v0/content/canvas_page',
        ],
        ['headers:X-Something'],
        ['zzz'],
      ],
    ];
  }

  public function testDelete(): void {
    $url = Url::fromUri('base:/canvas/api/v0/content/canvas_page/1');
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];

    $this->assertAuthenticationAndAuthorization($url, 'DELETE');

    $request_options['headers']['X-CSRF-Token'] = $this->drupalGet('session/token');
    // Authenticated, unauthorized, with CSRF token: 403.
    $response = $this->makeApiRequest('DELETE', $url, $request_options);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame(['errors' => ["The 'delete canvas_page' permission is required."]], json_decode((string) $response->getBody(), TRUE));

    // Authenticated, authorized, with CSRF token: 204.
    Role::load('authenticated')?->grantPermission(Page::DELETE_PERMISSION)->save();
    $response = $this->makeApiRequest('DELETE', $url, $request_options);
    $this->assertSame(204, $response->getStatusCode());
    $this->assertNull(\Drupal::entityTypeManager()->getStorage(Page::ENTITY_TYPE_ID)->load(1));

    // Try to delete the page 2, which is set as homepage.
    $url = Url::fromUri('base:/canvas/api/v0/content/canvas_page/2');
    $response = $this->makeApiRequest('DELETE', $url, $request_options);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame(
      ['errors' => ['This entity cannot be deleted because its path is set as the homepage.']],
      json_decode((string) $response->getBody(), TRUE)
    );
  }

  public function testDeleteOperationInList(): void {
    $url = Url::fromUri('base:/canvas/api/v0/content/canvas_page');

    $this->assertAuthenticationAndAuthorization($url, 'GET');

    // Authenticated, authorized: 200.
    $user = $this->createUser([Page::EDIT_PERMISSION, Page::DELETE_PERMISSION], 'administer_canvas_page_user');
    assert($user instanceof UserInterface);
    $this->drupalLogin($user);
    $body = $this->assertExpectedResponse('GET', $url, [], 200, ['url.query_args:search', 'user.permissions'], [AutoSaveManager::CACHE_TAG, 'config:system.site', 'http_response', 'canvas_page:2', 'canvas_page_list'], 'UNCACHEABLE (request policy)', 'MISS');
    assert(\is_array($body));
    assert(\array_key_exists('2', $body) && \array_key_exists('links', $body['2']));
    $this->assertEquals(
      [
        CanvasUriDefinitions::LINK_REL_EDIT => Url::fromUri('base:/canvas/editor/canvas_page/2')->toString(),
        CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => Url::fromUri('base:/canvas/editor/canvas_page/2')->toString(),
      ],
      $body['2']['links'],
      'Links for page 2 should not include delete operation, as it is set as homepage.'
    );
    // Assert links for page 1.
    assert(\array_key_exists('1', $body) && \array_key_exists('links', $body['1']));
    $this->assertEquals(
      [
        CanvasUriDefinitions::LINK_REL_EDIT => Url::fromUri('base:/canvas/editor/canvas_page/1')->toString(),
        CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => Url::fromUri('base:/canvas/editor/canvas_page/1')->toString(),
        CanvasUriDefinitions::LINK_REL_DELETE => Url::fromUri('base:/canvas/api/v0/content/canvas_page/1')->toString(),
      ],
      $body['1']['links'],
      'Links for page 1 should include delete operation.'
    );
    // Assert links for page 3.
    assert(\array_key_exists('3', $body) && \array_key_exists('links', $body['3']));
    $this->assertEquals(
      [
        CanvasUriDefinitions::LINK_REL_EDIT => Url::fromUri('base:/canvas/editor/canvas_page/3')->toString(),
        CanvasUriDefinitions::LINK_REL_DELETE => Url::fromUri('base:/canvas/api/v0/content/canvas_page/3')->toString(),
        CanvasUriDefinitions::LINK_REL_SET_AS_HOMEPAGE => Url::fromUri('base:/canvas/editor/canvas_page/3')->toString(),
      ],
      $body['3']['links'],
      'Links for page 3 should include delete operation.'
    );
  }

  public function testDuplicate(): void {
    $url = Url::fromUri('base:/canvas/api/v0/content/canvas_page');
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::JSON => ['entity_id' => '10'],
    ];

    $this->assertAuthenticationAndAuthorization($url, 'POST');
    // Authenticated, authorized, with CSRF token: 204.
    $request_options['headers']['X-CSRF-Token'] = $this->drupalGet('session/token');
    Role::load('authenticated')?->grantPermission(Page::CREATE_PERMISSION)->save();

    // Try to duplicate a non-existent entity.
    $response = $this->makeApiRequest('POST', $url, $request_options);
    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame(
      '{"error":"Cannot find entity to duplicate."}',
      (string) $response->getBody()
    );

    $original = \Drupal::entityTypeManager()->getStorage('canvas_page')->load(1);
    assert($original instanceof ContentEntityInterface);
    $this->assertEquals('Page 1', $original->label());
    self::assertFalse($original->get('path')->isEmpty());
    self::assertNotNull($original->get('path')->first()?->get('alias')->getValue());

    $request_options[RequestOptions::JSON] = ['entity_id' => $original->id()];

    // Test module will return view access forbidden for canvas_page id 1 instance.
    $this->container->get('module_installer')->install(['canvas_test_access']);

    // Try to duplicate entity without view access.
    $response = $this->makeApiRequest('POST', $url, $request_options);
    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame(
      '{"error":"Cannot find entity to duplicate."}',
      (string) $response->getBody()
    );

    // Turn off module to have proper view access.
    $this->container->get('module_installer')->uninstall(['canvas_test_access']);
    // Duplicate Page 1 entity.
    $response = $this->makeApiRequest('POST', $url, $request_options);
    $this->assertSame(201, $response->getStatusCode());
    $this->assertSame(
      '{"entity_type":"canvas_page","entity_id":"4"}',
      (string) $response->getBody()
    );
    $duplicate_1 = \Drupal::entityTypeManager()->getStorage('canvas_page')->load(4);
    assert($duplicate_1 instanceof ContentEntityInterface);
    $this->assertEquals('Page 1 (Copy)', $duplicate_1->label());
    self::assertNull($duplicate_1->get('path')->first()?->get('alias')->getValue());

    // Add temp store data for Previous duplicate.
    $auto_save_manager = \Drupal::service(AutoSaveManager::class);
    $duplicate_1->set('title', 'Title from temp store');
    $auto_save_manager->saveEntity($duplicate_1);

    $url = Url::fromUri('base:/canvas/api/v0/content/canvas_page');
    $request_options[RequestOptions::JSON] = ['entity_id' => $duplicate_1->id()];
    $response = $this->makeApiRequest('POST', $url, $request_options);
    $this->assertSame(201, $response->getStatusCode());
    $this->assertSame(
      '{"entity_type":"canvas_page","entity_id":"5"}',
      (string) $response->getBody()
    );

    $duplicate_2 = \Drupal::entityTypeManager()->getStorage('canvas_page')->load(5);
    assert($duplicate_2 instanceof EntityInterface);
    // Test that the data from the temp store is present.
    $this->assertEquals('Title from temp store (Copy)', $duplicate_2->label());
    $this->assertNotEmpty($auto_save_manager->getAutoSaveEntity($original));
    // Autosaved data is empty in duplicate.
    self::assertTrue($auto_save_manager->getAutoSaveEntity($duplicate_2)->isEmpty());
    self::assertNull($duplicate_2->get('path')->first()?->get('alias')->getValue());
  }

  private function assertAuthenticationAndAuthorization(Url $url, string $method): void {
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];

    // Authenticated but unauthorized: 403 due to missing CSRF token.
    $user = $this->createUser([]);
    assert($user instanceof UserInterface);
    $this->drupalLogin($user);
    if ($method !== 'GET') {
      $response = $this->makeApiRequest($method, $url, $request_options);
      $this->assertSame(403, $response->getStatusCode());
      $this->assertSame(
        ['errors' => ['X-CSRF-Token request header is missing']],
        json_decode((string) $response->getBody(), TRUE)
      );
    }

    // Authenticated but unauthorized: 403 due to missing permission.
    $request_options['headers']['X-CSRF-Token'] = $this->drupalGet('session/token');
    $response = $this->makeApiRequest($method, $url, $request_options);
    $this->assertSame(403, $response->getStatusCode());

    $error = match ($method) {
      'POST' => "The 'create canvas_page' permission is required.",
      'DELETE' => "The 'delete canvas_page' permission is required.",
      // GET method
      default => "The 'edit canvas_page' permission is required.",
    };
    $this->assertSame(
      ['errors' => [$error]],
      json_decode((string) $response->getBody(), TRUE)
    );
  }

}
