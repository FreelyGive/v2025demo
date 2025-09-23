<?php

declare(strict_types=1);

namespace Drupal\canvas\Utility;

/**
 * Provides helper methods for handling exceptions.
 *
 * @internal
 */
final class ExceptionHelper {

  /**
   * Gets the verbose message if available, otherwise the standard message.
   *
   * Some exception classes provide additional helpful details. {@see
   * https://github.com/thephpleague/openapi-psr7-validator/pull/184}
   *
   * @param \Throwable $exception
   *   The exception to get the message from.
   *
   * @return string
   *   The verbose message if available, otherwise the standard message.
   */
  public static function getVerboseMessage(\Throwable $exception): string {
    if (method_exists($exception, 'getVerboseMessage')) {
      return $exception->getVerboseMessage();
    }
    return $exception->getMessage();
  }

}
