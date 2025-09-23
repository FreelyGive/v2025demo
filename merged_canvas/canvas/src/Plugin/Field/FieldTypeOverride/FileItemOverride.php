<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\canvas\Plugin\Validation\Constraint\StringSemanticsConstraint;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\Entity\MediaType;

/**
 * @todo Fix upstream.
 * @property null|string|int $target_id
 */
class FileItemOverride extends FileItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['description']->addConstraint('StringSemantics', StringSemanticsConstraint::PROSE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    // Also treat '' as empty. The client-side transform we apply for the
    // file_generic widget reads the value of the hidden `fids` field. This
    // field contains an empty string when the value is removed. We could cast
    // this to NULL with a client-side transform but NULL values don't trigger
    // a state update in the page builder, so that would prevent any change at
    // all from being detected and sent to the back-end.
    return ($this->target_id === '') || parent::isEmpty();
  }

  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    // FileItem::generateSampleValue() creates File entity with hardcoded .txt
    // .extension, media:video requires .mp4 extension for video_file source.
    // @see \Drupal\file\Plugin\Field\FieldType\FileItem::generateSampleValue()
    // @todo Remove once https://www.drupal.org/project/drupal/issues/2550977 is in
    if ($field_definition->getSetting('file_extensions') == 'mp4' || $field_definition->getTargetEntityTypeId() === 'media' && !empty($field_definition->getTargetBundle()) && MediaType::load($field_definition->getTargetBundle())->getSource()->getPluginId() === 'video_file') {
      $random = new Random();
      $settings = $field_definition->getSettings();

      // Prepare destination.
      $dirname = static::doGetUploadLocation($settings);
      \Drupal::service('file_system')->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY);

      // Generate a File entity.
      $destination = $dirname . '/' . $random->name(10, TRUE) . '.mp4';
      $data = $random->paragraphs(3);
      /** @var \Drupal\file\FileRepositoryInterface $file_repository */
      $file_repository = \Drupal::service('file.repository');
      $file = $file_repository->writeData($data, $destination, FileExists::Error);

      return [
        'target_id' => $file->id(),
        'display' => (int) $settings['display_default'],
        'description' => $random->sentences(10),
      ];
    }
    return parent::generateSampleValue($field_definition);
  }

}
