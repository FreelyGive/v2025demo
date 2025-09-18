<?php

declare(strict_types=1);

namespace Drupal\canvas\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\canvas\Utility\ExceptionHelper;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Event subscriber base class for validating Drupal Canvas API messages.
 *
 * This functionality only takes effect in the presence of the
 * league/openapi-psr7-validator Composer library with PHP assertions enabled
 * for local development or CI purposes.
 *
 * @see self::isValidationEnabled()
 *
 * @internal
 */
abstract class ApiMessageValidatorBase implements EventSubscriberInterface {

  /**
   * The OpenAPI validator builder.
   *
   * This property will only be set if the validator library is available.
   * Don't access it directly. Use {@see self::getConfiguredValidatorBuilder}
   * instead.
   */
  private ?ValidatorBuilder $validatorBuilder = NULL;

  public function __construct(
    private readonly LoggerInterface $logger,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly RouteMatchInterface $currentRouteMatch,
    protected readonly HttpMessageFactoryInterface $httpMessageFactory,
    private readonly string $appRoot,
  ) {}

  /**
   * Sets the OpenAPI validator builder service if available.
   */
  public function setValidatorBuilder(?ValidatorBuilder $validatorBuilder = NULL): void {
    if ($validatorBuilder instanceof ValidatorBuilder) {
      $this->validatorBuilder = $validatorBuilder;
    }
    elseif (class_exists(ValidatorBuilder::class)) {
      $this->validatorBuilder = new ValidatorBuilder();
    }
  }

  /**
   * Validates Drupal Canvas API messages.
   *
   * @throws \League\OpenAPIValidation\PSR7\Exception\ValidationFailed
   *    See docblock on {@see self::validate()}.
   */
  public function onMessage(RequestEvent|ResponseEvent $event): void {
    if (!$this->shouldValidate($event->getRequest())) {
      return;
    }

    try {
      $validatorBuilder = $this->getConfiguredValidatorBuilder();
      $this->validate($validatorBuilder, $event);
    }
    catch (NoPath $e) {
      // @todo Temporarily log and ignore missing paths. Once 'openapi.yml' is
      //   is complete, remove this to treat them as failures.
      $this->logger->debug($e->getMessage());
    }
    catch (ValidationFailed $e) {
      $this->logFailure($e);
      // @todo Surface exception details better for front-end display.
      // @see https://www.drupal.org/project/canvas/issues/3470321
      throw $e;
    }
  }

  /**
   * Determines whether the message should be validated.
   */
  private function shouldValidate(Request $request): bool {
    return !$this->isProd()
      && $this->isCanvasMessage()
      && $this->isValidationEnabled()
      && !$request->headers->has('X-NO-OPENAPI-VALIDATION');
  }

  /**
   * Determines whether the application is in production.
   */
  private function isProd(): bool {
    $is_prod = TRUE;

    // Assertions are assumed to be disabled in prod, so this assignment will
    // never take place there.
    // @phpstan-ignore-next-line booleanNot.alwaysTrue, function.alreadyNarrowedType
    assert(!($is_prod = FALSE));

    return $is_prod;
  }

  /**
   * Determines whether the message is from this module.
   */
  private function isCanvasMessage(): bool {
    return str_starts_with(
      $this->currentRouteMatch->getRouteName() ?? '',
      'canvas.api.',
    );
  }

  /**
   * Determines whether validation is enabled.
   *
   * Validation is implicitly enabled if the league/openapi-psr7-validator
   * Composer library is present. To add it to your project, require it as a dev
   * dependency:
   *
   * ```
   * composer require --dev league/openapi-psr7-validator
   * ```
   */
  public function isValidationEnabled(): bool {
    // The builder won't be set if league/openapi-psr7-validator is absent.
    /* @see self::setValidatorBuilder() */
    return $this->validatorBuilder instanceof ValidatorBuilder;
  }

  /**
   * Validates the message.
   *
   * @throws \League\OpenAPIValidation\PSR7\Exception\ValidationFailed
   *   If validation fails.
   */
  abstract protected function validate(
    ValidatorBuilder $validatorBuilder,
    RequestEvent|ResponseEvent $event,
  ): void;

  /**
   * Gets the validator builder configured with the module's OpenAPI schema.
   */
  private function getConfiguredValidatorBuilder(): ValidatorBuilder {
    $openapi_spec_file = sprintf(
      '%s/%s/openapi.yml',
      $this->appRoot,
      $this->moduleHandler
        ->getModule('canvas')
        ->getPath(),
    );

    assert($this->validatorBuilder instanceof ValidatorBuilder);

    return $this->validatorBuilder
      ->fromYamlFile($openapi_spec_file);
  }

  /**
   * Logs a validation failure.
   */
  protected function logFailure(ValidationFailed $e): void {
    $this->logger->debug(
      ExceptionHelper::getVerboseMessage($e)
    );
  }

}
