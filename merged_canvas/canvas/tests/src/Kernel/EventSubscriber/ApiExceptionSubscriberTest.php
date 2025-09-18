<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\EventSubscriber;

use Drupal\Core\Routing\RouteMatch;
use Drupal\KernelTests\KernelTestBase;
use Drupal\canvas\EventSubscriber\ApiExceptionSubscriber;
use Drupal\Tests\canvas\Doubles\TestVerboseException;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

/**
 * @group canvas
 */
class ApiExceptionSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'system',
    'user',
  ];

  /**
   * Tests the response for an HTTP 500 error.
   *
   * @dataProvider providerTest500Response
   */
  public function test500Response(string $exception_class, array $exception_arguments, string $expected_message): void {
    $sut = new ApiExceptionSubscriber(
      new RouteMatch('canvas.api.test', new Route('/test-path')),
      \Drupal::service('config.factory'),
      User::create(),
    );

    $http_kernel = $this->container->get('http_kernel');
    /** @var \Throwable $exception */
    $exception = new $exception_class(...$exception_arguments);
    $event = new ExceptionEvent(
      $http_kernel,
      new Request(),
      HttpKernelInterface::MAIN_REQUEST,
      $exception,
    );

    // Exercise the SUT.
    $sut->onException($event);

    // Assert on the response.
    $response = $event->getResponse();
    assert($response instanceof Response);
    $content = $response->getContent();
    assert(is_string($content));
    $content = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertEquals(500, $response->getStatusCode(), 'Response status code is 500.');
    self::assertArrayHasKey('message', $content, 'Response contains correct message.');
    self::assertSame($expected_message, $content['message'], 'Response message is correct.');
  }

  public static function providerTest500Response(): array {
    return [
      [
        'exception_class' => \Exception::class,
        'exception_arguments' => ['Basic message.'],
        'expected_message' => 'Basic message.',
      ],
      [
        'exception_class' => TestVerboseException::class,
        'exception_arguments' => ['Basic message.', 'Verbose message.'],
        'expected_message' => 'Verbose message.',
      ],
    ];
  }

}
