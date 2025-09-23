<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\ClientSideRepresentation;

#[ConfigEntityType(
  id: self::ENTITY_TYPE_ID,
  label: new TranslatableMarkup('Folder'),
  label_singular: new TranslatableMarkup('folder'),
  label_plural: new TranslatableMarkup('folders'),
  label_collection: new TranslatableMarkup('Folders'),
  admin_permission: self::ADMIN_PERMISSION,
  handlers: [
    'access' => EntityAccessControlHandler::class,
  ],
  entity_keys: [
    'id' => 'uuid',
    'label' => 'name',
  ],
  config_export: [
    'name',
    'configEntityTypeId',
    'weight',
    'items',
  ],
  constraints: [
    'ImmutableProperties' => ['uuid', 'configEntityTypeId'],
  ],
  additional: [
    // The client-side representation uses `id` as the identifier, not `uuid`.
    // @see ::normalizeForClientSide()
    'canvas_client_id_key' => 'id',
  ],
)]
final class Folder extends ConfigEntityBase implements CanvasHttpApiEligibleConfigEntityInterface {

  public const string ENTITY_TYPE_ID = 'folder';
  public const string ADMIN_PERMISSION = 'administer folders';

  protected string $name;
  protected string $configEntityTypeId;
  protected int $weight = 0;
  protected array $items = [];

  public function id(): ?string {
    return $this->uuid();
  }

  public function normalizeForClientSide(): ClientSideRepresentation {
    return ClientSideRepresentation::create(
      values: [
        'name' => $this->label(),
        'id' => $this->uuid(),
        'type' => $this->configEntityTypeId,
        'weight' => $this->weight,
        'items' => $this->items,
      ],
      preview: NULL,
    )->addCacheableDependency($this);
  }

  public static function createFromClientSide(array $data): static {
    $data['configEntityTypeId'] = $data['type'];
    unset($data['id']);
    unset($data['type']);
    return static::create($data);
  }

  public function updateFromClientSide(array $data): void {
    unset($data['id']);
    unset($data['type']);
    foreach ($data as $key => $value) {
      $this->set($key, $value);
    }
  }

  public static function refineListQuery(QueryInterface &$query, RefinableCacheableDependencyInterface $cacheability): void {
    $query->sort('weight');
  }

  public static function loadByNameAndConfigEntityTypeId(string $name, string $configEntityTypeId): self|NULL {
    $results = \Drupal::entityTypeManager()->getStorage(self::ENTITY_TYPE_ID)->loadByProperties([
      'name' => $name,
      'configEntityTypeId' => $configEntityTypeId,
    ]);
    // @phpstan-ignore return.type
    return !empty($results) ? reset($results) : NULL;
  }

  public static function loadByItemAndConfigEntityTypeId(string $item_id, string $configEntityTypeId): ?self {
    $query = \Drupal::entityTypeManager()->getStorage(self::ENTITY_TYPE_ID)
      ->getQuery()
      ->condition('configEntityTypeId', $configEntityTypeId)
      ->condition('items.*', $item_id)
      ->accessCheck(FALSE);
    $ids = $query->execute();
    return match (count($ids)) {
      0 => NULL,
      1 => self::load(reset($ids)),
      default => throw new \RuntimeException('It is impossible for an item to exist in multiple Folders.'),
    };
  }

  public function addItems(array $new_item_ids): self {
    $items_in_folder = $this->get('items');
    $new_items = array_unique([...$items_in_folder, ...$new_item_ids]);
    $this->set('items', array_values($new_items));
    return $this;
  }

  public function removeItem(string $remove_id): self {
    $items_in_folder = $this->get('items');
    $new_items = array_values(array_filter($items_in_folder, fn($item) => $item !== $remove_id));
    $this->set('items', $new_items);
    return $this;
  }

}
