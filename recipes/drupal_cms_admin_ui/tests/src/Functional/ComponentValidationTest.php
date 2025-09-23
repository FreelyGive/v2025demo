<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_admin_ui\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

#[Group('drupal_cms_admin_ui')]
#[IgnoreDeprecations]
class ComponentValidationTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  public function test(): void {
    $dir = realpath(__DIR__ . '/../../..');

    // The recipe should apply cleanly.
    $this->applyRecipe($dir);
    // Apply it again to prove that it is idempotent.
    $this->applyRecipe($dir);

    $account = $this->drupalCreateUser([
      'access navigation',
      'view welcome dashboard',
      // Needed to see the announcements block.
      'access announcements',
      // Permissions needed to access the top tasks.
      'administer menu',
      'administer modules',
      'administer themes',
      'administer users',
    ]);
    // Don't use one-time login links, because they will bypass the dashboard
    // upon login.
    $this->useOneTimeLoginLinks = FALSE;
    $this->drupalLogin($account);
    $assert_session = $this->assertSession();

    // Find the top-level items in the navigation.
    $navigation_items = array_map(
      fn (NodeElement $item): string => $item->getText(),
      $this->getSession()->getPage()->findAll('css', '.toolbar-block__list > li > a'),
    );
    // Every item should only appear once.
    $items_count = array_count_values($navigation_items);
    $this->assertSame(count($items_count), array_sum($items_count));
    // The Dashboard link should be the first navigation item.
    $this->assertSame('Dashboard', $navigation_items[0]);

    // We should be on the welcome dashboard.
    $assert_session->addressEquals('/admin/dashboard');
    // There should be a menu of top tasks, and everything in it should be
    // accessible to us.
    $top_tasks = $assert_session->elementExists('css', 'h2:contains("Top tasks") + ul');
    $top_task_links = [
      'Choose recommended add-ons',
      'Browse modules',
      'Change site appearance',
      'Invite users to collaborate',
      'Edit top tasks',
    ];
    foreach ($top_task_links as $link) {
      $top_tasks->clickLink($link);
      $this->assertSame(200, $this->getSession()->getStatusCode(), "The '$link' link was not accessible.");
      $this->drupalGet('/admin/dashboard');
    }
    // We should see the Announcements block.
    $assert_session->pageTextContains('Announcements');
    $assert_session->elementExists('named', ['link', 'View all announcements']);

    // Ensure that the Project Browser tabs are renamed as expected.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalGet('/admin/modules');
    // Get all local task elements, indexed by their link text.
    $elements = $assert_session->elementExists('css', 'h2:contains("Primary tabs")')
      ->getParent()
      ->findAll('css', 'a');
    $local_tasks = [];
    foreach ($elements as $element) {
      $link_text = $element->getText();
      $local_tasks[$link_text] = $element;
    }
    $this->assertArrayHasKey('Browse modules', $local_tasks);
    // The first task should go to core's regular modules page.
    $this->assertSame('admin/modules', reset($local_tasks)->getAttribute('data-drupal-link-system-path'));
    // We should have access to Project Browser.
    $local_tasks['Browse modules']->click();
    $assert_session->statusCodeEquals(200);
    $assert_session->addressEquals('/admin/modules/browse/drupalorg_jsonapi');

    $this->drupalLogout();
    // Ensure that there are no broken blocks in the navigation (or anywhere
    // else). We need to test this with the root user because they have all
    // permissions, and therefore any broken blocks in the navigation will be
    // obvious to them.
    $this->drupalLogin($this->rootUser);
    $assert_session->pageTextNotContains('This block is broken or missing.');
  }

}
