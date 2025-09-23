<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_helper\Functional;

use Drupal\Core\Recipe\RecipeRunner;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

#[Group('drupal_cms_helper')]
class RecipeFrontPageTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'path'];

  /**
   * Tests that the front page is set via a recipe's extra property.
   */
  public function testFrontPageRecipeConfiguration(): void {
    $this->drupalCreateContentType(['type' => 'page']);

    $node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Front Page',
      'path' => '/custom-front-page',
    ]);
    $this->assertStringEndsWith('/custom-front-page', $node->toUrl()->toString());

    $recipe = $this->createRecipe([
      'name' => 'Test Front Page Recipe',
      'description' => 'Sets front page via extra property',
      'type' => 'Site',
      'install' => [
        'drupal_cms_helper',
      ],
      'config' => [
        'actions' => [
          'system.site' => [
            'simpleConfigUpdate' => [
              'page.front' => '/custom-front-page',
            ],
          ],
        ],
      ],
    ]);
    RecipeRunner::processRecipe($recipe);
    $this->assertSame('/node/' . $node->id(), $this->config('system.site')->get('page.front'));
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains($node->body->value);
  }

}
