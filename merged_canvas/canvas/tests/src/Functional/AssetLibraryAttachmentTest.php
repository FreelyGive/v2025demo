<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\Page;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests AssetLibrary config entities' generated assets load successfully.
 *
 * @group canvas
 */
final class AssetLibraryAttachmentTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['canvas'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * @covers \Drupal\canvas\Hook\ComponentSourceHooks::pageAttachments
   */
  public function test(): void {
    // We need to disable CSS/JS aggregation to test the raw assets.
    $config = $this->container->get(ConfigFactoryInterface::class)->getEditable('system.performance');
    $config->set('js.preprocess', FALSE);
    $config->set('css.preprocess', FALSE);
    $config->save();

    // Simulate 3 users:
    // - visitor (end user)
    // - content creator (able to modify >=1 entity with an Canvas field)
    // - code component developer (without the ability to create content)
    $visitor = $this->drupalCreateUser(['access content']);
    $this->assertInstanceOf(AccountInterface::class, $visitor);
    $content_creator = $this->drupalCreateUser([Page::EDIT_PERMISSION]);
    $this->assertInstanceOf(AccountInterface::class, $content_creator);
    $code_component_developer = $this->drupalCreateUser([JavaScriptComponent::ADMIN_PERMISSION]);
    $this->assertInstanceOf(AccountInterface::class, $code_component_developer);

    $library = AssetLibrary::load(AssetLibrary::GLOBAL_ID);
    \assert($library instanceof AssetLibrary);
    // Ensure the library has both JavaScript and CSS.
    $library->set('css', [
      'original' => '.regular-content { color: blue; }',
      'compiled' => '.regular-content{color:blue}',
    ]);
    $library->set('js', [
      'original' => 'console.log("Regular Content")',
      'compiled' => 'console.log("Regular Content")',
    ]);
    $library->save();

    $page = Page::create([
      'title' => 'Test page',
      'type' => 'page',
    ]);
    $this->assertSame(SAVED_NEW, $page->save());

    $url_generator = \Drupal::service(FileUrlGeneratorInterface::class);
    $regular_css_path = $url_generator->generateString($library->getCssPath());
    $regular_js_path = $url_generator->generateString($library->getJsPath());
    $auto_save_css_path = base_path() . 'canvas/api/v0/auto-saves/css/asset_library/' . $library->id();
    $auto_save_js_path = base_path() . 'canvas/api/v0/auto-saves/js/asset_library/' . $library->id();

    $assert_library_global_library = function (string $path, bool $is_preview) use ($regular_css_path, $regular_js_path, $auto_save_css_path, $auto_save_js_path) {
      $response = $this->drupalGet($path);
      $parsed_response = json_decode($response, TRUE);
      if ($parsed_response === NULL) {
        $html = $response;
      }
      else {
        $parsed_response = json_decode($response, TRUE);
        $html = $parsed_response['html'];
      }
      $crawler = new Crawler($html);
      self::assertCount($is_preview ? 0 : 1, $crawler->filter('link[href^="' . $regular_css_path . '"]'));
      self::assertCount($is_preview ? 0 : 1, $crawler->filter('script[src^="' . $regular_js_path . '"]'));
      self::assertCount($is_preview ? 1 : 0, $crawler->filter('link[href^="' . $auto_save_css_path . '"]'));
      self::assertCount($is_preview ? 1 : 0, $crawler->filter('script[src^="' . $auto_save_js_path . '"]'));
    };
    // Case 1: Visitor on a regular page should use regular asset library.
    $this->drupalLogin($visitor);
    $assert_library_global_library('/user', FALSE);

    // Case 2: A content creator should see the regular asset library on the
    // regular page also.
    $this->drupalLogin($content_creator);
    $assert_library_global_library('/user', FALSE);

    $this->drupalGet($regular_css_path);
    $this->assertSame($library->getCss(), $this->getTextContent());

    $this->drupalGet($regular_js_path);
    $this->assertSame($library->getJs(), $this->getTextContent());

    // Case 3: Route with _canvas_use_template_draft should use regular asset
    // library if there is no auto-saved version.
    $assert_library_global_library('/canvas/api/v0/layout/canvas_page/' . $page->id(), TRUE);

    // Create auto-save data for the global asset library.
    $auto_save_data = [
      'css' => [
        'original' => '.auto-save-content { color: red; }',
        'compiled' => '.auto-save-content{color:red}',
      ],
      'js' => [
        'original' => 'console.log("Auto-save Content")',
        'compiled' => 'console.log("Auto-save Content")',
      ],
    ];

    $auto_save_manager = $this->container->get(AutoSaveManager::class);
    $library->updateFromClientSide($auto_save_data);
    $auto_save_manager->saveEntity($library);

    // Case 4: Route with _canvas_use_template_draft should use auto-saved version
    // library if it exists.
    $assert_library_global_library('/canvas/api/v0/layout/canvas_page/' . $page->id(), TRUE);

    // Case 5: Test that on regular page the content creator sees the regular
    // version even if the auto-save version exists.
    $assert_library_global_library('/user', FALSE);

    // Case 6: Test that the auto-save version is accessible .
    $assert_auto_save_access = function (string $path, bool $should_pass) use ($auto_save_js_path) {
      $response = $this->drupalGet($path);
      $parsed_response = json_decode($response, TRUE);

      if (!$should_pass) {
        $this->assertSession()->statusCodeEquals(403);
        self::assertSame('Requires >=1 content entity type with an Canvas field that can be created or edited.', $parsed_response['errors'][0]);
      }
      else {
        $this->assertSession()->statusCodeEquals(200);
        if ($path == $auto_save_js_path) {
          self::assertSame('console.log("Auto-save Content")', $response);
        }
        else {
          self::assertSame('.auto-save-content{color:red}', $response);
        }
      }
    };

    // A visitor should not have access to the auto-saved assets and therefore
    // get an error.
    $this->drupalLogin($visitor);
    $assert_auto_save_access($auto_save_js_path, FALSE);
    $assert_auto_save_access($auto_save_css_path, FALSE);

    // User with permission to create/edit code components should have access
    // to the auto-saved assets.
    $this->drupalLogin($code_component_developer);
    $assert_auto_save_access($auto_save_js_path, TRUE);
    $assert_auto_save_access($auto_save_css_path, TRUE);

    // User with permission to access the Canvas UI (and hence is allowed to access
    // previews, which in turns means they must be able to see auto-saved code
    // components) should have access to the auto-saved assets.
    $this->drupalLogin($content_creator);
    $assert_auto_save_access($auto_save_js_path, TRUE);
    $assert_auto_save_access($auto_save_css_path, TRUE);
  }

}
