<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_helper\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\drupal_cms_helper\Drush\Commands\SiteCommands;
use Drupal\drupal_cms_helper\SiteExporter;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('drupal_cms_helper')]
#[CoversClass(SiteExporter::class)]
#[CoversClass(SiteCommands::class)]
final class SiteExportTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_cms_helper'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'demo_umami';

  public function testExportSite(): void {
    $destination = $this->publicFilesDirectory . '/export';

    $this->drush(
      'site:export',
      options: ['destination' => $destination],
    );

    // Parse the generated recipe so we can examine it more closely.
    $recipe = file_get_contents($destination . '/recipe.yml');
    $recipe = Yaml::decode($recipe);

    // All installed extensions should be listed in the recipe, except the
    // install profile, which should be explicitly excluded.
    $installed = [
      ...array_keys($this->container->get(ModuleExtensionList::class)->getAllInstalledInfo()),
      ...array_keys($this->container->get(ThemeExtensionList::class)->getAllInstalledInfo()),
    ];
    $installed = array_values(array_diff($installed, [$this->profile]));
    sort($installed);
    sort($recipe['install']);
    $this->assertSame($installed, $recipe['install']);

    // Config should have been exported and none of it should have it UUIDs or
    // the _core key.
    $storage = new FileStorage($destination . '/config');
    $names = $storage->listAll();
    $this->assertGreaterThan(0, count($names));
    // The core.extension config should always be excluded from a recipe.
    $this->assertNotContains('core.extension', $names);
    foreach ($names as $name) {
      $data = $storage->read($name);
      $this->assertIsArray($data);
      $this->assertArrayNotHasKey('uuid', $data);
      $this->assertArrayNotHasKey('_core', $data);
      // No simple config from System or User should have been exported as a
      // file, but should have been put into config actions.
      if (substr_count($name, '.') === 1) {
        $this->assertStringStartsNotWith('system.', $name);
        $this->assertStringStartsNotWith('user.', $name);
      }
    }

    // Content should have been exported.
    $finder = new Finder($destination . '/content');
    $this->assertNotEmpty($finder->data);

    // Installed non-core modules should have been added to `composer.json`.
    $composer_data = file_get_contents($destination . '/composer.json');
    $this->assertStringContainsString('"drupal/drupal_cms_helper": ', $composer_data);
  }

}
