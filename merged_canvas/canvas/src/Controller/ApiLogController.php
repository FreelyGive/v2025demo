<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Core\Logger\RfcLogLevel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handles logging of error messages via an API endpoint.
 *
 * Accepts a JSON payload with a 'message' and 'level', validates the input, and logs the message using Drupal's logging system.
 */
final class ApiLogController {

  public function __construct(
    #[Autowire(service: 'logger.channel.canvas')]
    private readonly LoggerInterface $logger,
  ) {}

  public function __invoke(Request $request): JsonResponse {
    $content = json_decode($request->getContent(), TRUE);
    $error_message = $content['message'] ?? NULL;
    $error_level = strtolower($content['level'] ?? '');

    $allowed_levels = [
      LogLevel::EMERGENCY => RfcLogLevel::EMERGENCY,
      LogLevel::ALERT => RfcLogLevel::ALERT,
      LogLevel::CRITICAL => RfcLogLevel::CRITICAL,
      LogLevel::ERROR => RfcLogLevel::ERROR,
      LogLevel::WARNING => RfcLogLevel::WARNING,
      LogLevel::NOTICE => RfcLogLevel::NOTICE,
      LogLevel::INFO => RfcLogLevel::INFO,
      LogLevel::DEBUG => RfcLogLevel::DEBUG,
    ];

    // Validate request.
    if (empty($error_message)) {
      return new JsonResponse(['error' => 'Message is required'], 400);
    }
    if (empty($error_level)) {
      return new JsonResponse(['error' => 'Log level is required'], 400);
    }
    if (!array_key_exists($error_level, $allowed_levels)) {
      return new JsonResponse(['error' => 'Invalid log level'], 400);
    }

    $rfc_level = $allowed_levels[$error_level];

    $this->logger->log($rfc_level, $error_message);
    return new JsonResponse(['status' => 'Error logged successfully']);
  }

}
