<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\AutoSave;

use Drupal\Core\Entity\EntityInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;
use Drupal\Tests\canvas\Traits\AutoSaveRequestTestTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait AutoSaveConflictTestTrait {

  use AutoSaveRequestTestTrait;

  protected EntityInterface $entity;

  abstract protected static function getPermissions(): array;

  abstract protected function setUpEntity(): void;

  abstract protected function modifyJsonToSendAsAutoSave(array &$json, string $text): void;

  abstract protected function assertCurrentAutoSaveText(string $text): void;

  abstract protected function getUpdateAutoSaveRequest(array $json): Request;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['system', 'block', 'user', 'dynamic_page_cache']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'stark')->save();

    (new CanvasTestSetup())->setup();

    $this->setUpEntity();
    $this->setUpCurrentUser(permissions: self::getPermissions());
  }

  protected function getAutoSaveManager(): AutoSaveManager {
    $autoSaveManager = $this->container->get(AutoSaveManager::class);
    assert($autoSaveManager instanceof AutoSaveManager);
    return $autoSaveManager;
  }

  /**
   * 💡 Debug tip: put a breakpoint in the 409-throwing ::validateAutoSaves().
   */
  public function testOutdatedAutoSave(): void {
    $autoSaveKey = AutoSaveManager::getAutoSaveKey($this->entity);
    $url = $this->getAutoSaveUrl();

    $response = $this->request(Request::create($url));
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $getJson = self::decodeResponse($response);
    // Even without an auto-save entry, the `autoSaves` value will not be empty.
    // We always need the `autoSaveStartingPoint` set to determine if the client data
    // is outdated because the `hash` being empty only indicates
    // that there was no auto-save entry at the time of the GET request. It does
    // not tell us if it was created from the current version of the entity. The
    // last request of the client could have been made a long time ago.
    $this->assertNull($getJson['autoSaves'][$autoSaveKey]['hash']);
    $this->assertNotEmpty($getJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint']);

    $originalGetJson = $getJson;
    $this->modifyJsonToSendAsAutoSave($getJson, 'Updated text');
    $originalClientId = 'known-client-id';
    $getJson['clientInstanceId'] = $originalClientId;

    // Success: auto-save using:
    // - ✅ *any* client ID
    // - ✅ initial `autoSaveStartingPoint`
    // - ✅ null `hash`
    $response = $this->request($this->getUpdateAutoSaveRequest($getJson));
    $this->assertCurrentAutoSaveText('Updated text');
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $postJson = self::decodeResponse($response);
    // The auto-save hash should be set after the first auto-save update but the
    // `autoSaveStartingPoint` should be the same as the one from the GET request.
    $this->assertNotNull($postJson['autoSaves'][$autoSaveKey]['hash']);
    $this->assertSame($getJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint'], $postJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint']);

    // Conflict: auto-save using:
    // - ⚠️ different client ID than last auto-save update.
    // - ✅ initial `autoSaveStartingPoint`
    // - ❌ null `hash` (MUST match the stored auto-save entry when client ID
    // does not match!)
    // (Rationale: subsequent auto-saves must update the existing one,
    // identified by the hash, unless we know it is from the same client.)
    $this->assertRequestAutoSaveConflict($this->getUpdateAutoSaveRequest($originalGetJson));
    $this->assertCurrentAutoSaveText('Updated text');

    // Success: auto-save using:
    // - ✅ same client ID as before
    // - ✅ initial `autoSaveStartingPoint`
    // - ✅ null `hash`
    // (Rationale: the same client must be trusted to send updates, because the
    // previous auto-save request might still be ongoing.)
    $originalGetJsonWithMatchingClientId = $originalGetJson;
    $originalGetJsonWithMatchingClientId['clientInstanceId'] = $originalClientId;
    $this->modifyJsonToSendAsAutoSave($originalGetJsonWithMatchingClientId, 'Updated text-finalV2');
    $response = $this->request($this->getUpdateAutoSaveRequest($originalGetJsonWithMatchingClientId));
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $this->assertCurrentAutoSaveText('Updated text-finalV2');

    // Success: auto-save using:
    // - ✅ same client ID as before
    // - ✅ initial `autoSaveStartingPoint`
    // - ✅ nonsensical `hash`
    // (Rationale: the same client must be trusted to send updates, because the
    // previous auto-save request might still be ongoing.)
    $originalGetJsonWithMatchingClientId['autoSaves'][$autoSaveKey]['hash'] = $this->randomString(8);
    $this->modifyJsonToSendAsAutoSave($originalGetJsonWithMatchingClientId, 'Updated text-finalV2a');
    $response = $this->request($this->getUpdateAutoSaveRequest($originalGetJsonWithMatchingClientId));
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $this->assertCurrentAutoSaveText('Updated text-finalV2a');

    $this->makePublishAllRequest();

    // After publishing the auto-save hash should be empty and the
    // `autoSaveStartingPoint` should be different.
    $response = $this->request(Request::create($url));
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $postPublishGetJson = self::decodeResponse($response);
    $this->assertNull($postPublishGetJson['autoSaves'][$autoSaveKey]['hash']);
    $this->assertNotEmpty($postPublishGetJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint']);
    $this->assertNotEquals($postPublishGetJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint'], $originalGetJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint']);

    // Conflict: auto-save using:
    // - ⚠️ no client ID
    // - ❌ initial `autoSaveStartingPoint` (non-matching!)
    // - ✅ null `hash`
    // (Rationale: auto-saves targeting a past entity revision make no sense.)
    $this->assertRequestAutoSaveConflict($this->getUpdateAutoSaveRequest($originalGetJson));

    // Conflict: auto-save using:
    // - ✅ matching client ID
    // - ❌ initial `autoSaveStartingPoint` (non-matching!)
    // - ✅ null `hash`
    // (Rationale: auto-saves targeting a past entity revision make no sense,
    // even if they come from the same client. The client must refresh to target
    // the entity that was updated after the auto-save publish.)
    self::assertTrue($this->getAutoSaveManager()->getAutoSaveEntity($this->entity)->isEmpty());
    $originalGetJsonWithMatchingClientId = $originalGetJson;
    $originalGetJsonWithMatchingClientId['clientInstanceId'] = $originalClientId;
    $this->modifyJsonToSendAsAutoSave($originalGetJsonWithMatchingClientId, 'Updated text-finalV3');
    $this->assertRequestAutoSaveConflict($this->getUpdateAutoSaveRequest($originalGetJsonWithMatchingClientId));

    // Success: auto-save using:
    // - ✅ matching client ID
    // - ✅ post-publish `autoSaveStartingPoint`
    // - ✅ null `hash`
    $postPublishGetJson['clientInstanceId'] = $originalClientId;
    $this->modifyJsonToSendAsAutoSave($postPublishGetJson, 'Updated text-finalV4');
    $response = $this->request($this->getUpdateAutoSaveRequest($postPublishGetJson));
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $this->assertCurrentAutoSaveText('Updated text-finalV4');

    // Conflict: auto-save using:
    // - ✅ matching client ID
    // - ❌ initial `autoSaveStartingPoint` (non-matching!)
    // - ✅ post-publish `hash`
    $autoSaveEntity = $this->getAutoSaveManager()->getAutoSaveEntity($this->entity);
    self::assertFalse($autoSaveEntity->isEmpty());
    self::assertSame($originalClientId, $autoSaveEntity->clientId);
    $originalGetJsonWithMatchingClientId['autoSaves'][$autoSaveKey]['hash'] = $autoSaveEntity->hash;
    $this->assertRequestAutoSaveConflict($this->getUpdateAutoSaveRequest($originalGetJsonWithMatchingClientId));
    $this->assertCurrentAutoSaveText('Updated text-finalV4');

    // Ensure that the `autoSaveStartingPoint` is updated *immediately* after
    // every entity save, not just after auto-saves.
    // @todo We should be able to assert here that there's an
    //   `X-Drupal-Dynamic-Cache: HIT` here, but we can't due to this being a
    //   kernel test, which makes CommandLineOrUnsafeMethod deny caching.
    //   Convert tests that use this trait to functional tests to be able to
    //   test this in https://drupal.org/i/3535315.
    $response = $this->request(Request::create($url));
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $preEntitySaveJson = self::decodeResponse($response);
    $this->assertNotNull($preEntitySaveJson['autoSaves'][$autoSaveKey]['hash']);
    $this->assertNotEmpty($preEntitySaveJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint']);

    $this->entity->save();

    // @todo We should be able to assert here that there's an
    //   `X-Drupal-Dynamic-Cache: MISS` here, but we can't due to this being a
    //   kernel test, which makes CommandLineOrUnsafeMethod deny caching.
    //   Convert tests that use this trait to functional tests to be able to
    //   test this in https://drupal.org/i/3535315.
    $response = $this->request(Request::create($url));
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $postEntitySaveJson = self::decodeResponse($response);
    $this->entity instanceof CanvasHttpApiEligibleConfigEntityInterface ?
        // For CanvasHttpApiEligibleConfigEntityInterface entities the auto-save
        // will be deleted after the entity save.
        // @see \Drupal\canvas\AutoSave\AutoSaveManager::onCanvasConfigEntitySave()
        $this->assertNull($postEntitySaveJson['autoSaves'][$autoSaveKey]['hash']) :
        $this->assertSame($preEntitySaveJson['autoSaves'][$autoSaveKey]['hash'], $postEntitySaveJson['autoSaves'][$autoSaveKey]['hash']);
    $this->assertNotEmpty($postEntitySaveJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint']);

    // The `autoSaveStartingPoint` should have changed.
    self::assertNotEquals($preEntitySaveJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint'], $postEntitySaveJson['autoSaves'][$autoSaveKey]['autoSaveStartingPoint']);
  }

  abstract protected function getAutoSaveUrl(): string;

}
