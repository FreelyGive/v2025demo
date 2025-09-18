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
class ContentEditingTest extends BrowserTestBase {

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
  public function testMenuSettingsVisibility(string $recipe_name, string $content_type): void {
    // Apply the recipe for the given content type.
    $dir = InstalledVersions::getInstallPath($recipe_name);
    $this->applyRecipe($dir);

    $account = $this->drupalCreateUser();
    $account->addRole('content_editor')->save();
    $this->drupalLogin($account);
    $this->drupalGet("/node/add/$content_type");

    // Only pages should have menu settings.
    $this->assertSame(
      $content_type === 'page',
      str_contains($this->getSession()->getPage()->getText(), 'Menu settings'),
    );
    // Verify the form loaded without errors.
    $this->assertSession()->statusCodeEquals(200);
  }

}
