<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Controller;

// cspell:ignore Gábor Hojtsy uniquesearchterm gàbor autosave searchterm

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Controller\ApiContentControllers;
use Drupal\canvas\Entity\Page;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the ApiContentControllers::list() method.
 *
 * @group canvas
 * @coversDefaultClass \Drupal\canvas\Controller\ApiContentControllers
 */
class ApiContentControllersListTest extends KernelTestBase {
  use UserCreationTrait;

  /**
   * Base path for the content API endpoint.
   *
   * @todo Strip `canvas_page` in https://www.drupal.org/i/3498525, and add test coverage for other content entity types.
   */
  private const API_BASE_PATH = '/api/canvas/content/canvas_page';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'system',
    'canvas_test_page',
    'user',
    'field',
    'text',
    'filter',
    'path_alias',
    'path',
    'media',
    'image',
  ];

  /**
   * The API Content controller service.
   *
   * @var \Drupal\canvas\Controller\ApiContentControllers
   */
  protected ApiContentControllers $apiContentController;

  /**
   * The AutoSaveManager service.
   *
   * @var \Drupal\canvas\AutoSave\AutoSaveManager
   */
  protected AutoSaveManager $autoSaveManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected TransliterationInterface $transliteration;

  /**
   * Test pages.
   *
   * @var \Drupal\canvas\Entity\Page[]
   */
  protected $pages = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('canvas_page');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('media');
    $this->installConfig(['system', 'field', 'filter', 'path_alias']);

    // Create a user with appropriate permissions.
    $this->setUpCurrentUser([], ['access content', Page::CREATE_PERMISSION, Page::EDIT_PERMISSION, Page::DELETE_PERMISSION]);

    $this->apiContentController = $this->container->get(ApiContentControllers::class);
    $this->autoSaveManager = $this->container->get(AutoSaveManager::class);
    $this->entityTypeManager = $this->container->get(EntityTypeManagerInterface::class);
    $this->transliteration = $this->container->get('transliteration');

    $this->createTestPages();
  }

  /**
   * Creates test pages for the tests.
   */
  protected function createTestPages(): void {
    $page1 = Page::create([
      'title' => "Published Canvas Page",
      'status' => TRUE,
      'path' => ['alias' => "/page-1"],
    ]);
    $page1->save();
    $this->pages['published'] = $page1;

    $page2 = Page::create([
      'title' => "Unpublished Canvas Page",
      'status' => FALSE,
    ]);
    $page2->save();
    $this->pages['unpublished'] = $page2;

    // Create page with unique searchable title.
    $page3 = Page::create([
      'title' => "UniqueSearchTerm Canvas Page",
      'status' => TRUE,
      'path' => ['alias' => "/page-3"],
    ]);
    $page3->save();
    $this->pages['searchable'] = $page3;

    // Create a page with diacritical marks (accents) in title.
    $page4 = Page::create([
      'title' => "Gábor Hojtsy Page",
      'status' => TRUE,
      'path' => ['alias' => "/page-4"],
    ]);
    $page4->save();
    $this->pages['accented'] = $page4;
  }

  /**
   * Creates auto-save data for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to create auto-save data for.
   * @param string $label
   *   The label to use in auto-save data.
   * @param string|null $path
   *   The path alias to use in auto-save data.
   */
  protected function createAutoSaveData(ContentEntityInterface $entity, string $label, ?string $path = NULL): void {
    $autoSaveEntity = $entity::create($entity->toArray());
    $autoSaveEntity->set('title', $label);
    if ($path !== NULL) {
      $autoSaveEntity->set('path', ['alias' => $path]);
    }
    $this->autoSaveManager->saveEntity($autoSaveEntity);
  }

  /**
   * Helper method to execute list request and return parsed response data.
   *
   * @param array $query
   *   Optional query parameters.
   *
   * @return array
   *   Parsed JSON response data.
   */
  protected function executeListRequest(array $query = []): array {
    $request = Request::create(self::API_BASE_PATH, 'GET', $query);
    $response = $this->apiContentController->list(Page::ENTITY_TYPE_ID, $request);

    $content = $response->getContent();
    self::assertNotEmpty($content);

    return json_decode($content, TRUE);
  }

  /**
   * Helper method to validate page data in response.
   *
   * @param array $response_data
   *   The response data to validate.
   * @param array $expected_search_result_data
   *   Expected search result data to validate against.
   * @param array $expected_auto_save_data
   *   Optional auto-save data to validate.
   */
  protected function assertValidResultData(array $response_data, array $expected_search_result_data, array $expected_auto_save_data = []): void {
    // Assert that all expected fields are present and correct
    foreach ($expected_search_result_data as $key => $expected_value) {
      self::assertArrayHasKey($key, $response_data, "Response should contain key: {$key}");
      self::assertSame($expected_value, $response_data[$key], "Value for {$key} should match expected value");
    }

    if (!empty($expected_auto_save_data['label'])) {
      self::assertSame($expected_auto_save_data['label'], $response_data['autoSaveLabel']);
    }

    if (isset($expected_auto_save_data['path'])) {
      self::assertSame($expected_auto_save_data['path'], $response_data['autoSavePath']);
    }
  }

  /**
   * Tests basic list functionality with no search parameter.
   *
   * @covers ::list
   */
  public function testBasicList(): void {
    $response = $this->apiContentController->list('canvas_page', Request::create(self::API_BASE_PATH, 'GET'));

    $content = $response->getContent();
    self::assertNotEmpty($content, 'Response content should not be empty');

    $data = json_decode($content, TRUE);
    self::assertIsArray($data, 'Response data should be an array');

    self::assertCount(count($this->pages), $data, 'Response should contain all test pages');

    foreach ($this->pages as $page) {
      $page_id = (int) $page->id();
      self::assertArrayHasKey($page_id, $data, "Page {$page_id} should be in the results");
      $this->assertValidResultData($data[$page_id], $this->getEntityData($page));
    }

    $cache_metadata = $response->getCacheableMetadata();

    // Expected cache tags should include entity list tag, auto-save tag,
    // and individual entity tags
    $expected_cache_tags = [
      'canvas_page_list',
      // Access check on home-page adds this.
      'config:system.site',
      AutoSaveManager::CACHE_TAG,
    ];
    $actual_cache_tags = $cache_metadata->getCacheTags();

    $expected_cache_contexts = [
      'url.query_args:search',
      'user.permissions',
    ];
    $actual_cache_contexts = $cache_metadata->getCacheContexts();
    self::assertEquals($expected_cache_tags, $actual_cache_tags, 'All expected cache tags should be present');
    self::assertEquals($expected_cache_contexts, $actual_cache_contexts, 'All expected cache contexts should be present');
  }

  /**
   * Tests list method with search parameter.
   *
   * @covers ::list
   */
  public function testListWithSearch(): void {
    $data = $this->executeListRequest(['search' => 'UniqueSearchTerm']);
    self::assertCount(1, $data, 'Search should return exactly one result');
    $page_id = (int) $this->pages['searchable']->id();
    self::assertArrayHasKey($page_id, $data, "Searchable page should be in the results");
    $this->assertValidResultData($data[$page_id], $this->getEntityData($this->pages['searchable']));

    $data = $this->executeListRequest(['search' => 'Canvas Page']);
    self::assertGreaterThan(1, count($data), 'Search should return multiple results');

    $data = $this->executeListRequest(['search' => 'NoMatchingTerm']);
    self::assertEmpty($data, 'Search with no matches should return empty array');

    $data = $this->executeListRequest(['search' => 'uniquesearchterm']);
    self::assertCount(1, $data, 'Search should be case-insensitive');

    $database_type = $this->container->get('database')->driver();
    if ($database_type !== 'pgsql' && $database_type !== 'sqlite') {
      // LIKE queries that perform transliteration are MYSQL/MariaDB specific.
      $data = $this->executeListRequest(['search' => 'Gábor']);
      self::assertCount(1, $data, 'Search with accented character should match page');
      $page_id = (int) $this->pages['accented']->id();
      self::assertArrayHasKey($page_id, $data, "Accented page should be in the results");
      $this->assertValidResultData($data[$page_id], $this->getEntityData($this->pages['accented']));

      $data = $this->executeListRequest(['search' => 'gabor']);
      self::assertCount(1, $data, 'Search without accent should match page with accented character');
      $page_id = (int) $this->pages['accented']->id();
      self::assertArrayHasKey($page_id, $data, "Accented page should be in the results");
      $this->assertValidResultData($data[$page_id], $this->getEntityData($this->pages['accented']));
    }
    $data = $this->executeListRequest(['search' => 'puBliSHed']);
    self::assertCount(2, $data, 'Search with mixed case should match published and unpublished page');
  }

  /**
   * Tests search when no searchable content entities (currently only pages) exist yet.
   *
   * @covers ::list
   */
  public function testEmptyEntityList(): void {
    foreach ($this->pages as $page) {
      $page->delete();
    }
    $this->pages = [];

    $data = $this->executeListRequest();
    self::assertSame([], $data, 'Search should return empty array when no entities exist');

    // Now create a temporary page with auto-save data and then delete it
    // to test interaction with orphaned auto-save data
    $temp_page = Page::create([
      'title' => "Temporary Page for AutoSave",
      'status' => TRUE,
    ]);
    $temp_page->save();

    $this->createAutoSaveData($temp_page, "AutoSave Only Content", "/autosave-only");

    // Verify auto-save data was created by checking that it would be found if entity existed
    $temp_page_id = (int) $temp_page->id();
    $data = $this->executeListRequest(['search' => 'AutoSave Only Content']);
    self::assertCount(1, $data, 'Auto-save data should be found when entity exists');
    self::assertArrayHasKey($temp_page_id, $data, 'Page with auto-save data should be in results');

    // Now delete the page, leaving orphaned auto-save data
    $temp_page->delete();

    // Test search with no entities but orphaned auto-save data should return empty
    $data = $this->executeListRequest(['search' => 'AutoSave Only Content']);
    self::assertSame([], $data, 'Search should return empty results when auto-save data exists but no entities exist');

    // Test search with general term should also return empty
    $data = $this->executeListRequest(['search' => 'Temporary']);
    self::assertSame([], $data, 'Search should return empty results when searching for deleted entity content');
  }

  /**
   * Tests that search results are sorted by most recently updated first.
   *
   * @covers ::list
   * @covers ::filterAndMergeIds
   */
  public function testSearchSortOrder(): void {
    // Create test pages with the same search term but different titles
    $pages_data = [
      'page1' => "Canvas Search Term One",
      'page2' => "Canvas Search Term Two",
      'page3' => "Canvas Search Term Three",
    ];

    $page_ids = [];

    foreach ($pages_data as $key => $title) {
      $page = Page::create([
        'title' => $title,
        'status' => TRUE,
      ]);
      $page->save();
      $this->pages[$key] = $page;
      $page_ids[$key] = (int) $page->id();
    }

    // Update the pages in reverse order to change their revision timestamps
    // This ensures page1 is most recently updated, followed by page2, then page3
    $update_order = array_reverse(array_keys($pages_data));
    foreach ($update_order as $key) {
      $this->pages[$key]->set('title', "{$pages_data[$key]} Updated");
      $this->pages[$key]->save();
    }

    $data = $this->executeListRequest(['search' => 'Canvas Search Term']);
    self::assertCount(3, $data, 'Search should return all three matching pages');

    // Get the IDs in order they appear in the results
    $result_ids = array_map(function ($item) {
      return $item['id'];
    }, $data);

    $result_ids = array_keys($result_ids);

    // Verify the order is by most recently updated (page1, page2, page3)
    self::assertSame($page_ids['page1'], $result_ids[2]);
    self::assertSame($page_ids['page2'], $result_ids[1]);
    self::assertSame($page_ids['page3'], $result_ids[0]);
  }

  /**
   * Tests auto-save entries in search results.
   *
   * @covers ::list
   * @covers ::filterAndMergeIds
   */
  public function testSearchWithAutoSave(): void {
    $page = Page::create([
      'title' => "Original Title Page",
      'status' => TRUE,
      'path' => ['alias' => "/original-page"],
    ]);
    $page->save();

    $this->createAutoSaveData($page, "AutoSave SearchTerm Title", "/autosave-path");
    $data = $this->executeListRequest(['search' => 'AutoSave SearchTerm']);
    self::assertCount(1, $data, 'Search should find the page with matching auto-save data');

    // Verify that both original and auto-save data are correctly included
    $page_id = (int) $page->id();
    self::assertArrayHasKey($page_id, $data);
    self::assertSame($page_id, $data[$page_id]['id']);
    self::assertSame($page->label(), $data[$page_id]['title']);
    self::assertSame("AutoSave SearchTerm Title", $data[$page_id]['autoSaveLabel']);
    self::assertSame("/autosave-path", $data[$page_id]['autoSavePath']);

    $data = $this->executeListRequest(['search' => 'autosave searchterm']);
    self::assertCount(1, $data, 'Search should be case-insensitive for auto-save data');

    $data = $this->executeListRequest(['search' => 'Original Title']);
    self::assertCount(1, $data, 'Search should find original title when auto-save exists');
  }

  /**
   * Extracts essential data from a Page entity for test assertions.
   *
   * @param \Drupal\Core\Entity\EntityPublishedInterface $entity
   *   The entity to extract data from.
   *
   * @return array
   *   An array containing the entity's ID, title, status, and path.
   */
  private function getEntityData(EntityPublishedInterface $entity) {
    return [
      'id' => (int) $entity->id(),
      'title' => $entity->label(),
      'status' => $entity->isPublished(),
      'internalPath' => '/' . $entity->toUrl()->getInternalPath(),
      'path' => $entity->toUrl()->toString(),
    ];
  }

}
