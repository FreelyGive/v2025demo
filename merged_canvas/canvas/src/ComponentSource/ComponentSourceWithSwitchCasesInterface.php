<?php

declare(strict_types=1);

namespace Drupal\canvas\ComponentSource;

/**
 * Defines an interface for component sources that support switch-cases.
 *
 *  ⚠️ This is highly experimental and *will* be refactored or even removed.
 *
 * @see https://www.drupal.org/i/3525746#comment-16121437
 */
interface ComponentSourceWithSwitchCasesInterface extends ComponentSourceInterface {

  public const string SWITCH = 'switch';

  public const string CASE = 'case';

  public function isCase(): bool;

  public function isSwitch(): bool;

  public function isNegotiatedCase(array $inputs): bool;

}

