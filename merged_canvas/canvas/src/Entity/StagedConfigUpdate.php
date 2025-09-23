<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\ClientSideRepresentation;
use Drupal\canvas\EntityHandlers\StagedConfigUpdateAccessControlHandler;
use Drupal\canvas\EntityHandlers\StagedConfigUpdateStorage;

/**
 * @phpstan-type StagedConfigUpdateActions array<string, array{name: string, input: array<string, mixed>}>
 */
#[ConfigEntityType(
  id: self::ENTITY_TYPE_ID,
  label: new TranslatableMarkup("Staged configuration update"),
  label_collection: new TranslatableMarkup("Staged configuration updates"),
  label_singular: new TranslatableMarkup("staged configuration update"),
  label_plural: new TranslatableMarkup("staged configuration updates"),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'storage' => StagedConfigUpdateStorage::class,
    'access' => StagedConfigUpdateAccessControlHandler::class,
  ],
  config_export: [
    'id',
    'label',
    'target',
    'actions',
  ],
)]
final class StagedConfigUpdate extends ConfigEntityBase implements CanvasHttpApiEligibleConfigEntityInterface, AutoSavePublishAwareInterface {

  public const string ENTITY_TYPE_ID = 'staged_config_update';

  /**
   * {@inheritdoc}
   *
   * Disabled by default to prevent the staged config update from being
   * applied automatically.
   */
  protected $status = FALSE;

  /**
   * The ID.
   */
  protected string $id;

  /**
   * The human-readable label of the staged config update.
   */
  protected string $label;

  /**
   * The config actions to perform, e.g., 'simpleConfigUpdate', 'set'.
   *
   * @phpstan-var StagedConfigUpdateActions
   */
  protected array $actions = [];

  /**
   * The target configuration name.
   */
  protected string $target = '';

  public function id(): string {
    return $this->id;
  }

  /**
   * @phpstan-return StagedConfigUpdateActions
   */
  public function getActions(): array {
    return $this->actions;
  }

  public function getTarget(): string {
    return $this->target;
  }

  public function getCacheTagsToInvalidate(): array {
    // This particular config entity never needs to be invalidated
    // as nothing cacheable can depend on it aside from the "Review changes"
    // panel. Instead, we use the configuration target.
    // @see parent::getCacheTagsToInvalidate().
    return ["config:$this->target"];
  }

  public function normalizeForClientSide(): ClientSideRepresentation {
    return ClientSideRepresentation::create(
      values: [
        'id' => $this->id,
        'label' => $this->label,
        'target' => $this->target,
        'actions' => $this->actions,
      ],
      preview: NULL
    );
  }

  public static function createFromClientSide(array $data): static {
    return self::create($data);
  }

  public function updateFromClientSide(array $data): void {
    // Prevent the client from changing the status. It should only be modified
    // when staged changes are published. Also prevent the client from
    // changing the target or ID of the staged config update.
    unset($data['status'], $data['target'], $data['id']);
    foreach ($data as $key => $value) {
      $this->set($key, $value);
    }
  }

  public static function refineListQuery(QueryInterface &$query, RefinableCacheableDependencyInterface $cacheability): void {
    // Nothing to do.
  }

  /**
   * {@inheritdoc}
   */
  public function autoSavePublish(): self {
    // @see \Drupal\canvas\EntityHandlers\StagedConfigUpdateStorage::save()
    $this->setStatus(TRUE);
    return $this;
  }

}
