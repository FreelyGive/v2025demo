<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_starter\Functional;

use Composer\InstalledVersions;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;

#[Group('drupal_cms_starter')]
#[IgnoreDeprecations]
class ListingPagesTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('system_menu_block:main', [
      'label' => 'Main menu',
    ]);
  }

  #[TestWith(['drupal/drupal_cms_case_study', 'case_study', 'Case studies', '/case-studies', 'Add a case study'])]
  #[TestWith(['drupal/drupal_cms_events', 'event', 'Events', '/events', 'Add an event'])]
  #[TestWith(['drupal/drupal_cms_news', 'news', 'News', '/news', 'Add a news item'])]
  #[TestWith(['drupal/drupal_cms_project', 'project', 'Projects', '/projects', 'Add a project'])]
  #[TestWith(['drupal/drupal_cms_person', 'person', 'People', '/people', 'Add a person profile'])]
  public function testListingPages(string $recipe, string $content_type, string $link_text, string $url, string $create_link_text): void {
    $dir = InstalledVersions::getInstallPath($recipe);
    $this->applyRecipe($dir);

    $this->drupalCreateNode([
      'type' => $content_type,
      'title' => 'Item 1',
      'moderation_state' => 'published',
    ]);
    $this->drupalCreateNode([
      'type' => $content_type,
      'title' => 'Item 2',
      'moderation_state' => 'published',
    ]);
    $this->drupalCreateNode([
      'type' => $content_type,
      'title' => 'Item 3',
    ]);

    // Ensure we can access the listing page as an anonymous user.
    $this->drupalGet('<front>');
    $assert_session = $this->assertSession();
    $assert_session->elementExists('css', 'h2:contains("Main menu") + ul')
      ->clickLink($link_text);
    $assert_session->addressEquals($url);
    $assert_session->linkExists('Item 1');
    $assert_session->linkExists('Item 2');
    $assert_session->linkNotExists('Item 3');
    // If we log in as a content editor, we still should not see the unpublished item.
    $editor = $this->drupalCreateUser();
    $editor->addRole('content_editor')->save();
    $this->drupalLogin($editor);
    $this->drupalGet($url);
    $assert_session->linkNotExists('Item 3');
    // We should see a link to create a new item.
    $assert_session->linkExists($create_link_text);
  }

}
