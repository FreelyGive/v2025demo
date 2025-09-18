<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Doubles;

/**
 * A test exception that includes a verbose message.
 */
class TestVerboseException extends \Exception {

  public function __construct(
    string $message,
    private readonly string $verboseMessage,
  ) {
    parent::__construct($message);
  }

  public function getVerboseMessage(): string {
    return $this->verboseMessage;
  }

}
