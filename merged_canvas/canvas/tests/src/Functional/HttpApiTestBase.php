<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface;
use Drupal\Tests\ApiRequestTrait;
use Drupal\Tests\canvas\Traits\AutoSaveManagerTestTrait;
use Drupal\user\UserInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for functional tests of Canvas's internal HTTP API.
 *
 * Provides helper methods for making API requests and asserting response cacheability.
 *
 * @group canvas
 */
abstract class HttpApiTestBase extends FunctionalTestBase {

  use ApiRequestTrait;
  use AutoSaveManagerTestTrait;

  /**
   * @return ?array
   *   The decoded JSON response, or NULL if there is no body.
   *
   * @throws \JsonException
   */
  protected function assertExpectedResponse(string $method, Url $url, array $request_options, int $expected_status, ?array $expected_cache_contexts, ?array $expected_cache_tags, ?string $expected_page_cache, ?string $expected_dynamic_page_cache, array $additional_expected_response_headers = []): ?array {
    $request_options['headers']['X-CSRF-Token'] = $this->drupalGet('session/token');
    $response = $this->makeApiRequest($method, $url, $request_options);
    $body = (string) $response->getBody();
    $this->assertSame($expected_status, $response->getStatusCode(), $body);

    // Cacheability headers.
    $this->assertSame($expected_page_cache !== NULL, $response->hasHeader('X-Drupal-Cache'));
    if ($expected_page_cache !== NULL) {
      $this->assertSame($expected_page_cache, $response->getHeader('X-Drupal-Cache')[0], 'Page Cache response header');
    }
    $this->assertSame($expected_dynamic_page_cache !== NULL, $response->hasHeader('X-Drupal-Dynamic-Cache'));
    if ($expected_dynamic_page_cache !== NULL) {
      $this->assertSame($expected_dynamic_page_cache, $response->getHeader('X-Drupal-Dynamic-Cache')[0], 'Dynamic Page Cache response header');
    }
    $this->assertSame($expected_cache_tags !== NULL, $response->hasHeader('X-Drupal-Cache-Tags'));
    if ($expected_cache_tags !== NULL) {
      $this->assertEqualsCanonicalizing($expected_cache_tags, explode(' ', $response->getHeader('X-Drupal-Cache-Tags')[0]));
    }
    $this->assertSame($expected_cache_contexts !== NULL, $response->hasHeader('X-Drupal-Cache-Contexts'));
    if ($expected_cache_contexts !== NULL) {
      $this->assertEqualsCanonicalizing($expected_cache_contexts, explode(' ', $response->getHeader('X-Drupal-Cache-Contexts')[0]));
    }

    // Optionally, additional expected response headers can be validated.
    if ($additional_expected_response_headers) {
      foreach ($additional_expected_response_headers as $header_name => $expected_value) {
        $this->assertSame($response->getHeader($header_name), $expected_value);
      }
    }

    // Response must at least be decodable JSON, let this throw an exception
    // otherwise. (Assertions of the contents happen outside this method.)
    if ($body === '') {
      return NULL;
    }
    $json = json_decode($body, associative: TRUE, flags: JSON_THROW_ON_ERROR);

    return $json;
  }

  /**
   * Asserts the given data can be auto-saved (and retrieved) correctly.
   */
  protected function performAutoSave(array $data_to_auto_save, array $expected_auto_save_entity, string $entity_type_id, string $entity_id): void {
    static $clientIdNumber = 0;
    $auto_save_url = Url::fromUri("base:/canvas/api/v0/config/auto-save/$entity_type_id/$entity_id");
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];
    $getResponse = $this->makeApiRequest('GET', $auto_save_url, $request_options);
    self::assertSame(Response::HTTP_OK, $getResponse->getStatusCode());
    $decoded = json_decode((string) $getResponse->getBody(), associative: TRUE, flags: JSON_THROW_ON_ERROR);
    self::assertArrayHasKey('autoSaves', $decoded);
    $autoSaves = $decoded['autoSaves'];
    $request_options[RequestOptions::JSON] = [
      'data' => $data_to_auto_save,
      'autoSaves' => $autoSaves,
      // Use a unique client instance ID to ensure that auto-save hashes are
      // always verified, except when we are explicitly testing using the same
      // client.
      // @see \Drupal\Tests\canvas\Kernel\AutoSave\AutoSaveConflictTestTrait::testOutdatedAutoSave()
      // @see \Drupal\canvas\Controller\AutoSaveTrait::validateAutoSaves()
      'clientInstanceId' => 'test-client-' . (++$clientIdNumber),
    ];
    $patch_response = $this->assertExpectedResponse('PATCH', $auto_save_url, $request_options, 200, NULL, NULL, NULL, NULL);
    $entity = $this->container->get(EntityTypeManagerInterface::class)->getStorage($entity_type_id)->load($entity_id);
    self::assertSame($this->getClientAutoSaves([$entity]), $patch_response);

    $this->assertCurrentAutoSave(200, $expected_auto_save_entity, $entity_type_id, $entity_id);
  }

  /**
   * Asserts the current auto-save data for the given entity.
   */
  protected function assertCurrentAutoSave(int $expected_status_code, ?array $expected_auto_save, string $entity_type_id, string $entity_id): void {
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];
    $auto_save_url = Url::fromUri("base:/canvas/api/v0/config/auto-save/$entity_type_id/$entity_id");
    // Because the 'autoSaveStartingPoint`, which is returned from the
    // $auto_save_url GET request, is created using the saved entity in addition
    // to the auto-save entry we need to expect the cache tags of the entity.
    // @see \Drupal\canvas\Controller\AutoSaveValidateTrait::getClientAutoSaveData()
    $entity = $this->container->get(EntityTypeManagerInterface::class)->getStorage($entity_type_id)->load($entity_id);
    $cacheTags = [AutoSaveManager::CACHE_TAG, 'http_response'];
    if ($entity instanceof EntityInterface) {
      $cacheTags = Cache::mergeTags($cacheTags, $entity->getCacheTags());
    }

    // First GET request: auto-save retrieved successfully?
    // - 200 if there is a current auto-save
    // - 204 if there isn't one
    // - 404 if this entity does not exist (anymore)
    if ($expected_status_code < 400) {
      $auto_save_data = $this->assertExpectedResponse('GET', $auto_save_url, $request_options, $expected_status_code, ['user.permissions'], $cacheTags, 'UNCACHEABLE (request policy)', 'MISS');
      $this->assertIsArray($auto_save_data);
      $this->assertArrayHasKey('data', $auto_save_data);
      $this->assertSame($expected_auto_save, $auto_save_data['data']);
    }
    else {
      $this->assertExpectedResponse('GET', $auto_save_url, $request_options, $expected_status_code, NULL, NULL, 'UNCACHEABLE (request policy)', 'UNCACHEABLE (no cacheability)');
    }

    if ($expected_status_code < 400) {
      // Repeat the same request: same status code, but now is a Dynamic Page
      // Cache hit.
      $auto_save_data = $this->assertExpectedResponse('GET', $auto_save_url, $request_options, $expected_status_code, ['user.permissions'], $cacheTags, 'UNCACHEABLE (request policy)', 'HIT');
      $this->assertIsArray($auto_save_data);
      $this->assertArrayHasKey('data', $auto_save_data);
      $this->assertSame($expected_auto_save, $auto_save_data['data']);
    }

    // The expected array must also match what the AutoSaveManager currently contains.
    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage($entity_type_id);
    $entity = $storage->loadUnchanged($entity_id);
    // When the underlying entity has been deleted, parameter upcasting fails
    // and a 404 is the result: no auto-saves for deleted entities.
    if ($expected_status_code === 404) {
      // The entity is deleted.
      $this->assertNull($entity);
      // No corresponding auto-save entries exists.
      $auto_save_keys = \array_keys($this->container->get(AutoSaveManager::class)->getAllAutoSaveList());
      // Auto save keys start with the entity type ID and ID, but could also
      // include a language ID if the entity supports translation.
      self::assertCount(0, \array_filter($auto_save_keys, static fn (string $key): bool => \str_starts_with($key, "$entity_type_id:$entity_id")));
      return;
    }
    \assert($entity instanceof EntityInterface);
    $data = $this->container->get(AutoSaveManager::class)->getAutoSaveEntity($entity)->entity;
    \assert($data instanceof CanvasHttpApiEligibleConfigEntityInterface);
    $this->assertSame($expected_auto_save, $data->normalizeForClientSide()->values);
  }

  /**
   * Asserts we can delete a resource, and we get an empty list afterward.
   */
  protected function assertDeletionAndEmptyList(Url $resource_url, Url $list_url, string $list_cache_tag, array $default_list = []): void {
    // Delete the sole remaining segment via the Canvas HTTP API: 204.
    $body = $this->assertExpectedResponse('DELETE', $resource_url, [], 204, NULL, NULL, NULL, NULL);
    $this->assertNull($body);

    // Re-retrieve list: 200, empty list. Dynamic Page Cache miss.
    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, ['user.permissions'], [$list_cache_tag, 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    if (empty($default_list)) {
      $this->assertSame([], $body);
    }
    else {
      // If the default list is not empty, check against that instead of an
      // empty array. We only expect this for the Folder config entity type.
      // @see \Drupal\Tests\canvas\Functional\CanvasConfigEntityHttpApiTest::$defaultFolders
      assert(str_contains($list_url->getUri(), 'folder'));
      $this->assertSameFoldersSansUuids($default_list, $body ?? []);
    }
    $individual_body = $this->assertExpectedResponse('GET', $resource_url, [], 404, NULL, NULL, 'UNCACHEABLE (request policy)', 'UNCACHEABLE (no cacheability)');
    if (empty($default_list)) {
      $this->assertSame([], $individual_body);
    }
  }

  protected function assertSingleConfigAutoSaveList(EntityInterface $entity, UserInterface $user): void {
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];
    $expected_list = [
      $entity->getEntityTypeId() . ':' . $entity->id() => [
        'owner' => [
          'name' => $user->getDisplayName(),
          'avatar' => NULL,
          'uri' => $user->toUrl()->toString(),
          'id' => (int) $user->id(),
        ],
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
        'langcode' => 'en',
        'label' => $entity->label(),
      ],
    ];
    $body = $this->assertExpectedResponse('GET', Url::fromUri("base:/canvas/api/v0/auto-saves/pending"), $request_options, 200, ['user.permissions'], [...$entity->getCacheTags(), 'config:user.settings', AutoSaveManager::CACHE_TAG, 'http_response', "user:{$user->id()}"], 'UNCACHEABLE (request policy)', 'MISS');
    $id = array_keys($expected_list)[0];
    \assert(\is_array($body));
    self::assertArrayHasKey($id, $body);
    self::assertArrayHasKey('data_hash', $body[$id]);
    self::assertArrayHasKey('updated', $body[$id]);
    unset($body[$id]['updated'], $body[$id]['data_hash']);
    $this->assertSame($expected_list, $body);
  }

  /**
   * Asserts that two Folders match other than their UUIDs.
   *
   * (Folder auto-creation means that names of Folders are predictable, but not
   * their UUIDs, which are randomly generated by the configuration system.)
   */
  protected function assertSameFoldersSansUuids(array $expected, array $actual): void {
    $this->assertCount(count($expected), $actual);

    $expected_values = array_map(function ($item) {
      unset($item['id']);
      $items = $item['items'];
      asort($items);
      $item['items'] = array_values($items);
      return $item;
    }, array_values($expected));
    usort($expected_values, function ($a, $b) {
      return strcmp($a['name'], $b['name']);
    });

    $actual_values = array_map(function ($item) {
      unset($item['id']);
      $items = $item['items'];
      asort($items);
      $item['items'] = array_values($items);
      return $item;
    }, array_values($actual));
    usort($actual_values, function ($a, $b) {
      return strcmp($a['name'], $b['name']);
    });
    $this->assertSame($expected_values, $actual_values);
  }

}
