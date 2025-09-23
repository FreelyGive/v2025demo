<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\canvas\Controller\ApiAutoSaveController;
use Drupal\canvas\Entity\PageRegion;
use Drupal\file\Entity\File;
use Drupal\image\ImageStyleInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

trait AutoSaveManagerTestTrait {

  use UserCreationTrait;

  protected static function generateAutoSaveHash(array $data): string {
    // Use reflection access private \Drupal\canvas\AutoSave\AutoSaveManager::generateHash
    $autoSaveManager = new \ReflectionClass('Drupal\canvas\AutoSave\AutoSaveManager');
    $generateHash = $autoSaveManager->getMethod('generateHash');
    $generateHash->setAccessible(TRUE);
    $hash = $generateHash->invokeArgs(NULL, [$data]);
    self::assertIsString($hash);
    return $hash;
  }

  /**
   * @todo Move this logic elsewhere in https://www.drupal.org/project/canvas/issues/3535458
   */
  protected function getClientAutoSaves(array $entities, bool $addRegions = TRUE): array {
    $autoSaves = [];
    $autoSaveManager = \Drupal::service(AutoSaveManager::class);
    assert($autoSaveManager instanceof AutoSaveManager);
    if ($addRegions) {
      $entities += PageRegion::loadForActiveTheme();
    }
    foreach ($entities as $entity) {
      assert($entity instanceof EntityInterface);
      $autoSaves[AutoSaveManager::getAutoSaveKey($entity)] = $autoSaveManager->getClientAutoSaveData($entity);
    }
    return ['autoSaves' => $autoSaves];
  }

  /**
   * Adds a user with picture field and sets as current.
   *
   * @return array
   *   The user, and the picture image style url.
   */
  protected function setUserWithPictureField(array $permissions): array {
    $fileUri = 'public://image-2.jpg';
    \Drupal::service(FileSystemInterface::class)->copy(\Drupal::root() . '/core/tests/fixtures/files/image-2.jpg', PublicStream::basePath(), FileExists::Replace);
    $picture = File::create([
      'uri' => $fileUri,
      'status' => TRUE,
    ]);
    $imageStyle = \Drupal::entityTypeManager()->getStorage('image_style')->load(ApiAutoSaveController::AVATAR_IMAGE_STYLE);
    self::assertInstanceOf(ImageStyleInterface::class, $imageStyle);
    $avatarUrl = $imageStyle->buildUrl($fileUri);

    $account1 = $this->createUser($permissions, values: ['user_picture' => $picture]);
    self::assertInstanceOf(AccountInterface::class, $account1);
    $this->setCurrentUser($account1);

    return [$account1, $avatarUrl];
  }

}
