<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional\Config;

use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\canvas\Functional\FunctionalTestBase;

/**
 * @covers \Drupal\canvas\Entity\ContentTemplate::onDependencyRemoval
 *
 * @group canvas
 */
final class ContentTemplateDependencyTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'canvas',
    'field_ui',
    'link',
    'node',
    'canvas_test_sdc',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up a content type with two simple fields.
    $this->drupalCreateContentType(['type' => 'article']);
    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'node',
      'type' => 'string',
      'field_name' => 'field_slogan',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Slogan',
    ])->save();

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'node',
      'type' => 'string',
      'field_name' => 'field_motto',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Motto',
    ])->save();

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'node',
      'type' => 'link',
      'field_name' => 'field_more_info',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'More info',
    ])->save();

    // Opt the content type into Canvas rendering by adding a component tree field.
    $field_storage = FieldStorageConfig::create([
      'type' => 'component_tree',
      'entity_type' => 'node',
      'field_name' => 'field_canvas_tree',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
    ])->save();

    // Create a simple template that uses string fields to populate component
    // instances.
    $template = ContentTemplate::create([
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'article',
      'content_entity_type_view_mode' => 'full',
      'component_tree' => [
        [
          'uuid' => '02b766f7-0edc-4359-98bb-3f489e878330',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'inputs' => [
            'heading' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_motto␞␟value',
            ],
          ],
        ],
        [
          'uuid' => '4ca2cb2e-f9ac-40e5-83be-0f2d08b455b3',
          'component_id' => 'sdc.canvas_test_sdc.my-cta',
          'inputs' => [
            'text' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_slogan␞␟value',
            ],
            'href' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_more_info␞␟uri',
            ],
            'target' => [
              'sourceType' => 'static:field_item:string',
              'value' => '_blank',
              'expression' => 'ℹ︎string␟value',
            ],
          ],
        ],
      ],
    ]);
    $template->setStatus(TRUE)->save();
    // All fields should be hard dependencies of the template.
    $dependencies = $template->getDependencies();
    $this->assertContains('field.field.node.article.field_slogan', $dependencies['config']);
    $this->assertContains('field.field.node.article.field_motto', $dependencies['config']);
    $this->assertContains('field.field.node.article.field_more_info', $dependencies['config']);
  }

  public function testRemoveFieldUsedByTemplate(): void {
    // Create an article node and confirm that Canvas is rendering it.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'field_slogan' => 'My slogan',
      'field_motto' => 'My important motto',
      'field_more_info' => 'https://example.com',
    ]);
    $this->drupalGet($node->toUrl());
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('My slogan');
    // "Press" is the first example value of the my-cta SDC's `text` prop.
    // @see core/modules/system/tests/modules/sdc_test/components/my-cta/my-cta.component.yml
    $assert_session->pageTextNotContains('Press');
    $assert_session->pageTextContains('My important motto');
    $assert_session->linkByHrefExists('https://example.com');

    // Log in with permission to administer fields, and go delete one of the
    // fields in use by the template.
    $account = $this->drupalCreateUser(['administer node fields']);
    assert($account instanceof AccountInterface);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/structure/types/manage/article/fields/node.article.field_slogan/delete');
    // The template should be among the things being updated, but nothing should
    // be getting deleted.
    $assert_session->pageTextContains('The listed configuration will be updated.');
    $assert_session->pageTextContains('article content items — Full content view');
    $assert_session->pageTextNotContains('The listed configuration will be deleted.');
    $this->getSession()->getPage()->pressButton('Delete');
    $assert_session->statusMessageContains('The field Slogan has been deleted from the article content type.');

    // Revisit the node to ensure it still renders. The missing input should
    // be replaced with an example value.
    $this->drupalGet($node->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextNotContains('My slogan');
    $assert_session->pageTextContains('Press');
    $assert_session->pageTextContains('My important motto');
    $assert_session->linkByHrefExists('https://example.com');
    // There shouldn't be any error message (which we would see if any props
    // were invalid or broken).
    $assert_session->pageTextNotContains('Oops, something went wrong! Site admins have been notified.');

    // Ensure that the missing input was actually replaced by a static prop
    // source.
    $tree = ContentTemplate::load('node.article.full')?->getComponentTree();
    $item = $tree?->get(1);
    \assert($item instanceof ComponentTreeItem);
    $input = $item->getInputs();
    // The stored value is the default specified in the component's metadata.
    // @see core/modules/system/tests/modules/sdc_test/components/my-cta/my-cta.component.yml
    $this->assertSame('Press', $input['text'] ?? NULL);
  }

  public function testRemoveFieldTypeProviderModule(): void {
    $template = ContentTemplate::load('node.article.full');
    assert($template instanceof ContentTemplate);
    $tree = $template->get('component_tree');
    $tree[1]['inputs']['text'] = [
      'sourceType' => 'static:field_item:shape',
      'value' => 'Trapezoid',
      'expression' => 'ℹ︎shape␟shape',
    ];
    $template->setComponentTree($tree)->save();
    // The template should now depend on entity_test, since it's using a field
    // type that it provides.
    $dependencies = $template->getDependencies();
    $this->assertContains('entity_test', $dependencies['module']);

    // Create an article node and confirm that Canvas is rendering it.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'field_motto' => 'My important motto',
      'field_more_info' => 'https://example.com',
    ]);
    $this->drupalGet($node->toUrl());
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Trapezoid');
    $assert_session->pageTextContains('My important motto');
    $assert_session->linkByHrefExists('https://example.com');

    // Log in with permission to administer modules and uninstall entity_test,
    // which provides the `shape` field type.
    $account = $this->drupalCreateUser(['administer modules']);
    assert($account instanceof AccountInterface);
    $this->drupalLogin($account);
    $this->drupalGet('/admin/modules/uninstall');
    $assert_session->elementAttributeNotExists('named', ['field', 'Entity CRUD test module'], 'disabled');
    $page = $this->getSession()->getPage();
    $page->checkField('Entity CRUD test module');
    $page->pressButton('Uninstall');
    $assert_session->responseContains('Confirm uninstall');
    // A lot of configuration will be deleted, but the content template should
    // not be among those things.
    $assert_session->pageTextContains('The listed configuration will be deleted.');
    $assert_session->elementTextNotContains('css', '#edit-entity-deletes', 'article content items — Full content view');
    $page->pressButton('Uninstall');
    $assert_session->statusMessageContains('The selected modules have been uninstalled.');

    \Drupal::entityTypeManager()
      ->getStorage(ContentTemplate::ENTITY_TYPE_ID)
      ->resetCache();
    $template = ContentTemplate::load('node.article.full');
    $this->assertInstanceOf(ContentTemplate::class, $template);

    // Ensure that the dependency has been removed from the template.
    $dependencies = $template->getDependencies();
    $this->assertNotContains('entity_test', $dependencies['module']);

    // The prop that used the removed field type as its static prop source
    // should have been replaced with a static prop source that matches the
    // SDC's example value.
    // @see core/modules/system/tests/modules/sdc_test/components/my-cta/my-cta.component.yml
    $tree = $template->getComponentTree();
    $item = $tree->get(1);
    \assert($item instanceof ComponentTreeItem);
    $input = $item->getInputs();
    // The stored value is the default specified in the component's metadata.
    $this->assertSame('Press', $input['text'] ?? NULL);

    $this->drupalGet($node->toUrl());
    $assert_session->pageTextContains('Press');
    $assert_session->pageTextContains('My important motto');
    $assert_session->linkByHrefExists('https://example.com');
  }

}
