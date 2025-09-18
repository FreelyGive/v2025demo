<?php

declare(strict_types=1);

namespace Drupal\canvas_test_sdc\Extension;

use Twig\Error\Error;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Defines a twig function that throws Exception when passed TRUE.
 */
final class SdcTestExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('throw_exception', [
        $this,
        'throwException',
      ]),
    ];
  }

  /**
   * Used in testing exception handling in SDC twig templates.
   *
   * @param bool $crash
   *
   * @return void
   *
   * @throws \Twig\Error\Error
   */
  public function throwException(bool $crash = FALSE): void {
    if ($crash) {
      throw new Error('Intentional test exception.');
    }
  }

}
