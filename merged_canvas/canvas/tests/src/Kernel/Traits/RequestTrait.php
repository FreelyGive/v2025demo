<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

trait RequestTrait {

  /**
   * Passes a request to the HTTP kernel and returns a response.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Exception
   */
  protected function request(Request $request): Response {
    // Reset the request stack.
    // \Drupal\KernelTests\KernelTestBase::bootKernel() pushes a bogus request
    // to boot the kernel, but it is also needed for any URL generation in tests
    // to work. We also need to reset the request stack every time we make a
    // request.
    $request_stack = $this->container->get('request_stack');
    while ($request_stack->getCurrentRequest() !== NULL) {
      $request_stack->pop();
    }

    $http_kernel = $this->container->get('http_kernel');
    self::assertInstanceOf(HttpKernelInterface::class, $http_kernel);
    $response = $http_kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);
    $content = $response->getContent();
    self::assertNotFalse($content);
    $this->setRawContent($content);

    self::assertInstanceOf(TerminableInterface::class, $http_kernel);
    $http_kernel->terminate($request, $response);

    return $response;
  }

  protected static function decodeResponse(Response $response): array {
    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertIsString($response->getContent());
    self::assertJson($response->getContent());
    return \json_decode($response->getContent(), TRUE);
  }

}
