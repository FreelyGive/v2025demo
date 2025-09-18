<?php

declare(strict_types=1);

namespace Drupal\canvas\EventSubscriber;

use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber that validates an Drupal Canvas API response.
 *
 * @internal
 */
final class ApiResponseValidator extends ApiMessageValidatorBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => 'onMessage'];
  }

  /**
   * {@inheritdoc}
   */
  protected function validate(
    ValidatorBuilder $validatorBuilder,
    RequestEvent|ResponseEvent $event,
  ): void {
    assert($event instanceof ResponseEvent);
    $request = $event->getRequest();
    $response = $event->getResponse();
    if (!$response instanceof JsonResponse) {
      return;
    }

    $validator = $validatorBuilder->getResponseValidator();

    $operation = new OperationAddress(
      $request->getPathInfo(),
      strtolower($request->getMethod()),
    );

    $psr7_response = $this->httpMessageFactory
      ->createResponse($response);

    $validator->validate($operation, $psr7_response);
  }

}
