<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\ClientSideRepresentation;
use Drupal\canvas\EntityHandlers\AssetLibraryAccessControlHandler;
use Drupal\canvas\EntityHandlers\CanvasAssetStorage;

#[ConfigEntityType(
  id: self::ENTITY_TYPE_ID,
  label: new TranslatableMarkup('In-browser code library'),
  label_singular: new TranslatableMarkup('in-browser code library'),
  label_plural: new TranslatableMarkup('in-browser code libraries'),
  label_collection: new TranslatableMarkup('In-browser code libraries'),
  admin_permission: JavaScriptComponent::ADMIN_PERMISSION,
  handlers: [
    'storage' => CanvasAssetStorage::class,
    'access' => AssetLibraryAccessControlHandler::class,
  ],
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  links: [],
  config_export: [
    'id',
    'label',
    'css',
    'js',
  ],
)]
final class AssetLibrary extends ConfigEntityBase implements CanvasAssetInterface {

  use CanvasAssetLibraryTrait;

  public const string ENTITY_TYPE_ID = 'asset_library';
  private const string ASSETS_DIRECTORY = 'assets://canvas/';

  public const string GLOBAL_ID = 'global';

  protected string $id;

  /**
   * The human-readable label of the asset library.
   */
  protected ?string $label;

  /**
   * {@inheritdoc}
   *
   * This corresponds to `AssetLibrary` in openapi.yml.
   *
   * @see docs/adr/0005-Keep-the-front-end-simple.md
   */
  public function normalizeForClientSide(): ClientSideRepresentation {
    return ClientSideRepresentation::create(
      values: [
        'id' => $this->id,
        'label' => $this->label,
        'css' => $this->css,
        'js' => $this->js,
      ],
      preview: NULL
    );
  }

  /**
   * {@inheritdoc}
   *
   * This corresponds to `AssetLibrary` in openapi.yml.
   *
   * @see docs/adr/0005-Keep-the-front-end-simple.md
   */
  public static function createFromClientSide(array $data): static {
    $entity = static::create(['id' => $data['id']]);
    $entity->updateFromClientSide($data);
    return $entity;
  }

  /**
   * {@inheritdoc}
   *
   * This corresponds to `AssetLibrary` in openapi.yml.
   *
   * @see docs/adr/0005-Keep-the-front-end-simple.md
   */
  public function updateFromClientSide(array $data): void {
    foreach ($data as $key => $value) {
      $this->set($key, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function refineListQuery(QueryInterface &$query, RefinableCacheableDependencyInterface $cacheability): void {
    // Nothing to do.
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);
    // The files generated in CanvasAssetStorage::doSave() have a content-dependent
    // hash in their name. This has 2 consequences:
    // 1. Cached responses that referred to an older version, continue to work.
    // 2. New responses must use the newly generated files, which requires the
    //    asset library to point to those new files. Hence the library info must
    //    be recalculated.
    // @see \canvas_library_info_build()
    Cache::invalidateTags(['library_info']);
  }

  /**
   * {@inheritdoc}
   */
  public function getAssetLibrary(bool $isPreview): string {
    // Inside the Canvas UI, always load the draft even if there isn't one. Let the
    // controller logic automatically serve the non-draft assets when a draft
    // disappears. This is necessary to allow for asset library dependencies,
    // and avoids race conditions.
    // @see \Drupal\canvas\Controller\ApiConfigAutoSaveControllers::getCss()
    // @see \Drupal\canvas\Controller\ApiConfigAutoSaveControllers::getJs()
    return 'canvas/asset_library.' . $this->id() . ($isPreview ? '.draft' : '');
  }

  /**
   * {@inheritdoc}
   */
  public function getAssetLibraryDependencies(): array {
    return [];
  }

}
