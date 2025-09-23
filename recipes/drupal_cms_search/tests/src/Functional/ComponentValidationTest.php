<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_search\Functional;

use Composer\InstalledVersions;
use Drupal\Core\State\StateInterface;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;

#[Group('drupal_cms_search')]
#[IgnoreDeprecations]
class ComponentValidationTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  #[TestWith(['drupal/drupal_cms_blog', 'blog'])]
  #[TestWith(['drupal/drupal_cms_case_study', 'case_study'])]
  #[TestWith(['drupal/drupal_cms_events', 'event'])]
  #[TestWith(['drupal/drupal_cms_news', 'news'])]
  #[TestWith(['drupal/drupal_cms_page', 'page'])]
  #[TestWith(['drupal/drupal_cms_person', 'person'])]
  #[TestWith(['drupal/drupal_cms_project', 'project'])]
  public function testContentIsIndexed(string $recipe, string $node_type): void {
    $recipe = InstalledVersions::getInstallPath($recipe);
    $this->applyRecipe($recipe);

    $this->drupalCreateNode([
      'type' => $node_type,
      'title' => "Search for this $node_type",
      'moderation_state' => 'published',
      // Make the node owned by user 1 so we can prove that it is visible to
      // anonymous users when published and searched for.
      'uid' => 1,
    ]);

    // Apply the search recipe twice to prove that it applies cleanly and is
    // idempotent.
    $dir = realpath(__DIR__ . '/../../..');
    $this->applyRecipe($dir);
    $this->applyRecipe($dir);

    // Reset the last cron run time, so we can prove that the next request
    // triggers a cron run.
    $last_run = 0;
    $state = $this->container->get(StateInterface::class);
    $state->set('system.cron_last', $last_run);
    $this->drupalGet('/search');
    $seconds_waited = 0;
    // Give cron up to a minute.
    while (empty($last_run) && $seconds_waited < 60) {
      sleep(1);
      $seconds_waited++;
      $last_run = $state->get('system.cron_last');
    }
    $this->assertGreaterThan(0, $last_run);

    // Ensure that we can search for the content we just created.
    $page = $this->getSession()->getPage();
    $page->fillField('Search keywords', $node_type);
    $page->pressButton('Find');
    $this->assertSession()->linkExists("Search for this $node_type");
  }

}
