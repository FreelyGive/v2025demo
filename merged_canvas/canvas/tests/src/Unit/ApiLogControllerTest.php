<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\HttpFoundation\Request;
use Drupal\canvas\Controller\ApiLogController;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * @coversDefaultClass \Drupal\canvas\Controller\ApiLogController
 * @group canvas
 */
class ApiLogControllerTest extends TestCase {

  private const LOG_LEVEL_MAP = [
    'emergency' => RfcLogLevel::EMERGENCY,
    'alert' => RfcLogLevel::ALERT,
    'critical' => RfcLogLevel::CRITICAL,
    'error' => RfcLogLevel::ERROR,
    'warning' => RfcLogLevel::WARNING,
    'notice' => RfcLogLevel::NOTICE,
    'info' => RfcLogLevel::INFO,
    'debug' => RfcLogLevel::DEBUG,
  ];

  public static function providerApiLogController(): array {
    return [
      'INVALID: missing message' => [
        ['level' => 'error'],
        400,
        'Message is required',
      ],
      'INVALID: missing level' => [
        ['message' => 'Test error message'],
        400,
        'Log level is required',
      ],
      'INVALID: non-existent level' => [
        ['message' => 'Test error message', 'level' => 'invalid_log_level'],
        400,
        '',
      ],
      'VALID' => [
        ['message' => 'Test error message', 'level' => 'error'],
        200,
        'Error logged successfully',
      ],
    ];
  }

  /**
   * @dataProvider providerApiLogController
   */
  public function testApiLogController(array $payload, int $expectedStatus, string $expectedMessage): void {
    $logger = $this->getLogger();
    $controller = $this->getController($logger);
    $request = new Request([], [], [], [], [], [], json_encode($payload) ?: '');
    $response = $controller->__invoke($request);

    $this->assertSame($expectedStatus, $response->getStatusCode());
    if ($expectedMessage) {
      $this->assertStringContainsString($expectedMessage, (string) $response->getContent());
    }

    $logs = $logger->cleanLogs();
    if ($expectedStatus === 200) {
      $this->assertNotEmpty($logs);
      $this->assertLogEntry($logs[0], $payload['level'], $payload['message']);
    }
    else {
      $this->assertEmpty($logs);
    }
  }

  /**
   * Asserts that a log entry matches the expected level and message.
   */
  private function assertLogEntry(array $log, string $expectedLevel, string $expectedMessage): void {
    $this->assertEquals(self::LOG_LEVEL_MAP[$expectedLevel], $log[0], 'Log level does not match.');
    $this->assertStringContainsString($expectedMessage, $log[1], 'Log message does not match.');
  }

  /**
   * Initializes a LogController with a BufferingLogger.
   */
  private function getController(BufferingLogger $logger): ApiLogController {
    return new ApiLogController($logger);
  }

  /**
   * Returns a new instance of BufferingLogger.
   */
  private function getLogger(): BufferingLogger {
    return new BufferingLogger();
  }

}
