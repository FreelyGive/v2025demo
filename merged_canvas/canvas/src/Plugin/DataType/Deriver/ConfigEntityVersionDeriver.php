<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\DataType\Deriver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\canvas\Entity\VersionedConfigEntityInterface;
use Drupal\canvas\Plugin\DataType\ConfigEntityVersionAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides data type plugins for each existing versioned config entity type.
 */
final class ConfigEntityVersionDeriver implements ContainerDeriverInterface {

  protected array $derivatives = [];
  protected string $basePluginId;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(
    string $base_plugin_id,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $base_plugin_id,
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition): ?array {
    if (!empty($this->derivatives) && !empty($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
    \assert(\is_array($base_plugin_definition));
    $this->getDerivativeDefinitions($base_plugin_definition);
    if (isset($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // Also keep the 'config_entity_version' defined as is.
    $this->derivatives[''] = $base_plugin_definition;
    // Add definitions for each config entity type.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(VersionedConfigEntityInterface::class)) {
        continue;
      }
      $this->derivatives[$entity_type_id] = [
        'class' => ConfigEntityVersionAdapter::class,
        'label' => $entity_type->getLabel(),
        'constraints' => $entity_type->getConstraints(),
        'internal' => $entity_type->isInternal(),
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
