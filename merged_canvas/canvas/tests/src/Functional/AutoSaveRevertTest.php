<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Page;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests that auto-saved changes are deleted when reverting a page revision.
 *
 * @group canvas
 */
class AutoSaveRevertTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['canvas', 'user', 'node'];

  /**
   * Tests access to page revisions.
   */
  public function testRevisionAccess(): void {
    $user = $this->drupalCreateUser(['edit canvas_page']);
    assert($user instanceof AccountInterface);
    $this->drupalLogin($user);

    $page = Page::create(['title' => 'Test Page']);
    $page->save();
    $original_vid = $page->getRevisionId();

    $page->setNewRevision(TRUE);
    $page->set('title', 'Test Page - Revision 2');
    $page->save();

    $this->drupalGet('/page/' . $page->id() . '/revisions/' . $original_vid . '/revert');
    $this->assertSession()->pageTextNotContains('This page has unpublished changed in Drupal Canvas.');

    $autoSaveManager = $this->container->get(AutoSaveManager::class);
    $page->set('title', 'Test Page - Auto-saved revision');
    $autoSaveManager->saveEntity($page);

    $this->drupalGet('/page/' . $page->id() . '/revisions/' . $original_vid . '/revert');
    $this->assertSession()->pageTextContains('This page has unpublished changed in Drupal Canvas.');
    $this->submitForm([], 'Revert');

    $this->assertSession()->addressEquals('page/1/revisions');
    self::assertTrue($autoSaveManager->getAutoSaveEntity($page)->isEmpty());

    $this->drupalGet('/page/' . $page->id() . '/revisions/' . $original_vid . '/revert');
    $this->assertSession()->pageTextNotContains('This page has unpublished changed in Drupal Canvas.');
  }

  /**
   * Tests access to page revisions.
   */
  public function testRevertingArticle(): void {
    $this->createContentType(['type' => 'article']);
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_component_tree',
      'entity_type' => 'node',
      'type' => 'component_tree',
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
    ])->save();

    $user = $this->drupalCreateUser([
      'create article content',
      'edit any article content',
      'view article revisions',
      'revert article revisions',
    ]);
    assert($user instanceof AccountInterface);
    $this->drupalLogin($user);

    $node = Node::create(['title' => 'Test Article', 'type' => 'article']);
    $node->save();
    $original_vid = $node->getRevisionId();

    $node->setNewRevision(TRUE);
    $node->set('title', 'Test Article - Revision 2');
    $node->save();

    $this->drupalGet('/node/' . $node->id() . '/revisions/' . $original_vid . '/revert');
    $this->assertSession()->pageTextNotContains('This page has unpublished changed in Drupal Canvas.');

    $autoSaveManager = $this->container->get(AutoSaveManager::class);
    $node->set('title', 'Test Article - Auto-saved revision');
    $autoSaveManager->saveEntity($node);

    $this->drupalGet('/node/' . $node->id() . '/revisions/' . $original_vid . '/revert');
    $this->assertSession()->pageTextContains('This page has unpublished changed in Drupal Canvas.');
    $this->submitForm([], 'Revert');

    $this->assertSession()->addressEquals('node/1/revisions');
    self::assertTrue($autoSaveManager->getAutoSaveEntity($node)->isEmpty());

    $this->drupalGet('/node/' . $node->id() . '/revisions/' . $original_vid . '/revert');
    $this->assertSession()->pageTextNotContains('This page has unpublished changed in Drupal Canvas.');
  }

}
