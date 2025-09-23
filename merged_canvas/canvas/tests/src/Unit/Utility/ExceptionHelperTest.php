<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit\Utility;

use Drupal\canvas\Utility\ExceptionHelper;
use Drupal\Tests\canvas\Doubles\TestVerboseException;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\canvas\Utility\ExceptionHelper
 * @group canvas
 */
class ExceptionHelperTest extends UnitTestCase {

  /**
   * @covers ::getVerboseMessage
   *
   * @dataProvider exceptionProvider
   */
  public function testGetVerboseMessage(\Throwable $exception, string $expected_message): void {
    $result = ExceptionHelper::getVerboseMessage($exception);
    $this->assertEquals($expected_message, $result);
  }

  public static function exceptionProvider(): array {
    return [
      [
        'exception' => new \Exception('Basic message'),
        'expected_message' => 'Basic message',
      ],
      [
        'exception' => new TestVerboseException('Basic message', 'Verbose message'),
        'expected_message' => 'Verbose message',
      ],
    ];
  }

}
