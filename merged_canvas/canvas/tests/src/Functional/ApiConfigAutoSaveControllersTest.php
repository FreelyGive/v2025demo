<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\CanvasAssetInterface;
use Drupal\Tests\canvas\Traits\AutoSaveManagerTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\user\UserInterface;
use GuzzleHttp\RequestOptions;

/**
 * Tests the details of auto-saving config entities, NOT the "live" version.
 *
 * @covers \Drupal\canvas\Controller\ApiConfigAutoSaveControllers
 * @group canvas
 */
final class ApiConfigAutoSaveControllersTest extends HttpApiTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use AutoSaveManagerTestTrait;

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
    $user = $this->createUser([
      Page::EDIT_PERMISSION,
      JavaScriptComponent::ADMIN_PERMISSION,
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
      JavaScriptComponent::ENTITY_TYPE_ID => [
        JavaScriptComponent::ENTITY_TYPE_ID,
        [
          'machineName' => 'test',
          'name' => 'Test',
          'status' => FALSE,
          'required' => [
            'string',
            'integer',
          ],
          'props' => [
            'string' => [
              'title' => 'Title',
              'type' => 'string',
              'examples' => ['Press', 'Submit now'],
            ],
            'boolean' => [
              'title' => 'Truth',
              'type' => 'boolean',
              'examples' => [TRUE, FALSE],
            ],
            'integer' => [
              'title' => 'Integer',
              'type' => 'integer',
              'examples' => [23, 10, 2024],
            ],
            'number' => [
              'title' => 'Number',
              'type' => 'number',
              'examples' => [3.14],
            ],
          ],
          'slots' => [],
          'sourceCodeJs' => 'console.log("Test")',
          'sourceCodeCss' => '.test { display: none; }',
          'compiledJs' => 'console.log("Test")',
          'compiledCss' => '.test{display:none;}',
          'importedJsComponents' => [],
          'dataDependencies' => [],
        ],
        [
          'sourceCodeCss' => '.test { display: none; }/**/',
          'compiledCss' => '.test{display:none;}/**/',
        ],
        [
          'machineName' => 'test',
          'name' => 'Test',
          'status' => FALSE,
          'props' => [
            'string' => [
              'title' => 'Title',
              'type' => 'string',
              'examples' => ['Press', 'Submit now'],
            ],
            'boolean' => [
              'title' => 'Truth',
              'type' => 'boolean',
              'examples' => [TRUE, FALSE],
            ],
            'integer' => [
              'title' => 'Integer',
              'type' => 'integer',
              'examples' => [23, 10, 2024],
            ],
            'number' => [
              'title' => 'Number',
              'type' => 'number',
              'examples' => [3.14],
            ],
          ],
          'required' => [
            'string',
            'integer',
          ],
          'slots' => [],
          'sourceCodeJs' => 'console.log("Test")',
          'sourceCodeCss' => '.test { display: none; }/**/',
          'compiledJs' => 'console.log("Test")',
          'compiledCss' => '.test{display:none;}/**/',
          'dataDependencies' => [],
        ],
        ['compiledJs'],
        ['compiledCss'],
        "The 'administer code components' permission is required.",
      ],
      AssetLibrary::ENTITY_TYPE_ID => [
        AssetLibrary::ENTITY_TYPE_ID,
        [
          'id' => 'global',
          'label' => 'I am an asset!',
          'css' => [
            'compiled' => '.test{display:none;}',
            'original' => '.test { display: none; }',
          ],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
        ],
        [
          'css' => [
            'original' => '.test { display: none; }/**/',
            'compiled' => '.test{display:none;}/**/',
          ],
        ],
        [
          'id' => 'global',
          'label' => 'I am an asset!',
          'css' => [
            'original' => '.test { display: none; }/**/',
            'compiled' => '.test{display:none;}/**/',
          ],
          'js' => [
            'original' => 'console.log("Test")',
            'compiled' => 'console.log("Test")',
          ],
        ],
        ['js', 'compiled'],
        ['css', 'compiled'],
        "The 'administer code components' permission is required.",
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
    array $compiled_js_path_in_normalization,
    array $compiled_css_path_in_normalization,
    string $missingPermissionError,
  ): void {
    if ($entity_type_id === AssetLibrary::ENTITY_TYPE_ID) {
      // Delete the library created during install.
      $library = AssetLibrary::load(AssetLibrary::GLOBAL_ID);
      \assert($library instanceof AssetLibrary);
      $library->delete();
    }
    $entity_type_manager = $this->container->get(EntityTypeManagerInterface::class);
    $storage = $entity_type_manager->getStorage($entity_type_id);
    $definition = $entity_type_manager->getDefinition($entity_type_id);
    $id_key = $definition->getKey('id');
    assert(!empty($initial_entity[$id_key]));
    $entity_id = $initial_entity[$id_key];
    $base = rtrim(base_path(), '/');
    $post_url = Url::fromUri("base:/canvas/api/v0/config/$entity_type_id");
    $auto_save_url = Url::fromUri("base:/canvas/api/v0/config/auto-save/$entity_type_id/$entity_id");
    $js_auto_save_url = Url::fromRoute('canvas.api.config.auto-save.get.js', [
      'canvas_config_entity_type_id' => $entity_type_id,
      'canvas_config_entity' => $entity_id,
    ]);
    $css_auto_save_url = Url::fromRoute('canvas.api.config.auto-save.get.css', [
      'canvas_config_entity_type_id' => $entity_type_id,
      'canvas_config_entity' => $entity_id,
    ]);

    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];

    $this->drupalLogin($this->httpApiUser);

    // GETting the auto-save state for a config entity when that entity does not yet exist: 404.
    $auto_save_data = $this->assertExpectedResponse('GET', $auto_save_url, $request_options, 404, NULL, NULL, 'UNCACHEABLE (request policy)', 'UNCACHEABLE (no cacheability)');
    $this->assertSame([], $auto_save_data);

    // CSS and JS draft endpoints should be 404 as well.
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
    \assert($original_entity instanceof CanvasAssetInterface);
    $original_entity_array = $original_entity->toArray();
    assert(is_array($original_entity_array));

    // Now the entity exists, these should serve a 200 response containing the
    // non-draft CSS/JS, and NOT redirect to the non-draft. Otherwise, a race
    // condition occurs when a user loads a preview with draft code components,
    // and at the same time another uses publishes the draft.
    // @see https://www.drupal.org/project/canvas/issues/3508922#comment-16003426
    $response = $this->drupalGet($js_auto_save_url);
    $this->assertSession()->statusCodeEquals(200);
    self::assertSame(NestedArray::getValue($initial_entity, $compiled_js_path_in_normalization), $response);
    $response = $this->drupalGet($css_auto_save_url);
    $this->assertSession()->statusCodeEquals(200);
    self::assertSame(NestedArray::getValue($initial_entity, $compiled_css_path_in_normalization), $response);

    // Anonymously: 401.
    $this->drupalLogout();
    $body = $this->assertExpectedResponse('GET', $auto_save_url, [], 401, ['user.roles:anonymous'], ['4xx-response', 'config:system.site', 'config:user.role.anonymous', 'http_response'], 'MISS', NULL);
    $this->assertSame([
      'errors' => [
        'You must be logged in to access this resource.',
      ],
    ], $body);

    // Limited Permissions: 403.
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

    // Assert that draft endpoints can be fetched.
    // Draft CSS/JS should not be available to unauthenticated users.
    $this->drupalLogout();
    $this->drupalGet($js_auto_save_url);
    $this->assertSession()->statusCodeEquals(401);
    $this->drupalGet($css_auto_save_url);
    $this->assertSession()->statusCodeEquals(401);

    // Draft CSS/JS should not be available to unprivileged users.
    $this->drupalLogin($this->limitedPermissionsUser);
    $this->drupalGet($js_auto_save_url);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($css_auto_save_url);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->httpApiUser);
    $this->drupalGet($js_auto_save_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/javascript; charset=utf-8');
    self::assertEquals(NestedArray::getValue($updated_entity, $compiled_js_path_in_normalization), $this->getSession()->getPage()->getContent());

    $this->drupalGet($css_auto_save_url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/css; charset=utf-8');
    self::assertEquals(NestedArray::getValue($updated_entity, $compiled_css_path_in_normalization), $this->getSession()->getPage()->getContent());

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
