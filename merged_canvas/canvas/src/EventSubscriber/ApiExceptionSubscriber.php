<?php

namespace Drupal\canvas\EventSubscriber;

use Drupal\canvas\Controller\ApiAutoSaveController;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\canvas\Utility\ExceptionHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Handle exceptions for Drupal Canvas API routes.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Handles exceptions and converts them to JSON responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event): void {
    // When param conversion fails, the more detailed exception is wrapped in
    // another.
    // @see \Drupal\Core\Routing\Enhancer\ParamConversionEnhancer::onException()
    $previous_exception = $event->getThrowable()->getPrevious();

    // Only handle Canvas API routes. Special care is needed for 404s caused by
    // requests to individual config entities that do not exist. This is not a
    // challenge in the generic (HTTP) exception handling because that
    // determined by the (wrapper) format, whereas Canvas API routes *always* return
    // a JSON response.
    // @see \Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase::onException()
    // @todo Consider adding a `_format` requirement to all Canvas API routes, that
    // might allow this to be simplified.
    $route_name = $this->routeMatch->getRouteName() ?? ($previous_exception instanceof ParamNotConvertedException ? $previous_exception->getRouteName() : NULL);
    if (str_starts_with($route_name ?? '', 'canvas.api.')) {
      $exception = $event->getThrowable();

      $status = match (TRUE) {
        $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
        $exception instanceof ConstraintViolationException => Response::HTTP_UNPROCESSABLE_ENTITY,
        default => Response::HTTP_INTERNAL_SERVER_ERROR,
      };

      // Client-side error responses.
      // @see https://jsonapi.org/format/#error-objects
      if ($status >= 400 && $status < 500) {
        $response = match (TRUE) {
          $exception instanceof ConstraintViolationException => [
            'errors' => array_map(
              fn($violation) => self::violationToJsonApiStyleErrorObject($violation),
              iterator_to_array($exception->getConstraintViolationList())
            ),
          ],
          // A 404 for a a route parameter that could not be upcasted is not
          // worth a verbose Symfony message: that's simply an entity that does
          // not exist.
          $exception instanceof NotFoundHttpException && $previous_exception instanceof ParamNotConvertedException => [],
          default => [
            'errors' => [
              0 => ExceptionHelper::getVerboseMessage($exception),
            ],
          ],
        };
      }
      // Server-side error responses.
      // @todo Make these also use JSON:API-style error responses?
      else {
        assert($status >= 500);
        $response = [
          'message' => ExceptionHelper::getVerboseMessage($exception),
        ];
      }

      // Generate a JSON response containing details when the status is 500, if
      // the current user has access to it.
      if ($status === 500) {
        // The stack trace may contain sensitive information. Only show it to
        // authorized users.
        // @see \Drupal\jsonapi\Normalizer\HttpExceptionNormalizer::buildErrorObjects()
        $is_verbose_reporting = $this->configFactory->get('system.logging')->get('error_level') === ERROR_REPORTING_DISPLAY_VERBOSE;
        $site_report_access = $this->currentUser->hasPermission('access site reports');
        if ($site_report_access && $is_verbose_reporting) {
          $response += [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace(),
          ];
        }
      }

      if ($exception instanceof CacheableDependencyInterface) {
        $event->setResponse(
          (new CacheableJsonResponse($response, $status))
            ->addCacheableDependency($exception)
        );
      }
      else {
        $event->setResponse(new JsonResponse($response, $status));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Lower than the priority of \Drupal\Core\EventSubscriber\ExceptionJsonSubscriber.
    $events[KernelEvents::EXCEPTION][] = ['onException', 50];
    return $events;
  }

  /**
   * Transforms a constraint violation to a JSON:API-style error object.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationInterface $violation
   *   A validation constraint violation.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   An associated entity if appropriate.
   *
   * @return array{'detail': string, 'source': array{'pointer': string}}
   *   A subset of a JSON:API error object.
   *
   * @see https://jsonapi.org/format/#error-objects
   * @see \Drupal\jsonapi\Normalizer\UnprocessableHttpEntityExceptionNormalizer
   */
  public static function violationToJsonApiStyleErrorObject(
    ConstraintViolationInterface $violation,
    ?EntityInterface $entity = NULL,
  ): array {
    $meta = [];
    if ($entity !== NULL) {
      $meta = [
        'meta' => \array_filter([
          'entity_type' => $entity->getEntityTypeId(),
          'entity_id' => $entity->id(),
          'label' => $entity->label(),
          ApiAutoSaveController::AUTO_SAVE_KEY => AutoSaveManager::getAutoSaveKey($entity),
        ]),
      ];
    }
    return [
      'detail' => (string) $violation->getMessage(),
      'source' => [
        // @todo Correctly convert to a JSON pointer.
        'pointer' => $violation->getPropertyPath(),
      ],
    ] + $meta;
  }

}
