<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\canvas\Entity\Page;
use Drupal\Tests\canvas\Functional\HttpApiTestBase;
use Drupal\Tests\canvas\Traits\AutoSaveManagerTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\user\UserInterface;
use Drupal\canvas_personalization\Entity\Segment;
use Drupal\canvas_personalization\Entity\SegmentInterface;
use GuzzleHttp\RequestOptions;

/**
 * Tests the details of auto-saving config entities, NOT the "live" version.
 *
 * @see \Drupal\Tests\canvas\Functional\ApiConfigAutoSaveControllersTest
 * @group canvas
 * @group canvas_personalization
 */
class ApiConfigAutoSaveControllersTest extends HttpApiTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use AutoSaveManagerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'canvas_personalization',
    // @todo Remove once ComponentSourceInterface is a public API, i.e. after https://www.drupal.org/i/3520484#stable is done.
    'canvas_dev_mode',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected readonly UserInterface $httpApiUser;

  protected readonly UserInterface $limitedPermissionsUser;

  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser([
      Page::EDIT_PERMISSION,
      Segment::ADMIN_PERMISSION,
    ]);
    assert($user instanceof UserInterface);
    $this->httpApiUser = $user;

    // Create a user with an arbitrary permission that is not related to
    // accessing any Canvas resources.
    $user2 = $this->createUser(['view media']);
    assert($user2 instanceof UserInterface);
    $this->limitedPermissionsUser = $user2;
  }

  public static function providerTest(): array {
    return [
      Segment::ENTITY_TYPE_ID => [
        Segment::ENTITY_TYPE_ID,
        [
          'id' => 'test',
          'label' => 'Test',
          'status' => FALSE,
          'rules' => [
            'utm_parameters' => [
              'id' => 'utm_parameters',
              'negate' => FALSE,
              'all' => TRUE,
              'parameters' => [
                [
                  "key" => "utm_source",
                  "value" => "my-source-id",
                  "matching" => "exact",
                ],
                [
                  "key" => "utm_campaign",
                  "value" => "HALLOWEEN",
                  "matching" => "starts_with",
                ],
              ],
            ],
          ],
          'weight' => 0,
        ],
        [
          'label' => 'Updated',
        ],
        [
          'id' => 'test',
          'label' => 'Updated',
          'description' => NULL,
          'rules' => [
            'utm_parameters' => [
              'id' => 'utm_parameters',
              'negate' => FALSE,
              'all' => TRUE,
              'parameters' => [
                [
                  "key" => "utm_source",
                  "value" => "my-source-id",
                  "matching" => "exact",
                ],
                [
                  "key" => "utm_campaign",
                  "value" => "HALLOWEEN",
                  "matching" => "starts_with",
                ],
              ],
            ],
          ],
          'weight' => 0,
          'status' => FALSE,
        ],
        "The 'administer personalization segments' permission is required.",
      ],
    ];
  }

  /**
   * @dataProvider providerTest
   */
  public function test(
    string $entity_type_id,
    array $initial_entity,
    array $patch_update,
    array $updated_entity,
    string $missingPermissionError,
  ): void {
    $entity_type_manager = $this->container->get(EntityTypeManagerInterface::class);
    $storage = $entity_type_manager->getStorage($entity_type_id);
    $definition = $entity_type_manager->getDefinition($entity_type_id);
    $id_key = $definition->getKey('id');
    assert(!empty($initial_entity[$id_key]));
    $entity_id = $initial_entity[$id_key];
    $base = rtrim(base_path(), '/');
    $post_url = Url::fromUri("base:/canvas/api/v0/config/$entity_type_id");
    $auto_save_url = Url::fromUri("base:/canvas/api/v0/config/auto-save/$entity_type_id/$entity_id");

    // Url generate will fail, as it's not a valid route, but we want to assert 404.
    $js_auto_save_url = '/canvas/api/v0/auto-saves/js/segment/test';
    $css_auto_save_url = '/canvas/api/v0/auto-saves/css/segment/test';

    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];

    $this->drupalLogin($this->httpApiUser);

    // GETting the auto-save state for a config entity when that entity does not yet exist: 404.
    $auto_save_data = $this->assertExpectedResponse('GET', $auto_save_url, $request_options, 404, NULL, NULL, 'UNCACHEABLE (request policy)', 'UNCACHEABLE (no cacheability)');
    $this->assertSame([], $auto_save_data);

    // CSS and JS draft endpoints should be 404, no matter if logged-in or not.
    $this->drupalGet($js_auto_save_url);
    $this->assertSession()->statusCodeEquals(404);
    $this->drupalGet($css_auto_save_url);
    $this->assertSession()->statusCodeEquals(404);

    $request_options[RequestOptions::JSON] = $initial_entity;
    $this->assertExpectedResponse('POST', $post_url, $request_options, 201, NULL, NULL, NULL, NULL, [
      'Location' => [
        "$base/canvas/api/v0/config/$entity_type_id/{$entity_id}",
      ],
    ]);
    $original_entity = $storage->load($entity_id);
    \assert($original_entity instanceof SegmentInterface);
    $original_entity_array = $original_entity->toArray();
    assert(is_array($original_entity_array));

    // Insufficient Permissions: 403.
    $this->drupalLogin($this->limitedPermissionsUser);
    $body = $this->assertExpectedResponse('GET', $auto_save_url, [], 403, ['user.permissions'], ['4xx-response', 'http_response'], 'UNCACHEABLE (request policy)', NULL);
    $this->assertSame([
      'errors' => [
        $missingPermissionError,
      ],
    ], $body);
    $body = $this->assertExpectedResponse('PATCH', $auto_save_url, [], 403, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'errors' => [
        $missingPermissionError,
      ],
    ], $body);

    // Assert auto-saving works for:
    // 1. The given *valid* entity values.
    $this->drupalLogin($this->httpApiUser);
    $this->performAutoSave($patch_update + $initial_entity, $updated_entity, $entity_type_id, $entity_id);
    $original_entity->updateFromClientSide($patch_update);
    $this->assertSingleConfigAutoSaveList($original_entity, $this->httpApiUser);
    // 2. The given *valid* entity values, with a garbage key-value pair added.
    $this->performAutoSave($patch_update + $initial_entity + ['new_key' => 'new_value'], $updated_entity, $entity_type_id, $entity_id);
    $this->assertSingleConfigAutoSaveList($original_entity, $this->httpApiUser);
    // 3. For just a patch update (missing other values).
    $this->performAutoSave($patch_update, $updated_entity, $entity_type_id, $entity_id);
    $this->assertSingleConfigAutoSaveList($original_entity, $this->httpApiUser);
    // 4. For missing values + garbage.
    $this->performAutoSave($patch_update + ['any_key' => ['any' => 'value']], $updated_entity, $entity_type_id, $entity_id);
    $this->assertSingleConfigAutoSaveList($original_entity, $this->httpApiUser);

    $this->assertSame($original_entity_array, $storage->loadUnchanged($entity_id)?->toArray(), 'The original entity was not changed by the auto-save.');
  }

}
