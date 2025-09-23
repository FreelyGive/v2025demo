<?php

declare(strict_types=1);

namespace Drupal\canvas\EventSubscriber;

use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Request subscriber that validates an Drupal Canvas API request.
 *
 * @internal
 */
final class ApiRequestValidator extends ApiMessageValidatorBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => 'onMessage'];
  }

  /**
   * {@inheritdoc}
   */
  protected function validate(
    ValidatorBuilder $validatorBuilder,
    RequestEvent|ResponseEvent $event,
  ): void {
    assert($event instanceof RequestEvent);
    $validator = $validatorBuilder->getRequestValidator();

    $psr7_request = $this->httpMessageFactory
      ->createRequest($event->getRequest());

    // Normalize the path.
    $uri = $psr7_request->getUri();
    $path = substr_replace($uri->getPath(), '/', 0, strlen(base_path()));
    $psr7_request = $psr7_request->withUri($uri->withPath($path));

    $validator->validate($psr7_request);
  }

}
