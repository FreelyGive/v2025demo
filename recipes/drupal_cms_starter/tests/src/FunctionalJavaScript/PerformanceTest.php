<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_starter\FunctionalJavascript;

use Composer\InstalledVersions;
use Drupal\canvas\JsonSchemaDefinitionsStreamwrapper;
use Drupal\FunctionalJavascriptTests\PerformanceTestBase;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * Tests the performance of the drupal_cms_starter recipe.
 *
 * Stark is used as the default theme so that this test is not Olivero specific.
 */
#[Group('OpenTelemetry')]
#[Group('#slow')]
#[RequiresPhpExtension('apcu')]
#[IgnoreDeprecations]
class PerformanceTest extends PerformanceTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function rebuildAll(): void {
    // The rebuild won't succeed without the `json-schema-definitions` stream
    // wrapper. This would normally happen automatically whenever a module is
    // installed, but in this case, all of that has taken place in a separate
    // process, so we need to refresh this process manually.
    // @see canvas_module_preinstall()
    $this->container->get('stream_wrapper_manager')
      ->registerWrapper(
        'json-schema-definitions',
        JsonSchemaDefinitionsStreamwrapper::class,
        JsonSchemaDefinitionsStreamwrapper::getType(),
      );

    parent::rebuildAll();
  }

  /**
   * Tests performance of the starter recipe.
   */
  public function testPerformance(): void {
    $dir = InstalledVersions::getInstallPath('drupal/drupal_cms_starter');
    $this->applyRecipe($dir);

    // Applying the recipe installs automated cron, but we don't want cron to
    // run in the middle of a performance test, so uninstall it.
    \Drupal::service('module_installer')->uninstall(['automated_cron']);
    $this->doTestAnonymousFrontPage();
    $this->doTestEditorFrontPage();
  }

  /**
   * Check the anonymous front page with a hot cache.
   */
  protected function doTestAnonymousFrontPage(): void {
    $this->drupalGet('');
    $this->drupalGet('');

    // Test frontpage.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('');
    }, 'drupalCMSAnonymousFrontPage');

    $expected = [
      'QueryCount' => 0,
      'CacheGetCount' => 2,
      'CacheSetCount' => 0,
      'CacheTagLookupQueryCount' => 1,
      // If there are small changes in the below limits, e.g. under 5kb, the
      // ceiling can be raised without any investigation. However large increases
      // indicate a large library is newly loaded for anonymous users.
      'StylesheetCount' => 2,
      'StylesheetBytes' => 80000,
      'ScriptCount' => 1,
      'ScriptBytes' => 23000,
    ];
    $this->assertMetrics($expected, $performance_data);
    $this->assertSession()->elementExists('css', 'article.node');
  }

  /**
   * Log in with the editor role and visit the front page with a warm cache.
   */
  protected function doTestEditorFrontPage(): void {
    $editor = $this->drupalCreateUser();
    $editor->addRole('content_editor')->save();
    $this->drupalLogin($editor);
    // Warm various caches.
    $this->drupalGet('');
    // Allow one second for post response tasks to write caches.
    sleep(1);
    $this->drupalGet('');
    sleep(1);

    // Test frontpage.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('');
    }, 'drupalCMSEditorFrontPage');
    $assert_session = $this->assertSession();
    $assert_session->elementAttributeContains('named', ['link', 'Dashboard'], 'class', 'toolbar-button--icon--navigation-dashboard');
    $assert_session->elementExists('css', 'article.node');

    // The following queries are the only database queries executed for editors on the
    // front page.
    $queries = [
      'SELECT "session" FROM "sessions" WHERE "sid" = "SESSION_ID" LIMIT 0, 1',
      'SELECT * FROM "users_field_data" "u" WHERE "u"."uid" = "2" AND "u"."default_langcode" = 1',
      'SELECT "roles_target_id" FROM "user__roles" WHERE "entity_id" = "2"',
      'SELECT "base_table"."id" AS "id", "base_table"."path" AS "path", "base_table"."alias" AS "alias", "base_table"."langcode" AS "langcode" FROM "path_alias" "base_table" WHERE ("base_table"."status" = 1) AND ("base_table"."alias" LIKE "/node/2" ESCAPE \'\\\\\') AND ("base_table"."langcode" IN ("en", "und")) ORDER BY "base_table"."langcode" ASC, "base_table"."id" DESC',
      'SELECT rid FROM "redirect" WHERE hash IN ("NKzL8tFQHWuVsiKsKSy9LeHXQXJXBi02otuiixBL8TE", "hef6TjxChWEKH2Wao9m0dVOigdwgf67UkEGlfXcimoA") ORDER BY LENGTH(redirect_source__query) DESC',
      'SELECT "config"."name" AS "name" FROM "config" "config" WHERE ("collection" = "") AND ("name" LIKE "klaro.klaro_app.%" ESCAPE \'\\\\\') ORDER BY "collection" ASC, "name" ASC',
      'SELECT "session" FROM "sessions" WHERE "sid" = "SESSION_ID" LIMIT 0, 1',
      'SELECT * FROM "users_field_data" "u" WHERE "u"."uid" = "2" AND "u"."default_langcode" = 1',
      'SELECT "roles_target_id" FROM "user__roles" WHERE "entity_id" = "2"',
    ];

    // To avoid a test failure when a database query is removed, check only
    // that a new database query has not been added.
    $query_diff = array_diff($performance_data->getQueries(), $queries);
    $this->assertSame([], $query_diff);
    $this->assertLessThanOrEqual(9, $performance_data->getQueryCount());

    $expected = [
      'QueryCount' => 9,
      'CacheGetCount' => 70,
      'CacheSetCount' => 0,
      'CacheTagLookupQueryCount' => 9,
      // If there are small changes in the below limits, e.g. under 5kb, the
      // ceiling can be raised without any investigation. However large increases
      // indicate a large library is newly loaded for authenticated users.
      'StylesheetCount' => 3,
      'StylesheetBytes' => 218000,
      'ScriptCount' => 4,
      'ScriptBytes' => 244800,
    ];
    $this->assertMetrics($expected, $performance_data);
  }

}
