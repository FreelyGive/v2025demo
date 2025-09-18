<?php

declare(strict_types=1);

namespace Drupal\canvas;

/**
 * Defines an exception for an invalid request body.
 */
final class InvalidRequestBodyValue extends \Exception {

  public function __construct(
    string $message,
    public readonly ?string $propertyPath = NULL,
  ) {
    parent::__construct($message);
  }

}
