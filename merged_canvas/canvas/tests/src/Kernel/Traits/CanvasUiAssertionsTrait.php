<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Traits;

use Drupal\canvas\CodeComponentDataProvider;

trait CanvasUiAssertionsTrait {

  /**
   * Asserts the UI mount element and settings for Drupal Canvas.
   */
  protected function assertCanvasMount(): void {
    self::assertArrayHasKey('canvas', $this->drupalSettings);
    self::assertEquals('canvas', $this->drupalSettings['canvas']['base']);

    // `drupalSettings.canvasData.v0` must be unconditionally present: in case the
    // user starts creating/editing code components.
    self::assertArrayHasKey(CodeComponentDataProvider::CANVAS_DATA_KEY, $this->drupalSettings);
    self::assertArrayHasKey(CodeComponentDataProvider::V0, $this->drupalSettings[CodeComponentDataProvider::CANVAS_DATA_KEY]);
    self::assertSame([
      'baseUrl',
      'branding',
      'breadcrumbs',
      'jsonapiSettings',
      'pageTitle',
    ], array_keys($this->drupalSettings[CodeComponentDataProvider::CANVAS_DATA_KEY][CodeComponentDataProvider::V0]));
    self::assertSame('This is a page title for testing purposes', $this->drupalSettings[CodeComponentDataProvider::CANVAS_DATA_KEY][CodeComponentDataProvider::V0]['pageTitle']);
  }

}
