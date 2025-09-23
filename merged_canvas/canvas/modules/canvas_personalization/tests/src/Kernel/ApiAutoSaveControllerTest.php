<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Kernel;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Controller\ApiAutoSaveController;
use Drupal\canvas\Controller\ErrorCodesEnum;
use Drupal\canvas\Entity\Page;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\Kernel\Traits\VfsPublicStreamUrlTrait;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;
use Drupal\Tests\canvas\Traits\AutoSaveManagerTestTrait;
use Drupal\Tests\canvas\Traits\AutoSaveRequestTestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\OpenApiSpecTrait;
use Drupal\Tests\canvas\Traits\CanvasFieldTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\canvas_personalization\Entity\Segment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @see \Drupal\Tests\canvas\Kernel\ApiAutoSaveControllerTest
 * @group canvas
 * @group canvas_personalization
 */
final class ApiAutoSaveControllerTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use AutoSaveManagerTestTrait;
  use AutoSaveRequestTestTrait;
  use UserCreationTrait;
  use OpenApiSpecTrait;
  use RequestTrait;
  use CanvasFieldTrait;
  use VfsPublicStreamUrlTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'test_user_config',
    'canvas_personalization',
    // @todo Remove once ComponentSourceInterface is a public API, i.e. after https://www.drupal.org/i/3520484#stable is done.
    'canvas_dev_mode',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('system');
    (new CanvasTestSetup())->setup();
  }

  public function testApiAutoSaveControllerGet(): void {
    $this->installConfig(['test_user_config']);
    $permissions = [
      Page::EDIT_PERMISSION,
      // We need access to segments even for seeing there are changes.
      Segment::ADMIN_PERMISSION,
    ];

    /** @var \Drupal\canvas\AutoSave\AutoSaveManager $autoSave */
    $autoSave = $this->container->get(AutoSaveManager::class);

    [$account, $avatarUrl] = $this->setUserWithPictureField($permissions);

    $segment_id = $this->getRandomGenerator()->machineName();
    $data = [
      'label' => 'A rule',
      'description' => 'A rule description',
      'id' => $segment_id,
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
    ];
    $segment = Segment::create($data);
    $segment->save();

    $new_title = $this->getRandomGenerator()->sentences(3);
    $new_description = $this->getRandomGenerator()->sentences(10);
    $data['label'] = $new_title;
    $data['description'] = $new_description;
    $data['rules'] = [
      'utm_parameters' => [
        'id' => 'utm_parameters',
        'negate' => FALSE,
        'all' => TRUE,
        'parameters' => [
          [
            "key" => "utm_campaign",
            "value" => "Christmas",
            "matching" => "exact",
          ],
        ],
      ],
    ];
    $segment->updateFromClientSide($data);
    $autoSave->saveEntity($segment);

    $request = Request::create(Url::fromRoute('canvas.api.auto-save.get')->toString());
    $response = $this->request($request);
    self::assertInstanceOf(CacheableJsonResponse::class, $response);
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    self::assertContains(AutoSaveManager::CACHE_TAG, $response->getCacheableMetadata()->getCacheTags());
    self::assertCount(0, \array_diff($account->getCacheTags(), $response->getCacheableMetadata()->getCacheTags()));
    self::assertCount(0, \array_diff($account->getCacheContexts(), $response->getCacheableMetadata()->getCacheContexts()));
    self::assertContains('config:user.settings', $response->getCacheableMetadata()->getCacheTags());
    $content = \json_decode($response->getContent() ?: '{}', TRUE);
    $segmentIdentifier = \sprintf('segment:%s', $segment->id());
    self::assertEquals([
      $segmentIdentifier,
    ], \array_keys($content));
    // We don't assert the exact value of these because of clock-drift during
    // the test, asserting their presence is enough.
    \assert(\is_array($content[$segmentIdentifier]));

    // Assert the content is marked as updated.
    self::assertArrayHasKey('updated', $content[$segmentIdentifier]);
    // And that a hash exists.
    self::assertArrayHasKey('data_hash', $content[$segmentIdentifier]);

    self::assertEquals([
      'langcode' => 'en',
      'entity_type' => $segment->getEntityTypeId(),
      'entity_id' => $segment->id(),
      'owner' => [
        'id' => $account->id(),
        'name' => $account->getDisplayName(),
        'avatar' => $avatarUrl,
        'uri' => $account->toUrl()->toString(),
      ],
      'label' => $new_title,
    ], \array_diff_key($content[$segmentIdentifier], \array_flip(['updated', 'data_hash'])));

    $this->assertDataCompliesWithApiSpecification($content, 'AutoSaveCollection');
  }

  /**
   * @testWith [false, "The 'publish auto-saves' permission is required."]
   *           [true, null]
   */
  public function testPost(bool $authorized, ?string $expected_403_message): void {
    $this->setUpImages();
    $entity_type_manager = $this->container->get('entity_type.manager');
    $segment_storage = $entity_type_manager->getStorage(Segment::ENTITY_TYPE_ID);
    /** @var \Drupal\canvas\AutoSave\AutoSaveManager $autoSave */
    $autoSave = \Drupal::service(AutoSaveManager::class);
    $permissions = [
      'edit any article content',
      Segment::ADMIN_PERMISSION,
      Page::EDIT_PERMISSION,
    ];
    if ($authorized) {
      $permissions[] = AutoSaveManager::PUBLISH_PERMISSION;
    }
    $this->setUpCurrentUser(permissions: $permissions);
    if ($expected_403_message) {
      $this->expectException(AccessDeniedHttpException::class);
      $this->expectExceptionMessage($expected_403_message);
    }
    $this->assertNoAutoSaveData();
    $segment_id = 'new_segment';
    $data = [
      'label' => 'New segment',
      'description' => 'New segment description',
      'id' => $segment_id,
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
    ];
    $segment = Segment::create($data);
    $this->assertSame(SAVED_NEW, $segment->save());

    $new_title = $this->getRandomGenerator()->sentences(3);
    $new_description = $this->getRandomGenerator()->sentences(10);

    $missing_auto_save_data = $data;
    $missing_auto_save_data['rules'] = [
      'utm_parameters' => [
        'id' => 'utm_parameters',
        // Missing 'all' and 'negate'.
        'parameters' => [
          [
            "key" => "utm_campaign",
            "value" => "Christmas",
            "matching" => "exact",
          ],
        ],
      ],
    ];
    $segment->updateFromClientSide($missing_auto_save_data);
    $autoSave->saveEntity($segment);

    $response = $this->makePublishAllRequest();
    $json = json_decode($response->getContent() ?: '', TRUE);
    self::assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    $errors[] = [
      'detail' => "'negate' is a required key because rules.%key is utm_parameters (see config schema type condition.plugin.utm_parameters).",
      'source' => [
        'pointer' => 'rules.utm_parameters',
      ],
      'meta' => [
        'entity_type' => Segment::ENTITY_TYPE_ID,
        'entity_id' => $segment->id(),
        // The label should not be updated if model validation failed.
        'label' => $segment->label(),
        ApiAutoSaveController::AUTO_SAVE_KEY => $autoSave->getAutoSaveKey($segment),
      ],
    ];
    $errors[] = [
      'detail' => "'all' is a required key because rules.%key is utm_parameters (see config schema type condition.plugin.utm_parameters).",
      'source' => [
        'pointer' => 'rules.utm_parameters',
      ],
      'meta' => [
        'entity_type' => Segment::ENTITY_TYPE_ID,
        'entity_id' => $segment->id(),
        // The label should not be updated if model validation failed.
        'label' => $segment->label(),
        ApiAutoSaveController::AUTO_SAVE_KEY => $autoSave->getAutoSaveKey($segment),
      ],
    ];

    self::assertEquals($errors, $json['errors']);
    $stored_segment = $segment_storage->loadUnchanged($segment_id);
    assert($stored_segment instanceof Segment);
    // Ensure the entity is not updated if is invalid.
    $this->assertEquals('New segment', $stored_segment->label());

    // Fix the errors.
    $updated_segment_data = $segment->normalizeForClientSide()->values;
    $updated_segment_data['label'] = $new_title;
    $updated_segment_data['description'] = $new_description;
    $updated_segment_data['rules'] = $data['rules'];
    $segment->updateFromClientSide($updated_segment_data);
    $autoSave->saveEntity($segment);

    $auto_save_data = $this->getAutoSaveStatesFromServer();
    $segment_auto_save_key = 'segment:' . $segment_id;
    self::assertArrayHasKey($segment_auto_save_key, $auto_save_data);

    // Make publish requests that have extra, and out-dated auto-save
    // information.
    $extra_auto_save_data = $auto_save_data;
    $extra_key = 'segment:missing_segment';
    $extra_auto_save_data[$extra_key] = $auto_save_data[$segment_auto_save_key];
    $response = $this->makePublishAllRequest($extra_auto_save_data);
    self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    self::assertEquals([
      'errors' => [
      [
        'detail' => ErrorCodesEnum::UnexpectedItemInPublishRequest->getMessage(),
        'source' => [
          'pointer' => $extra_key,
        ],
        'code' => ErrorCodesEnum::UnexpectedItemInPublishRequest->value,
      ],
      ],
    ], \json_decode($response->getContent() ?: '', TRUE, flags: JSON_THROW_ON_ERROR));

    $out_dated_auto_save_data = $auto_save_data;
    $out_dated_auto_save_data[$segment_auto_save_key]['data_hash'] = 'old-hash';
    $response = $this->makePublishAllRequest($out_dated_auto_save_data);
    self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    self::assertEquals([
      'errors' => [
        [
          'detail' => ErrorCodesEnum::UnmatchedItemInPublishRequest->getMessage(),
          'source' => [
            'pointer' => $segment_auto_save_key,
          ],
          'code' => ErrorCodesEnum::UnmatchedItemInPublishRequest->value,
          'meta' => [
            'entity_type' => 'segment',
            'entity_id' => $segment->id(),
            'label' => $new_title,
            ApiAutoSaveController::AUTO_SAVE_KEY => $autoSave->getAutoSaveKey($segment),
          ],
        ],
      ],
    ], \json_decode($response->getContent() ?: '', TRUE, flags: JSON_THROW_ON_ERROR));

    $response = $this->makePublishAllRequest();
    $json = json_decode($response->getContent() ?: '', TRUE);
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    self::assertEquals(['message' => 'Successfully published 1 item.'], $json);

    $stored = $segment_storage->loadUnchanged($segment_id);
    assert($stored instanceof Segment);
    $this->assertSame($new_title, $stored->label());
    $this->assertSame($new_description, $stored->get('description'));
    $this->assertSame($data['rules'], $stored->get('rules'));

    // Ensure that after the segment have been published their auto-save data is
    // removed.
    $this->assertNoAutoSaveData();
  }

}
