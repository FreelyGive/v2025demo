<?php

declare(strict_types=1);

namespace Drupal\canvas\EntityHandlers;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\canvas\Entity\CanvasAssetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines storage handler for Canvas config entities implementing CanvasAssetInterface.
 *
 * @internal
 */
class CanvasAssetStorage extends ConfigEntityStorage implements EntityHandlerInterface {

  private FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    $instance = parent::createInstance($container, $entity_type);
    $instance->fileSystem = $container->get(FileSystemInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    $result = parent::doSave($id, $entity);

    // Update the file system representations of the asset library described by
    // this config entity.
    assert($entity instanceof CanvasAssetInterface);
    $this->generateFiles($entity);

    return $result;
  }

  /**
   * Generates the CSS and JS assets on disk for the given CanvasAssetInterface.
   *
   * Does NOT handle asset library updates.
   *
   * @see \canvas_library_info_builds()
   * @see \Drupal\canvas\Entity\AssetLibrary::postSave()
   * @see \Drupal\canvas\Entity\JavaScriptComponent::postSave()
   */
  public function generateFiles(CanvasAssetInterface $entity): void {
    $this->write($entity->getCssPath(), $entity->getCss());
    $this->write($entity->getJsPath(), $entity->getJs());
  }

  private function write(string $filename, string $data): void {
    if (trim($data)) {
      $dir = dirname($filename);
      $this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $this->fileSystem->saveData($data, $filename, FileExists::Replace);
    }
  }

}
