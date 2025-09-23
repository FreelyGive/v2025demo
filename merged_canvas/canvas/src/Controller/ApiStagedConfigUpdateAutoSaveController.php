<?php

namespace Drupal\canvas\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\StagedConfigUpdate;
use Drupal\canvas\Exception\ConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ApiStagedConfigUpdateAutoSaveController extends ApiControllerBase {

  public function __construct(
    private readonly AutoSaveManager $autoSaveManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function post(Request $request): JsonResponse {
    $decoded = self::decode($request);
    if (!\array_key_exists('data', $decoded)) {
      throw new BadRequestHttpException('Missing data');
    }

    $canvas_config_entity = StagedConfigUpdate::createFromClientSide($decoded['data']);

    // Make sure the user can update the entity before saving it to verify the
    // user would also be able to edit it later. When creating a new entity,
    // there is no context to the data being provided.
    $update_access = $this->entityTypeManager
      ->getAccessControlHandler(StagedConfigUpdate::ENTITY_TYPE_ID)
      ->access($canvas_config_entity, 'update', return_as_object: TRUE);
    if (!$update_access->isAllowed()) {
      throw new AccessDeniedHttpException('Access denied to create entity of type ' . StagedConfigUpdate::ENTITY_TYPE_ID);
    }

    // Validate the entity before saving it.
    $violations = $canvas_config_entity->getTypedData()->validate();
    if ($violations->count()) {
      throw new ConstraintViolationException($violations);
    }

    $this->autoSaveManager->saveEntity($canvas_config_entity);
    return new JsonResponse(status: Response::HTTP_CREATED);
  }

}
