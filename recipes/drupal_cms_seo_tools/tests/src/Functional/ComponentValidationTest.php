<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_seo_tools\Functional;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

#[Group('drupal_cms_seo_tools')]
#[IgnoreDeprecations]
class ComponentValidationTest extends BrowserTestBase {

  use RecipeTestTrait {
    applyRecipe as traitApplyRecipe;
  }

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // This ECA model uses actions which don't have config schema in ECA yet.
    'eca.eca.setup_seo_fields',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create a content type so we can test the changes made by the recipe.
    $this->drupalCreateContentType(['type' => 'test']);
  }

  private function applyRecipe(mixed ...$arguments): void {
    $dir = realpath(__DIR__ . '/../../..');
    $this->traitApplyRecipe($dir, ...$arguments);
  }

  public function test(): void {
    // The recipe should apply cleanly.
    $this->applyRecipe();
    // Apply it again to prove that it is idempotent.
    $this->applyRecipe();

    // SEO fields should have been created on the extant content type.
    $this->assertSeoFieldsExist('test');
    // If we create a new content type, the SEO fields should be automatically
    // created by ECA. The current user needs to have the `administer site
    // configuration` permission in order to execute config actions via ECA;
    // additionally, the ECA model switches to user 1 (relying on the
    // frowned-upon super user access policy, which we should fix at some point)
    // so we need to ensure that the root user is, in fact, user 1.
    // @see \Drupal\eca_config\Plugin\Action\ConfigAction::access()
    $this->assertSame(1, $this->rootUser->id());
    $this->assertTrue($this->rootUser->hasPermission('administer site configuration'));
    $this->setCurrentUser($this->rootUser);
    $this->assertSeoFieldsExist($this->drupalCreateContentType()->id());

    // Check that the sitemap works as expected for anonymous users.
    $this->checkSitemap();

    // Check sitemap works as expected for authenticated users too.
    $authenticated = $this->createUser();
    $this->drupalLogin($authenticated);
    $this->checkSitemap();
  }

  /**
   * Asserts the presence of standard SEO fields on a content type.
   *
   * @param string $node_type
   *   The machine name of a content type.
   */
  private function assertSeoFieldsExist(string $node_type): void {
    // There should be an SEO image field on the content type, referencing
    // image media.
    $field_settings = FieldConfig::loadByName('node', $node_type, 'field_seo_image')?->getSettings();
    $this->assertIsArray($field_settings);
    $this->assertSame('default:media', $field_settings['handler']);
    $this->assertContains('image', $field_settings['handler_settings']['target_bundles']);

    // The other SEO fields should exist as well.
    $this->assertIsObject(FieldConfig::loadByName('node', $node_type, 'field_seo_description'));
    $this->assertIsObject(FieldConfig::loadByName('node', $node_type, 'field_seo_title'));
    $this->assertIsObject(FieldConfig::loadByName('node', $node_type, 'field_seo_analysis'));

    // Ensure the fields are visible on the form, in a field group.
    $form_display = $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getFormDisplay('node', $node_type);
    $group = $form_display->getThirdPartySetting('field_group', 'group_seo');
    $this->assertIsArray($group);
    $this->assertSame(['field_seo_title', 'field_seo_description', 'field_seo_image', 'field_seo_analysis'], $group['children']);
    $this->assertIsArray($form_display->getComponent('field_seo_analysis'));
    $this->assertIsArray($form_display->getComponent('field_seo_description'));
    $this->assertIsArray($form_display->getComponent('field_seo_title'));
    $this->assertIsArray($form_display->getComponent('field_seo_image'));
  }

  public function testAutomaticSitemapSettings(): void {
    $this->applyRecipe();

    // We should have Simple Sitemap settings for the extant content type.
    $settings = $this->container->get('config.storage')
      ->listAll('simple_sitemap.bundle_settings');
    $this->assertSame(['simple_sitemap.bundle_settings.default.node.test'], $settings);

    $get_settings = function (string $node_type): Config {
      return $this->config("simple_sitemap.bundle_settings.default.node.$node_type");
    };
    // If we create a new content type programmatically, Simple Sitemap settings
    // should be generated for it automatically.
    $node_type = $this->drupalCreateContentType()->id();
    $this->assertFalse($get_settings($node_type)->isNew());

    // If we create a new content type in the UI, Simple Sitemap settings should
    // NOT be automatically generated.
    $account = $this->createUser([
      'administer content types',
      'administer sitemap settings',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/structure/types/add');
    $node_type = $this->randomMachineName();
    $this->submitForm([
      'name' => $node_type,
      'type' => $node_type,
      'simple_sitemap[default][index]' => 0,
    ], 'Save');
    $this->assertTrue($get_settings($node_type)->isNew());

    // Extant settings should not be changed...
    $get_settings('test')->set('priority', '0.3')->save();
    $this->assertSame('0.3', $get_settings('test')->get('priority'));
    // ...even if we reapply the recipe...
    $this->applyRecipe();
    $this->assertSame('0.3', $get_settings('test')->get('priority'));
    // ...or sync config (here, we are simulating that the priority was changed
    // by a config sync).
    $this->container->get(ConfigInstallerInterface::class)->setSyncing(TRUE);
    $get_settings('test')->set('priority', '0.2')->save();
    $this->assertSame('0.2', $get_settings('test')->get('priority'));
  }

  /**
   * Checks that the sitemap is accessible and contains the expected links.
   */
  private function checkSitemap(): void {
    // Create a main menu link to ensure it shows up in the site map.
    $node = $this->drupalCreateNode(['type' => 'test']);
    $menu_link = MenuLinkContent::create([
      'title' => $node->getTitle(),
      'link' => 'internal:' . $node->toUrl()->toString(),
      'menu_name' => 'main',
    ]);
    $menu_link->save();

    $this->drupalGet('/sitemap');

    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->linkByHrefNotExists('/rss.xml');

    $site_map = $assert_session->elementExists('css', '.sitemap');
    $site_name = $this->config('system.site')->get('name');
    $this->assertTrue($site_map->hasLink("Front page of $site_name"), 'Front page link does not appear in the site map.');
    $this->assertTrue($site_map->hasLink($menu_link->label()), 'Main menu links do not appear in the site map.');
  }

}
