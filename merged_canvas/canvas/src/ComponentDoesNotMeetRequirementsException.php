<?php

declare(strict_types=1);

namespace Drupal\canvas;

/**
 * Defines an exception for when a component doesn't meet requirements.
 */
final class ComponentDoesNotMeetRequirementsException extends \Exception {

  public function __construct(
    protected readonly array $messages,
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct(\implode("\n", $this->messages), $code, $previous);
  }

  public function getMessages(): array {
    return $this->messages;
  }

}
