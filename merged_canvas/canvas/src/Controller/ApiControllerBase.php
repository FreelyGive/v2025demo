<?php

namespace Drupal\canvas\Controller;

use Drupal\canvas\Entity\EntityConstraintViolationList;
use Drupal\canvas\EventSubscriber\ApiExceptionSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @internal This HTTP API is intended only for the Canvas UI. These controllers
 *   and associated routes may change at any time.
 */
class ApiControllerBase {

  /**
   * Decodes a request whose body contains JSON.
   *
   * @return array
   *   The parsed JSON from the request body.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown if the request body cannot be decoded, or when no request body was
   *   provided with a POST or PATCH request.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   Thrown if the request body cannot be denormalized.
   *
   * @todo Introduce a custom Content-Type and validate that request header too, see \Drupal\jsonapi\JsonapiServiceProvider for inspiration.
   */
  protected static function decode(Request $request): array {
    $body = (string) $request->getContent();

    if (empty($body)) {
      throw new BadRequestHttpException('Empty request body.');
    }

    $data = json_decode($body, TRUE);
    if ($data === NULL) {
      throw new UnprocessableEntityHttpException('Request body contains invalid JSON.');
    }

    return $data;
  }

  /**
   * Creates a JSON:API-style error response from a set of entity violations.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface ...$violationSets
   *   The violations sets.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|null
   *   A JSON:API-style error response, with a top-level `errors` member that
   *   contains an array of `error` objects.
   *
   * @see https://jsonapi.org/format/#document-top-level
   * @see https://jsonapi.org/format/#error-objects
   */
  protected static function createJsonResponseFromViolationSets(ConstraintViolationListInterface ...$violationSets): ?JsonResponse {
    $violationSets = \array_filter($violationSets, static fn (ConstraintViolationListInterface $violationList): bool => $violationList->count() > 0);
    if (\count($violationSets) === 0) {
      return NULL;
    }

    return new JsonResponse(status: 422, data: [
      'errors' => \array_reduce($violationSets, static fn(array $carry, ConstraintViolationListInterface $violationList): array => [
        ...$carry,
        ...\array_map(static fn(ConstraintViolationInterface $violation) => ApiExceptionSubscriber::violationToJsonApiStyleErrorObject(
          $violation,
          $violationList instanceof EntityConstraintViolationList ? $violationList->entity : NULL,
        ), \iterator_to_array($violationList)),
      ], []),
    ]);
  }

}
