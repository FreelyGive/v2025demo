<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;

final class AutoSaveEntity implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  public function __construct(public readonly ?EntityInterface $entity, public readonly ?string $hash, public readonly ?string $clientId) {
    $this->cacheTags = [AutoSaveManager::CACHE_TAG];
  }

  public static function empty(): AutoSaveEntity {
    return new self(NULL, NULL, NULL);
  }

  public function isEmpty(): bool {
    return $this->entity === NULL;
  }

}
