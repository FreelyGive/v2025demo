<?php

declare(strict_types=1);

namespace Drupal\canvas\TypedData;

use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\canvas\Plugin\DataType\ConfigEntityVersionAdapter;

/**
 * A typed data definition class for describing a versioned config entity.
 */
final class ConfigEntityVersionDataDefinition extends EntityDataDefinition implements EntityDataDefinitionInterface {

  /**
   * {@inheritdoc}
   */
  public static function createFromDataType($data_type): static {
    $parts = \explode(':', $data_type);
    if ($parts[0] !== ConfigEntityVersionAdapter::PLUGIN_ID) {
      throw new \InvalidArgumentException(\sprintf('Data type must be in the form of "%s:ENTITY_TYPE."', ConfigEntityVersionAdapter::PLUGIN_ID));
    }
    $definition = static::create();
    // Set the passed entity type.
    if (($parts[1] ?? NULL) !== NULL) {
      $definition->setEntityTypeId($parts[1]);
    }
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataType() {
    $type = ConfigEntityVersionAdapter::PLUGIN_ID;
    if ($entity_type = $this->getEntityTypeId()) {
      $type .= ':' . $entity_type;
    }
    return $type;
  }

}
