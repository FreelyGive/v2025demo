<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageCopyTrait;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DefaultContent\Exporter as ContentExporter;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class SiteExporter implements LoggerAwareInterface {

  use LoggerAwareTrait;
  use StorageCopyTrait;
  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleExtensionList $moduleList,
    private readonly ThemeExtensionList $themeList,
    private readonly FileSystemInterface $fileSystem,
    private readonly StorageInterface $storage,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ConfigManagerInterface $configManager,
    private readonly ?ContentExporter $contentExporter = NULL,
  ) {}

  /**
   * Exports the current site's configuration and content into a recipe.
   *
   * @param string $destination
   *   The path where the recipe should be created.
   */
  public function export(string $destination): void {
    $name = basename($destination);
    $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // All installed modules and themes, except for the install profile (which
    // is irrelevant for a recipe), will be listed in the recipe's `install`
    // list.
    $extensions = $this->getInstalledExtensions();
    $recipe = [
      'name' => $this->configFactory->get('system.site')->get('name'),
      'type' => 'Site',
      'install' => array_keys($extensions),
      'config' => [
        'strict' => FALSE,
      ],
    ];

    // All simple config from the System and User modules needs to be updated
    // with config actions, because it's guaranteed to exist before the recipe
    // is applied.
    $names = [
      ...$this->storage->listAll('system.'),
      ...$this->storage->listAll('user.'),
    ];
    $names = array_filter(
      $names,
      fn (string $name): bool => $this->configManager->getEntityTypeIdByName($name) === NULL,
    );
    foreach ($this->storage->readMultiple($names) as $name => $data) {
      unset($data['_core'], $data['uuid']);
      $recipe['config']['actions'][$name]['simpleConfigUpdate'] = $data;
    }
    file_put_contents($destination . '/recipe.yml', Yaml::encode($recipe));

    // This will strip out the `_core` and `uuid` keys from all config before
    // writing it to the file system.
    $storage = new class ($destination . '/config') extends FileStorage {

      /**
       * {@inheritdoc}
       */
      public function write($name, array $data): bool {
        // Work around https://www.drupal.org/i/3002532.
        if (preg_match('/^language\.entity\.(?!und|zxx)/', $name)) {
          $data['dependencies']['config'][] = 'language.entity.und';
          $data['dependencies']['config'][] = 'language.entity.zxx';
        }
        unset($data['_core'], $data['uuid']);
        return parent::write($name, $data);
      }

    };
    static::replaceStorageContents($this->storage, $storage);
    // The core.extension config should never be included in a recipe.
    $names[] = 'core.extension';
    array_walk($names, $storage->delete(...));

    // Write `composer.json`, with version constraints for all installed
    // extensions.
    $composer = [
      'name' => 'drupal/' . $name,
      'type' => Recipe::COMPOSER_PROJECT_TYPE,
      'require' => $this->getExtensionRequirements($extensions),
    ];
    file_put_contents($destination . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Export all content, with its dependencies, as files.
    foreach ($this->loadAllContent() as $entity) {
      $this->contentExporter?->exportWithDependencies($entity, $destination . '/content');
    }
  }

  /**
   * Loads all exportable content entities.
   *
   * @return iterable<\Drupal\Core\Entity\ContentEntityInterface>
   *   An iterable that yields content entities.
   */
  private function loadAllContent(): iterable {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      // Path aliases are created when the content is, and therefore should not
      // be exported. Internal entities are more of a grey area, but we can
      // safely assume they shouldn't be exported (content moderation states are
      // the main example in core).
      if ($entity_type->isInternal() || $id === 'path_alias') {
        continue;
      }
      if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
        $storage = $this->entityTypeManager->getStorage($id);
        $query = $storage->getQuery()->accessCheck(FALSE);
        // Ignore users 0 or 1, since they always exist with those IDs.
        if ($id === 'user') {
          $query->condition('uid', 1, '>');
        }
        foreach ($query->execute() as $entity_id) {
          yield $storage->load($entity_id);
        }
      }
    }
  }

  /**
   * Finds all installed modules and themes.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   All installed extensions, keyed by machine name.
   */
  private function getInstalledExtensions(): array {
    $modules = array_intersect_key(
      $this->moduleList->getList(),
      $this->moduleList->getAllInstalledInfo(),
    );
    $themes = array_intersect_key(
      $this->themeList->getList(),
      $this->themeList->getAllInstalledInfo(),
    );
    return array_filter(
      [...$modules, ...$themes],
      // Install profiles should always be excluded from recipes.
      fn (Extension $e): bool => $e->getType() !== 'profile',
    );
  }

  /**
   * Generates Composer version constraints for a set of extensions.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   A set of extensions.
   *
   * @return array<string, string>
   *   An array of Composer version constraints, keyed by package name.
   */
  private function getExtensionRequirements(array $extensions): array {
    $requirements = [];

    foreach ($extensions as $name => $extension) {
      $package_name = str_starts_with($extension->getPath(), 'core/')
        ? 'drupal/core'
        : 'drupal/' . ($extension->info['project'] ?? $name);

      try {
        $version = InstalledVersions::getPrettyVersion($package_name);
      }
      catch (\OutOfBoundsException) {
        $message = $this->t('Cannot determine a version constraint for @type @names because the package @package does not appear to be installed.', [
          '@type' => $extension->getType(),
          '@name' => $name,
          '@package' => $package_name,
        ]);
        $this->logger?->warning((string) $message);
        continue;
      }

      $stability = VersionParser::parseStability($version);
      $stability = VersionParser::normalizeStability($stability);
      $requirements[$package_name] = $stability === 'dev' ? $version : "^$version";

      if ($stability !== 'stable') {
        $message = $this->t('Package @package has a @stability version constraint, which may prevent the recipe from being installed into projects that require stable dependencies.', [
          '@stability' => $stability,
          '@package' => $package_name,
        ]);
        $this->logger?->warning((string) $message);
      }
    }
    return $requirements;
  }

}
