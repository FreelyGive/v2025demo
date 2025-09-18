<?php

declare(strict_types=1);

namespace Drupal\canvas;

class MissingComponentInputsException extends \OutOfRangeException {

  public function __construct(
    public readonly string $componentInstanceUuid,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct(
      message: sprintf('No props sources stored for %s. Caused by either incorrect logic or `inputs` being out of sync with `tree`.', $this->componentInstanceUuid),
      previous: $previous
    );
  }

}
