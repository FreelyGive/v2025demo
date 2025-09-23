<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\DataType;

use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\VersionedConfigEntityInterface;

/**
 * Defines a 'config_entity_version_reference' data type.
 *
 * This serves as 'entity' property where you need to reference a specific
 * version of a config entity.
 *
 * The plain value of this reference is the config entity object, i.e. an
 * instance of \Drupal\canvas\Entity\VersionedConfigEntityInterface.
 * For setting the value the entity object or the entity ID may be passed.
 *
 * @property ?\Drupal\Core\TypedData\TypedDataInterface $target
 * @property ?string $id
 */
#[DataType(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Versioned config entity reference'),
  definition_class: DataReferenceDefinition::class,
)]
final class ConfigEntityVersionReference extends EntityReference {

  private const string PLUGIN_ID = 'config_entity_version_reference';

  /**
   * The config entity version.
   *
   * @var ?string
   */
  protected ?string $version = NULL;

  /**
   * The entity ID.
   *
   * @var string
   */
  protected $id;

  /**
   * Returns the definition of the referenced entity.
   *
   * @return \Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface
   *   The reference target's definition.
   */
  public function getTargetDefinition(): EntityDataDefinitionInterface {
    \assert($this->definition instanceof DataReferenceDefinition);
    /** @var \Drupal\canvas\TypedData\ConfigEntityVersionDataDefinition */
    return $this->definition->getTargetDefinition();
  }

  /**
   * Checks whether the target entity has not been saved yet.
   *
   * @return bool
   *   TRUE if the entity is new, FALSE otherwise.
   */
  public function isTargetNew() {
    // If only an ID is given, the reference cannot be a new entity.
    // @phpstan-ignore-next-line isset.property
    return !isset($this->id) && isset($this->target) && $this->target->getValue()->isNew();
  }

  /**
   * {@inheritdoc}
   */
  public function getTarget(): ?TypedDataInterface {
    // @phpstan-ignore-next-line isset.property
    if (!isset($this->target) && isset($this->id)) {
      $entity_type_id = $this->getTargetDefinition()->getEntityTypeId();
      \assert(\is_string($entity_type_id));
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
      // By default, always load the default version, so caches get used.
      $entity = $storage->load($this->id);
      \assert($entity instanceof VersionedConfigEntityInterface || $entity === NULL);
      if ($entity !== NULL &&
        $this->version !== NULL &&
        $entity->getLoadedVersion() !== $this->version &&
        // Do not allow a component instance to explicitly reference the fallback version.
        $this->version !== ComponentInterface::FALLBACK_VERSION
      ) {
        // A non-default version is referenced, so load that one.
        try {
          $entity->loadVersion($this->version);
        }
        catch (\OutOfRangeException) {
          // Validation will catch this.
        }
      }
      $this->target = $entity?->getTypedData() ?? NULL;
    }
    return $this->target;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetIdentifier(): ?string {
    if ($this->id !== NULL) {
      return $this->id;
    }
    if ($entity = $this->getValue()) {
      return $entity->id();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    unset($this->target);
    unset($this->id);
    unset($this->version);

    if ($value === NULL) {
      $this->target = NULL;
      $this->doNotify($notify);
      return;
    }
    if ($value instanceof VersionedConfigEntityInterface) {
      $this->target = $value->getTypedData();
      $this->doNotify($notify);
      return;
    }
    if (\is_string($value)) {
      // If only a single value is passed, this is a config entity ID.
      $this->id = $value;
      // Reset the version ID as none was passed we default to the active
      // version.
      $this->version = NULL;
      $target = $this->getTarget();
      \assert($target instanceof ConfigEntityVersionAdapter || $target === NULL);
      $this->version = $target?->getValue()->getActiveVersion();
      $this->doNotify($notify);
      return;
    }
    if (\is_array($value) && !\is_string($value['target_id']) || !\is_string($value['version']) || $this->getTargetDefinition()->getEntityTypeId() === NULL) {
      throw new \InvalidArgumentException('Value is not a valid config entity.');
    }
    $this->id = $value['target_id'];
    $this->version = $value['version'];
    $this->doNotify($notify);
  }

  private function doNotify(bool $notify): void {
    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

}
