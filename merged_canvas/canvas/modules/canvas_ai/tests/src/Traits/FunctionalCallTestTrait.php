<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_ai\Traits;

use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;

trait FunctionalCallTestTrait {

  protected static function normalizeErrorString(string $error): string {
    return trim((string) preg_replace('/\s+/', ' ', $error));
  }

  private function getToolOutput(string $pluginId, array $contexts): string {
    $tool = $this->functionCallManager->createInstance($pluginId);
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);

    foreach ($contexts as $key => $context) {
      $tool->setContextValue($key, $context)->execute();
    }
    return $tool->getReadableOutput();
  }

}
