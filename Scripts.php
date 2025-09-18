<?php

declare(strict_types=1);

use Composer\Package\PackageInterface;
use Composer\Script\Event as ScriptEvent;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Recipe\Recipe;

/**
 * Implements internal Composer scripts for developing Drupal CMS.
 */
class Scripts {

  /**
   * Adds a package to the curated list of recommended projects.
   *
   * Usage: composer dev:recommend <package-name> <project-title>
   *
   * @param \Composer\Script\Event $event
   *   The script event being handled.
   */
  public static function recommend(ScriptEvent $event): void {
    [$package_name, $title] = $event->getArguments();

    $package = $event->getComposer()
      ->getRepositoryManager()
      ->getLocalRepository()
      ->findPackage($package_name, '*');
    assert($package instanceof PackageInterface, "$package_name is not installed.");

    $file = __DIR__ . '/recommended.yml';
    $data = file_get_contents($file);
    $data = Yaml::decode($data);

    $project = [
      'title' => $title,
      'type' => match ($package->getType()) {
        'drupal-module' => 'module',
        Recipe::COMPOSER_PROJECT_TYPE => 'recipe',
      },
      'logo' => NULL,
      // Match any version of core by default, but it's STRONGLY recommended
      // that any curated package have an explicit constraint for core.
      'core' => '*',
      'machineName' => explode('/', $package_name, 2)[1],
      'body' => [
        'summary' => $package->getDescription(),
      ],
      'packageName' => $package_name,
    ];
    foreach ($package->getRequires() as $requirement) {
      if ($requirement->getTarget() === 'drupal/core') {
        $project['core'] = $requirement->getPrettyConstraint();
      }
    }
    // To prevent duplicates, key by package name.
    $data[$package_name] = $project;
    // Sort by package name.
    ksort($data, SORT_NATURAL);

    $comment = <<<EOF
# A curated list of recommended add-ons. This file is part of Drupal CMS's API and should
# not be renamed or removed unless you know precisely what you're doing. Its canonical URL
# is https://git.drupalcode.org/api/v4/projects/157093/repository/files/recommended.yml/raw?ref=HEAD
EOF;
    file_put_contents($file, $comment . "\n" . Yaml::encode($data));
  }

}
