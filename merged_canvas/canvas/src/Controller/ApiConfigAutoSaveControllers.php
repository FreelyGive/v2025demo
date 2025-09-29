<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\CanvasAssetInterface;
use Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ApiConfigAutoSaveControllers extends ApiControllerBase {

  use AutoSaveValidateTrait;

  public function __construct(
    private readonly AutoSaveManager $autoSaveManager,
  ) {}

  public function get(CanvasHttpApiEligibleConfigEntityInterface $canvas_config_entity): CacheableJsonResponse {
    $auto_save = $this->autoSaveManager->getAutoSaveEntity($canvas_config_entity);
    \assert($auto_save->entity === NULL || $auto_save->entity instanceof CanvasHttpApiEligibleConfigEntityInterface);

    $auto_save_normalization = $auto_save->entity?->normalizeForClientSide()->values;
    // When normalizing for auto-save, don't provide links to entity
    // operations. Those should only provided on this config entity's
    // canonical API route.
    if ($auto_save_normalization !== NULL) {
      unset($auto_save_normalization['links']);
    }

    return (new CacheableJsonResponse(
      data: [
        'data' => $auto_save_normalization,
        'autoSaves' => $this->getAutoSaveHashes([$canvas_config_entity]),
      ],
      status: Response::HTTP_OK,
    ))->addCacheableDependency($auto_save)
      // The `autoSaveStartingPoint` value in `autoSaves` is computed using the
      // config entity.
      ->addCacheableDependency($canvas_config_entity);

  }

  public function getCss(CanvasAssetInterface $canvas_config_entity): Response {
    $auto_save = $this->autoSaveManager->getAutoSaveEntity($canvas_config_entity);
    if (!$auto_save->isEmpty()) {
      \assert($auto_save->entity instanceof CanvasAssetInterface);
      $canvas_config_entity = $auto_save->entity;
    }
    $response = new Response($canvas_config_entity->getCss(), Response::HTTP_OK, [
      'Content-Type' => 'text/css; charset=utf-8',
    ]);
    $response->setPrivate();
    $response->headers->addCacheControlDirective('no-store');

    return $response;
  }

  public function getJs(CanvasAssetInterface $canvas_config_entity): Response {
    $auto_save = $this->autoSaveManager->getAutoSaveEntity($canvas_config_entity);
    if (!$auto_save->isEmpty()) {
      \assert($auto_save->entity instanceof CanvasAssetInterface);
      $canvas_config_entity = $auto_save->entity;
    }
    $response = new Response($canvas_config_entity->getJs(), Response::HTTP_OK, [
      'Content-Type' => 'text/javascript; charset=utf-8',
    ]);
    $response->setPrivate();
    $response->headers->addCacheControlDirective('no-store');

    return $response;
  }

  public function patch(Request $request, CanvasHttpApiEligibleConfigEntityInterface $canvas_config_entity): JsonResponse {
    $decoded = self::decode($request);
    if (!\array_key_exists('data', $decoded)) {
      throw new BadRequestHttpException('Missing data');
    }
    if (!\array_key_exists('autoSaves', $decoded)) {
      throw new BadRequestHttpException('Missing autoSaves');
    }
    if (!\array_key_exists('clientInstanceId', $decoded)) {
      throw new BadRequestHttpException('Missing clientInstanceId');
    }
    $this->validateAutoSaves([$canvas_config_entity], $decoded['autoSaves'], $decoded['clientInstanceId']);

    $auto_save_entity = $canvas_config_entity::create($canvas_config_entity->toArray());
    $auto_save_entity->updateFromClientSide($decoded['data']);
    $this->autoSaveManager->saveEntity($auto_save_entity, $decoded['clientInstanceId']);
    return new JsonResponse(data: ['autoSaves' => $this->getAutoSaveHashes([$canvas_config_entity])], status: Response::HTTP_OK);
  }

}
