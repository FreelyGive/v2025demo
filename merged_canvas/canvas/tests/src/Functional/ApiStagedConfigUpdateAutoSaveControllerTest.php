<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\StagedConfigUpdate;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\user\UserInterface;
use GuzzleHttp\RequestOptions;

/**
 * Tests the details of auto-saving Staged Config Update entities.
 *
 * @covers \Drupal\canvas\Controller\ApiStagedConfigUpdateAutoSaveController
 * @group canvas
 */
class ApiStagedConfigUpdateAutoSaveControllerTest extends HttpApiTestBase {

  use ContribStrictConfigSchemaTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['canvas'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected readonly UserInterface $httpApiUser;

  protected readonly UserInterface $limitedPermissionsUser;

  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser([Page::EDIT_PERMISSION]);
    assert($user instanceof UserInterface);
    $this->httpApiUser = $user;

    // Create a user with an arbitrary permission that is not related to
    // accessing any Canvas resources.
    $user2 = $this->createUser(['view media']);
    assert($user2 instanceof UserInterface);
    $this->limitedPermissionsUser = $user2;
  }

  public function testStagedConfigUpdate(): void {
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];
    $entity_type_id = StagedConfigUpdate::ENTITY_TYPE_ID;
    $entity_id = 'canvas_change_site_name';
    $storage = $this->container->get(EntityTypeManagerInterface::class)->getStorage($entity_type_id);

    $post_url = Url::fromUri('base:/canvas/api/v0/staged-update/auto-save');
    $auto_save_url = Url::fromUri("base:/canvas/api/v0/config/auto-save/$entity_type_id/$entity_id");

    $missingPermissionError = "The 'administer site configuration' permission is required.";

    $this->drupalLogin($this->httpApiUser);

    $auto_save_data = $this->assertExpectedResponse('GET', $auto_save_url, $request_options, 404, NULL, NULL, 'UNCACHEABLE (request policy)', 'UNCACHEABLE (no cacheability)');
    $this->assertSame([], $auto_save_data);

    // Verify `'administer site configuration'` permission is required.
    $entity_data = [
      'id' => $entity_id,
      'label' => 'Change the site name',
      'target' => 'system.site',
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => [
            'name' => 'My awesome site',
          ],
        ],
      ],
    ];
    $this->assertExpectedResponse(
      'POST',
      $post_url,
      [
        RequestOptions::JSON => [
          'data' => $entity_data,
        ],
        ...$request_options,
      ],
      403,
      NULL,
      NULL,
      NULL,
      NULL
    );

    $user = $this->createUser([
      Page::EDIT_PERMISSION,
      JavaScriptComponent::ADMIN_PERMISSION,
      'administer site configuration',
    ]);
    assert($user instanceof UserInterface);
    $this->drupalLogin($user);

    $this->assertExpectedResponse(
      'POST',
      $post_url,
      [
        RequestOptions::JSON => [
          'data' => $entity_data,
        ],
        ...$request_options,
      ],
      201,
      NULL,
      NULL,
      NULL,
      NULL
    );
    $original_entity = $storage->load($entity_id);
    self::assertNotNull($original_entity);

    // Created staged config updates are stored in auto-save.
    $auto_save_data = $this->assertExpectedResponse('GET', $auto_save_url, $request_options, 200, ['user.permissions'], [AutoSaveManager::CACHE_TAG, 'config:system.site', 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    self::assertIsArray($auto_save_data);
    self::assertArrayHasKey('data', $auto_save_data);
    $this->assertSame($entity_data, $auto_save_data['data']);

    // Anonymously: 401.
    $this->drupalLogout();
    $body = $this->assertExpectedResponse('GET', $auto_save_url, [], 401, ['user.roles:anonymous'], ['4xx-response', 'config:system.site', 'config:user.role.anonymous', 'http_response'], 'MISS', NULL);
    $this->assertSame([
      'errors' => [
        'You must be logged in to access this resource.',
      ],
    ], $body);

    // Insufficient permissions: 403
    $this->drupalLogin($this->limitedPermissionsUser);
    $body = $this->assertExpectedResponse('GET', $auto_save_url, [], 403, ['user.permissions'], ['4xx-response', 'http_response'], 'UNCACHEABLE (request policy)', NULL);
    $this->assertSame([
      'errors' => [
        $missingPermissionError,
      ],
    ], $body);

    $this->drupalLogin($user);
    $entity_data['actions'][0]['input']['name'] = 'My even more awesome site';
    $this->assertExpectedResponse(
      'POST',
      $post_url,
      [
        RequestOptions::JSON => [
          'data' => $entity_data,
        ],
        ...$request_options,
      ],
      201,
      NULL,
      NULL,
      NULL,
      NULL
    );
    $updated_entity = $storage->load($entity_id);
    self::assertNotNull($updated_entity);
    $updated_actions = $updated_entity->getActions();
    \assert(isset($updated_actions[0]['input']['name']));
    $this->assertSame('My even more awesome site', $updated_actions[0]['input']['name']);

    $this->assertSingleConfigAutoSaveList($original_entity, $user);
  }

}
