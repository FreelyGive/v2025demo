<?php

declare(strict_types=1);

// cspell:ignore magnifique

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Url;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\ApiRequestTrait;
use Drupal\Tests\content_translation\Traits\ContentTranslationTestTrait;

/**
 * @todo Add test coverage for dynamic prop sources used in the content type
 *   templates in https://drupal.org/i/3455629. This will most likely require
 *   adding back `canvas_entity_prepare_view()` which was removed in
 *   https://www.drupal.org/i/3481720.
 * @see https://www.drupal.org/project/canvas/issues/3455629#comment-15831060
 * @group canvas
 */
class TranslationTest extends FunctionalTestBase {

  use ApiRequestTrait;
  use ContentTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'canvas_test_sdc',
    'content_translation',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // In 11.2 and above we install modules in groups, which means this module
    // cannot be installed in the same group as canvas
    \Drupal::service(ModuleInstallerInterface::class)->install(['canvas_test_config_node_article']);

    // Display the `field_canvas_test` field.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'article')
      ->setComponent('field_canvas_test', [
        'label' => 'hidden',
        'type' => 'canvas_naive_render_sdc_tree',
      ])
      ->save();

    $page = $this->getSession()->getPage();
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/config/regional/language');
    $this->clickLink('Add language');
    $page->selectFieldOption('predefined_langcode', 'fr');
    $page->pressButton('Add language');
    $this->assertSession()->pageTextContains('The language French has been created and can now be used.');
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();
    $this->enableContentTranslation('node', 'article');
  }

  /**
   * Data provider for testTranslation().
   *
   * @return array<array{0: array, 1: bool}>
   */
  public static function translationDataProvider(): array {
    return [
      // In the symmetric case, the 'tree' property is not translatable. This
      // means every translation has the same components but can have different
      // properties.
      'symmetric' => [['inputs'], TRUE],
      // In the asymmetric case, both 'tree' and 'inputs' properties are
      // translatable. This means every translation can have different components
      // and properties for those components. There no connection at all between
      // the components in the different translations.
      'asymmetric' => [['tree', 'inputs'], FALSE],
      // This case tests when the field is not translatable, but it is used on
      // an entity that has translations. In this case, the components and their
      // properties are shared between the translations.
      'not translatable' => [[], TRUE],
    ];
  }

  /**
   * Tests translating the Canvas field.
   *
   * @param array<string> $translatable_properties
   *   The properties on the Canvas field that should be
   *   translatable.
   * @param bool $expect_component_removed_on_translation
   *   Whether the last component in Canvas tree is expected to be removed from the
   *   translation. The component is always removed from the default
   *   translation.
   *
   * @dataProvider translationDataProvider
   */
  public function testTranslation(array $translatable_properties, bool $expect_component_removed_on_translation): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $field_is_translatable = !empty($translatable_properties);

    $this->drupalGet('admin/config/regional/content-language');
    if ($field_is_translatable) {
      $page->checkField('settings[node][article][fields][field_canvas_test]');
      foreach (['tree', 'inputs'] as $field_property) {
        in_array($field_property, $translatable_properties)
          ? $page->checkField("settings[node][article][columns][field_canvas_test][$field_property]")
          : $page->uncheckField("settings[node][article][columns][field_canvas_test][$field_property]");
      }
    }
    else {
      $page->uncheckField('settings[node][article][fields][field_canvas_test]');
    }

    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('Settings successfully updated.');
    $original_node = $this->createCanvasNodeWithTranslation();
    $this->assertTrue($original_node->isDefaultTranslation());
    $translated_node = $original_node->getTranslation('fr');
    $this->assertSame('The French title', (string) $translated_node->getTitle());

    $this->drupalGet($original_node->toUrl());
    $hero_component = $assert_session->elementExists('css', '[data-component-id="canvas_test_sdc:my-hero"]');

    // Confirm the translated property is not on the page anywhere.
    $assert_session->pageTextNotContains('bonjour');
    // Confirm the first hero component does not use the translated properties
    // because it uses a StaticPropSource.
    $this->assertSame('hello, new world!', $hero_component->find('css', 'h1')?->getText());
    // Confirm the heading has been removed from display. This was changed on
    // the default translation.
    $assert_session->elementsCount('css', '[data-component-id="canvas_test_sdc:heading"]', 0);

    $this->drupalGet($translated_node->toUrl());
    $assert_session->elementTextEquals('css', '#block-stark-page-title h1', 'The French title');

    $hero_component = $assert_session->elementExists('css', '[data-component-id="canvas_test_sdc:my-hero"]');
    if ($field_is_translatable) {
      // If the field is translatable updating inputs in the default translation
      // should not have updated the French translation.
      $this->assertSame('bonjour, monde!', $hero_component->find('css', 'h1')?->getText());
      $assert_session->pageTextNotContains('hello, new world!');
    }
    else {
      // If the field is not translatable updating inputs in the default translation
      // should have also updated the French translation.
      $assert_session->pageTextNotContains('bonjour');
      $this->assertSame('hello, new world!', $hero_component->find('css', 'h1')?->getText());
    }

    // Confirm the heading component has been removed or not based the test case
    // expectation.
    $assert_session->elementsCount(
      'css',
      '[data-component-id="canvas_test_sdc:heading"]',
      $expect_component_removed_on_translation ? 0 : 1
    );

    // Verify the `name` for a single component instance is only present on the
    // original translation — both in the server-side storage, and in the
    // information provided to the client for the UI.
    $get_name = function (NodeInterface $node): ?string {
      $component_tree = $node->get('field_canvas_test');
      assert($component_tree instanceof ComponentTreeItemList);
      return $component_tree->getComponentTreeItemByUuid('208452de-10d6-4fb8-89a1-10e340b3744c')?->getLabel();
    };
    // If the field is not translatable updating inputs in the French
    // translation should have also updated the default translation.
    $expected_original_label = $field_is_translatable ? 'Starring … Drupal as the hero! 🤩' : "Drupal, c'est magnifique !";
    self::assertSame($expected_original_label, $get_name($original_node));
    self::assertSame("Drupal, c'est magnifique !", $get_name($translated_node));
    $get_name_in_api_response = function (string $root_relative_url): ?string {
      $response = $this->makeApiRequest('GET', Url::fromUri("base:$root_relative_url"), []);
      self::assertSame(200, $response->getStatusCode());
      $layout = json_decode((string) $response->getBody(), TRUE)['layout'];
      return $layout[0]['components'][0]['slots'][0]['components'][0]['name'];
    };
    self::assertSame($expected_original_label, $get_name_in_api_response('/canvas/api/v0/layout/node/1'));
    self::assertSame("Drupal, c'est magnifique !", $get_name_in_api_response('/fr/canvas/api/v0/layout/node/1'));
  }

  /**
   * Creates an article node with a translation.
   *
   * @return \Drupal\node\Entity\Node
   *   The default translation of the node.
   */
  protected function createCanvasNodeWithTranslation(): Node {
    $node = $this->createTestNode();
    $list = $node->get('field_canvas_test');
    assert($list instanceof ComponentTreeItemList);
    // There are five items in the default values for this field.
    self::assertEquals(5, $list->count());

    // Create a translation from the original English node.
    $translation = $node->addTranslation('fr');
    $this->assertInstanceOf(Node::class, $translation);
    $this->container->get('content_translation.manager')->getTranslationMetadata($translation)->setSource($node->language()->getId());
    // @phpstan-ignore-next-line
    $translation->title = 'The French title';
    $translation->save();
    $translation = $node->getTranslation('fr');
    $updated_item = $list->getComponentTreeItemByUuid('208452de-10d6-4fb8-89a1-10e340b3744c');
    assert($updated_item instanceof ComponentTreeItem);
    $updated_item_inputs = $updated_item->getInputs();

    // In both the Symmetric and Asymmetric translation cases, the `inputs` and
    // `label` field properties are translatable and this should only change the
    // translation.
    $french_inputs = $updated_item_inputs;
    $french_inputs['heading'] = 'bonjour, monde!';
    $french_list = $translation->get('field_canvas_test');
    assert($french_list instanceof ComponentTreeItemList);
    $french_item = $french_list->getComponentTreeItemByUuid('208452de-10d6-4fb8-89a1-10e340b3744c');
    assert($french_item instanceof ComponentTreeItem);
    $french_item->setInput($french_inputs)
      ->setLabel("Drupal, c'est magnifique !");
    $translation->save();

    // Update the English version.
    $updated_item_inputs['heading'] = 'hello, new world!';
    // In both the Symmetric and Asymmetric cases, the `inputs` property is
    // translatable and this should only change the original. If the field is
    // not translatable, this should change both the original and the
    // translation.
    $updated_item->setInput($updated_item_inputs);
    // Remove the heading from the tree.
    // In the asymmetric case, where 'tree' is translatable, this should only
    // affect the untranslated node.
    // In the symmetric case, where 'tree' is not translatable, this should
    // change both the original and the translation.
    $delta_to_remove = $list->getComponentTreeDeltaByUuid('e660e407-0901-4639-9726-9f99bc250c4c');
    \assert(\is_int($delta_to_remove));
    $list->removeItem($delta_to_remove);
    $node->save();
    return $node;
  }

}
