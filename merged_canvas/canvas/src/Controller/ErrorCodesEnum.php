<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

enum ErrorCodesEnum: int {

  case UnexpectedItemInPublishRequest = 1;
  case UnmatchedItemInPublishRequest = 2;
  case GlobalAssetNotPublished = 3;

  public function getMessage(): string {
    return match($this) {
      self::UnexpectedItemInPublishRequest =>
        'An unexpected item was found in the publish request. Please refresh your page and try again.',

      self::UnmatchedItemInPublishRequest =>
        'An item in the publish request did not match the expected format or value. Please refresh your page and try again.',
      self::GlobalAssetNotPublished =>
        'When publishing components you must also publish the Global CSS, please select it and retry.'
    };
  }

}
