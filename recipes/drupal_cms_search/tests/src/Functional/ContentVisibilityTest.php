<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_search\Functional;

use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;

#[Group('drupal_cms_search')]
#[IgnoreDeprecations]
final class ContentVisibilityTest extends BrowserTestBase {

  use CronRunTrait;
  use RecipeTestTrait;

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
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page']);
    $this->applyRecipe(__DIR__ . '/../../..');

    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, [
      'access content',
    ]);
  }

  #[TestWith([TRUE, FALSE])]
  #[TestWith([TRUE, TRUE])]
  #[TestWith([FALSE, TRUE])]
  #[TestWith([FALSE, FALSE])]
  public function test(bool $published, bool $excluded): void {
    $node = $this->drupalCreateNode([
      'type' => 'page',
      'status' => $published,
      'sae_exclude' => $excluded,
      'uid' => 1,
    ]);
    $this->cronRun();
    $this->drupalGet('/search');
    $this->assertSame($published && !$excluded, $this->getSession()->getPage()->hasLink($node->getTitle()));
  }

}
