<?php

namespace Drupal\export_media\Drush\Commands;

use Drupal\Core\Utility\Token;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * A Drush commandfile.
 */
final class ExportMediaCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs an ExportMediaCommands object.
   */
  public function __construct(
    private readonly Token $token,
  ) {
    parent::__construct();
  }

  /**
   * Export the media to the recipes.
   */
  #[CLI\Command(name: 'export_media:export', aliases: ['export-media'])]
  #[CLI\Usage(name: 'export_media:export', description: 'Exports all media to the recipes.')]
  public function commandName() {
    // Get all media.
    $medias = \Drupal::entityTypeManager()
      ->getStorage('media')
      ->loadMultiple();
    // Iteratre and get images.
    $this->logger()->notice('Exported all media to the recipes.');
    foreach ($medias as $media) {
      if ($media->bundle() === 'image') {
        // Run export command.
        $media_output = shell_exec('php core/scripts/drupal content:export media ' . $media->id());
        $yaml = Yaml::parse($media_output);
        $media_uuid = $yaml['_meta']['uuid'];
        // If that mediea already exists, skip it.
        if (file_exists('../custom_recipes/media_images/content/media/' . $media_uuid . '.yml')) {
          $this->logger()->notice('Media ' . $media->id() . ' already exists in the recipes, skipping.');
          continue;
        }
        // Get all the files.
        foreach ($yaml['default']['field_media_image'] as $item) {
          // Load file via uuid.
          $file = \Drupal::entityTypeManager()
            ->getStorage('file')
            ->loadByProperties(['uuid' => $item['entity']]);
          $file_output = shell_exec('php core/scripts/drupal content:export file ' . reset($file)->id());
          $file_yaml = Yaml::parse($file_output);
          $file_name = $file_yaml['default']['filename'][0]['value'];
          // If the file name ends with _0.jpg, _1.jpeg etc do not save it.
          if (preg_match('/_\\d+\\.(jpg|jpeg|png|gif)$/i', $file_name)) {
            $this->logger()->notice('File ' . reset($file)->id() . ' has a name that ends with number meaning doublet, skipping.');
            continue;
          }
          // Make sure that the file exists.
          if (file_exists($file_yaml['default']['uri'][0]['value'])) {
            // Now we can export the whole thing.
            copy($file_yaml['default']['uri'][0]['value'], '../custom_recipes/media_images/content/file/' . $file_name);
            file_put_contents('../custom_recipes/media_images/content/media/' . $media_uuid . '.yml', $media_output);
            file_put_contents('../custom_recipes/media_images/content/file/' . $file_yaml['_meta']['uuid'] . '.yml', $file_output);
            $this->logger()->notice('Exported media ' . $media->id() . ' and file ' . reset($file)->id() . ' to the recipes.');
          }
        }
      }
    }
  }

}
