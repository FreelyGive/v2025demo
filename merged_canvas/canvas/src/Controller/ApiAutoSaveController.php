<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\AutoSavePublishAwareInterface;
use Drupal\canvas\Entity\EntityConstraintViolationList;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Validation\ConstraintPropertyPathTranslatorTrait;
use Drupal\image\Entity\ImageStyle;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Handles retrieval and publication of auto-saved changes.
 */
final class ApiAutoSaveController extends ApiControllerBase {

  use ConstraintPropertyPathTranslatorTrait;

  public const AUTO_SAVE_KEY = 'api_auto_save_key';
  public const AVATAR_IMAGE_STYLE = 'canvas_avatar';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly AutoSaveManager $autoSaveManager,
    #[Autowire(service: 'logger.channel.canvas')]
    private readonly LoggerInterface $logger,
    private readonly AccountInterface $currentUser,
  ) {}

  private static function validateExpectedAutoSaves(array $expected_auto_saves, array $all_auto_saves): ?JsonResponse {
    $unexpected_keys = \array_diff_key($expected_auto_saves, $all_auto_saves);
    if ($unexpected_keys) {
      $errors = [];
      foreach (\array_keys($unexpected_keys) as $key) {
        $errors[] = [
          'detail' => ErrorCodesEnum::UnexpectedItemInPublishRequest->getMessage(),
          'source' => [
            'pointer' => $key,
          ],
          'code' => ErrorCodesEnum::UnexpectedItemInPublishRequest->value,
        ];
      }
      return new JsonResponse(data: ['errors' => $errors], status: Response::HTTP_CONFLICT);
    }
    // Check the data hashes.
    $unmatched_keys = \array_values(\array_filter(\array_keys($expected_auto_saves), function ($key) use ($expected_auto_saves, $all_auto_saves) {
      return !\hash_equals($expected_auto_saves[$key]['data_hash'], $all_auto_saves[$key]['data_hash']);
    }));
    if ($unmatched_keys) {
      return new JsonResponse(data: [
        'errors' => \array_map(static fn(string $key) => [
          'detail' => ErrorCodesEnum::UnmatchedItemInPublishRequest->getMessage(),
          'source' => [
            'pointer' => $key,
          ],
          'code' => ErrorCodesEnum::UnmatchedItemInPublishRequest->value,
          'meta' => \array_intersect_key($all_auto_saves[$key], \array_flip([
            'entity_type',
            'entity_id',
            'label',
          ])) + [
            self::AUTO_SAVE_KEY => $key,
          ],
        ], $unmatched_keys),
      ], status: Response::HTTP_CONFLICT);
    }
    // If any JavaScriptComponents are being published ensure the global
    // AssetLibrary is also being published.
    // @todo Improve this in https://www.drupal.org/project/canvas/issues/3535038
    $global_asset = AssetLibrary::load(AssetLibrary::GLOBAL_ID);
    if ($global_asset !== NULL) {
      $global_asset_key = AutoSaveManager::getAutoSaveKey($global_asset);
      if (\array_key_exists($global_asset_key, $all_auto_saves) && !\array_key_exists($global_asset_key, $expected_auto_saves)) {
        // There are changes to the global asset library, but it is not being
        // published. We need to ensure there are not code components being
        // published.
        foreach ($expected_auto_saves as $client_auto_save) {
          if ($client_auto_save['entity_type'] === JavaScriptComponent::ENTITY_TYPE_ID) {
            return new JsonResponse(data: [
              'errors' => [
                [
                  'detail' => ErrorCodesEnum::GlobalAssetNotPublished->getMessage(),
                  'source' => [
                    'pointer' => $global_asset_key,
                  ],
                  'code' => ErrorCodesEnum::GlobalAssetNotPublished->value,
                  'meta' => \array_intersect_key($all_auto_saves[$global_asset_key], \array_flip([
                    'entity_type',
                    'entity_id',
                    'label',
                  ])) + [
                    self::AUTO_SAVE_KEY => $global_asset_key,
                  ],
                ],
              ],
            ], status: Response::HTTP_FAILED_DEPENDENCY);
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Gets the auto-saved changes.
   */
  public function get(): CacheableJsonResponse {
    $cache = new CacheableMetadata();

    // Filter those the user has access to.
    $filtered = \array_filter($this->autoSaveManager->getAllAutoSaveList(TRUE), function (array $item) use ($cache) {
      assert($item['entity'] instanceof EntityInterface);
      $access = $item['entity']->access('view label', return_as_object: TRUE);
      // @todo This will result in the cache tag for this entity being returned
      //   in the response even though the user does not have access to view the
      //   entity. A less privileged user could still be able to determine that
      //   the entity exists and has pending changes. Determine if we should
      //   prevent this in https://drupal.org/i/3535355.
      $cache->addCacheableDependency($item['entity']);
      $cache->addCacheableDependency($access);
      return $access->isAllowed();
    });

    $userIds = \array_column($filtered, 'owner');
    /** @var \Drupal\user\UserInterface[] $users */
    $users = $this->entityTypeManager->getStorage('user')->loadMultiple($userIds);
    foreach ($users as $uid => $user) {
      $access = $user->access('view label', return_as_object: TRUE);
      $cache->addCacheableDependency($user);
      $cache->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        unset($users[$uid]);
      }
    }
    // User display names depend on configuration.
    $cache->addCacheableDependency($this->configFactory->get('user.settings'));

    // Remove 'data', 'client_id', 'entity' keys because this will reduce the amount of
    // data sent to the client and back to the server. Also, 'client_id' is only
    // used to determine if the client has the latest changes when editing an
    // entity in Drupal Canvas and not needed for the publishing process.
    $filtered = \array_map(fn(array $item) => \array_diff_key($item, \array_flip(['data', 'client_id', 'entity'])), $filtered);

    $withUserDetails = \array_map(fn(array $item) => [
      // @phpstan-ignore-next-line
      'owner' => \array_key_exists($item['owner'], $users) ? [
        'name' => $users[$item['owner']]->getDisplayName(),
        'avatar' => $this->buildAvatarUrl($users[$item['owner']]),
        'uri' => $users[$item['owner']]->toUrl()->toString(),
        'id' => $item['owner'],
      ] : [
        'name' => new TranslatableMarkup('User @uid', ['@uid' => $item['owner']]),
        'avatar' => NULL,
        'uri' => NULL,
        'id' => $item['owner'],
      ],
    ] + $item, $filtered);
    return (new CacheableJsonResponse($withUserDetails))->addCacheableDependency($cache->addCacheTags([AutoSaveManager::CACHE_TAG]));
  }

  /**
   * Publishes the auto-saved changes.
   *
   * @throws \Exception
   */
  public function post(Request $request): JsonResponse {
    $client_auto_saves = \json_decode($request->getContent(), TRUE);
    \assert(\is_array($client_auto_saves));
    $all_auto_saves = $this->autoSaveManager->getAllAutoSaveList(TRUE);
    if ($validation_response = self::validateExpectedAutoSaves($client_auto_saves, $all_auto_saves)) {
      return $validation_response;
    }

    if (\count($all_auto_saves) === 0) {
      return new JsonResponse(data: ['message' => 'No items to publish.'], status: Response::HTTP_NO_CONTENT);
    }

    // We keep these in an array instead of making use of a collection like
    // ConstraintViolationList, so we can keep violations grouped by each entity.
    $violationSets = [];
    $entities = [];
    // The client auto-saves do not contain the 'data' key, so we need to use
    // the versions from the auto-save manager.
    $publish_auto_saves = array_intersect_key($all_auto_saves, $client_auto_saves);

    // We want to report all access errors at one, so keeping the labels.
    $access_error_labels = [];
    $access_error_cache = new CacheableMetadata();
    $loadedEntities = [];
    foreach ($publish_auto_saves as $autoSaveKey => ['entity' => $entity]) {
      assert($entity instanceof EntityInterface);
      // Auto-saves always are updates to existing entities. This just used
      // EntityStorageInterface::create() to construct an entity object from
      // just its values, which for some entities would result in it being
      // considered new, when it is not. Ensure it is never considered new.
      // @see \Drupal\Core\Entity\EntityBase::isNew()
      // @see \Drupal\Core\Config\Entity\ConfigEntityBase::isNew()
      $entity->enforceIsNew(FALSE);
      $loadedEntities[$autoSaveKey] = $entity;

      $access = $entity->access(operation: 'update', return_as_object: TRUE);
      if (!$access->isAllowed()) {
        $access_error_cache->addCacheableDependency($entity);
        $access_error_cache->addCacheableDependency($access);
        $access_error_cache->addCacheTags([AutoSaveManager::CACHE_TAG]);
        $access_error_labels[] = $entity->label();
      }
    }
    if (!empty($access_error_labels)) {
      throw new CacheableAccessDeniedHttpException($access_error_cache, sprintf('Unable to update entities: %s.', implode(', ', array_map(fn(\Stringable|string|NULL $label) => $label ? "'$label'" : "''", $access_error_labels))));
    }

    foreach ($loadedEntities as $entity) {
      if ($entity instanceof ConfigEntityInterface) {
        $violations = $entity->getTypedData()->validate();
        if ($violations->count() > 0) {
          $violationSets[] = new EntityConstraintViolationList($entity, $violations);
          continue;
        }
        if ($entity instanceof AutoSavePublishAwareInterface) {
          $entity->autoSavePublish();
        }
      }
      else {
        assert($entity instanceof ContentEntityInterface);

        $fields = $entity->getFieldDefinitions();
        $entity_definition = $entity->getEntityType();
        assert($entity_definition instanceof ContentEntityTypeInterface);
        assert(!is_null($entity->id()));
        $original_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
        assert($original_entity instanceof FieldableEntityInterface);
        foreach ($fields as $field_name => $field) {
          $field_access = $entity->get($field_name)->access(operation: 'edit', return_as_object: TRUE);
          $original_field = $original_entity->get($field_name);

          // We ignore those fields that didn't change. We also need to ignore
          // field access for computed fields, because there
          // is nothing to set, and some fields that will always deny access.
          // We are protected because the entity validation will trigger errors
          // if those were changed in an unexpected way.
          // Status and published will be TRUE when publishing.
          $ignore_field = $field->isComputed() || $original_field->equals($entity->get($field_name));
          $keys = ['id', 'revision_id', 'uuid', 'langcode', 'status', 'published'];
          $revision_keys = ['revision_created', 'revision_user'];
          foreach ($keys as $key) {
            $ignore_field |= $field_name === $entity_definition->getKey($key);
          }
          foreach ($revision_keys as $revision_key) {
            $ignore_field |= $field_name === $entity_definition->getRevisionMetadataKey($revision_key);
          }
          if (!$ignore_field && $field_access->isForbidden()) {
            throw new CacheableAccessDeniedHttpException(
              (new CacheableMetadata())->addCacheableDependency($field_access),
              sprintf('Unable to update field %s for entity "%s".', $field_name, $entity->label()),
            );
          }
        }
        $use_existing_revision_id = AutoSaveManager::entityIsConsideredNew($entity);

        if ($entity instanceof EntityPublishedInterface) {
          $entity->setPublished();
        }
        // If the entity is new, the autosaved data is considered to be part
        // of the first revision. Therefore, do not create a new revision
        // for new entities.
        if ($use_existing_revision_id) {
          $entity->setNewRevision(FALSE);
        }
        else {
          // Reset the revision ID.
          $entity->setNewRevision();
          $revision_id_key = $entity_definition->getKey('revision');
          \assert(\is_string($revision_id_key));
          $entity->set($revision_id_key, NULL);
        }
        // Always set the revision user to the current user. Even though we
        // might not be creating a new revision, this would only be in the case
        // where this entity should be considered new, which means it has never
        // published before in Drupal Canvas.
        // @see \Drupal\canvas\AutoSave\AutoSaveManager::contentEntityIsConsideredNew()
        if ($revision_user = $entity_definition->getRevisionMetadataKey('revision_user')) {
          assert(is_string($revision_user));
          $entity->set($revision_user, $this->currentUser->id());
        }
        // Even though we will validate each entity individually before it is
        // saved to ensure the data is still valid after other entities have been
        // saved, we should still validate here before we save any entities to
        // avoid saving any entities if any are invalid. This is to avoid, when
        // possible, any side effects of saving entities that cannot be undone
        // by rolling back the database transaction, such as sending emails.
        $violations = $entity->validate();
        $form_violations = $this->autoSaveManager->getEntityFormViolations($entity);
        foreach ($form_violations as $form_violation) {
          // Add any form violations at this point.
          // @todo Remove this in https://drupal.org/i/3505018
          $violations->add($form_violation);
        }
        if ($violations->count() > 0) {
          $violationSets[] = self::getViolationSetsFromPropertyPathsAndRoot($entity, $violations);
          continue;
        }
      }
      $entity->enforceIsNew(FALSE);
      $entities[] = $entity;
    }
    if ($validation_errors_response = self::createJsonResponseFromViolationSets(...$violationSets)) {
      return $validation_errors_response;
    }

    // Either everything must be published, or nothing at all.
    $lastEntityEvaluated = NULL;
    try {
      $transaction = $this->database->startTransaction();
      foreach ($entities as $entity) {
        $lastEntityEvaluated = $entity;
        // Even though the entities are being validated before, there is a
        // possibility where, when multiple entities are being saved together,
        // the first entity collides with some of the following entities. So
        // we need to validate right before saving the entity.
        self::ensureEntityIsValid($entity);
        $entity->save();
      }
      foreach ($entities as $entity) {
        $this->autoSaveManager->delete($entity);
      }
    }
    catch (ConstraintViolationException $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $violationList = $e->getConstraintViolationList();
      \assert(count($violationList) > 0);
      $violationList = self::getViolationSetsFromPropertyPathsAndRoot($lastEntityEvaluated, $violationList);
      $violationsResponse = self::createJsonResponseFromViolationSets($violationList);
      assert($violationsResponse instanceof JsonResponse);
      return $violationsResponse;
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      Error::logException($this->logger, $e);
      return new JsonResponse(data: [
        'errors' => [
          [
            'detail' => $e->getMessage(),
            'source' => [
              'pointer' => 'error',
            ],
            'meta' => [
              'entity_type' => $lastEntityEvaluated->getEntityTypeId(),
              'entity_id' => $lastEntityEvaluated->id(),
              'label' => $lastEntityEvaluated->label(),
              self::AUTO_SAVE_KEY => AutoSaveManager::getAutoSaveKey($lastEntityEvaluated),
            ],
          ],
        ],
      ], status: 500);
    }

    return new JsonResponse(data: ['message' => new PluralTranslatableMarkup(\count($publish_auto_saves), 'Successfully published 1 item.', 'Successfully published @count items.')], status: 200);
  }

  public function delete(EntityInterface $entity): JsonResponse {
    if ($this->autoSaveManager->getAutoSaveEntity($entity)->isEmpty()) {
      return new JsonResponse(data: ['error' => 'No auto-save data found for this entity.'], status: Response::HTTP_NOT_FOUND);
    }
    $this->autoSaveManager->delete($entity);
    return new JsonResponse(data: ['message' => 'Auto-save data deleted successfully.'], status: Response::HTTP_NO_CONTENT);
  }

  /**
   * Gets URL to avatar.
   *
   * @param \Drupal\user\UserInterface $owner
   *
   * @return string|null
   */
  private function buildAvatarUrl(UserInterface $owner): ?string {
    if (!$owner->hasField('user_picture') || $owner->get('user_picture')->isEmpty()) {
      return NULL;
    }
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $owner->get('user_picture')->entity;
    if ($file === NULL) {
      return NULL;
    }
    $uri = $file->getFileUri();
    if ($uri === NULL) {
      return NULL;
    }
    $imageStyle = $this->entityTypeManager->getStorage('image_style')->load(self::AVATAR_IMAGE_STYLE);
    if (!$imageStyle instanceof ImageStyle || !$imageStyle->supportsUri($uri)) {
      return $this->fileUrlGenerator->generateString($uri);
    }
    return $imageStyle->buildUrl($uri);
  }

  /**
   * Validate different types of entities and throw an exception if there are violations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to validate.
   *
   * @throws \Drupal\canvas\Exception\ConstraintViolationException
   */
  private static function ensureEntityIsValid(EntityInterface $entity): void {
    $violations = new ConstraintViolationList();
    if ($entity instanceof ConfigEntityInterface) {
      $violations->addAll($entity->getTypedData()->validate());
    }
    elseif ($entity instanceof ContentEntityInterface) {
      $violations->addAll($entity->validate());
    }
    if (count($violations) > 0) {
      throw new ConstraintViolationException($violations);
    }
  }

  public static function getViolationSetsFromPropertyPathsAndRoot(
    FieldableEntityInterface|ConfigEntityInterface $entity,
    ConstraintViolationListInterface|EntityConstraintViolationListInterface $violations,
  ): ConstraintViolationListInterface {
    // Config entities doesn't have fields.
    if ($entity instanceof ConfigEntityInterface) {
      return $violations;
    }
    // Violations for Canvas field inputs should show against the 'model'
    // property.
    $map = \array_reduce(
      \array_keys(
        \array_filter(
          $entity->getFields(),
          static fn(FieldItemListInterface $field
          ): bool => $field->getItemDefinition()->getClass(
            ) === ComponentTreeItem::class
        )
      ),
      // We need our map to have one entry for each delta in the field item
      // list.
      static fn(array $carry, string $field_name): array => [
        ...$carry,
        ...\array_combine(
          // Key the map by the field name for each delta.
          // e.g. field_canvas_demo.0.inputs
          \array_map(static fn (int|string $delta) => \sprintf('%s.%d.inputs', $field_name, (int) $delta), \array_keys($entity->get($field_name)->getValue())),
          // And map this to 'model'.
          \array_fill(0, $entity->get($field_name)->count(), 'model'),
        ),
      ],
      []
    );
    return self::translateConstraintPropertyPathsAndRoot(
      $map,
      ($violations instanceof EntityConstraintViolationListInterface) ? EntityConstraintViolationList::fromCoreConstraintViolationList($violations) : $violations,
    );
  }

}
