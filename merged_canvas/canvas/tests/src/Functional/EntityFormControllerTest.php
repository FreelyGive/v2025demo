<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\user\Entity\User;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @coversDefaultClass \Drupal\canvas\Controller\EntityFormController
 * @group canvas
 */
class EntityFormControllerTest extends FunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['canvas'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  protected function setUp(): void {
    parent::setUp();
    $this->createComponentTreeField('node', 'article', 'field_component_tree');
  }

  /**
   * @covers ::form
   * @covers \Drupal\canvas\Hook\ContentTemplateHooks::entityFormDisplayAlter()
   */
  public function testForm(): void {
    $assert = $this->assertSession();
    $this->createTestNode();

    $this->assertFormResponse('canvas/api/v0/form/content-entity/node/1/default', TRUE);
    $this->assertFormResponse('canvas/api/v0/form/content-entity/node/1', TRUE);

    $new_form_mode_path = 'canvas/api/v0/form/content-entity/node/1/mode2';
    // Try to retrieve the form using the new form mode before it is created.
    $this->drupalGet($new_form_mode_path);
    $assert->statusCodeEquals(500);
    $assert->responseHeaderEquals('Content-Type', 'application/json');
    $json = json_decode($this->getSession()->getPage()->getContent());
    $this->assertSame('The "mode2" form display was not found', $json->message);
    // We are logged in as user 1 so we should see the trace.
    $this->assertObjectHasProperty('trace', $json);

    $user = $this->drupalCreateUser(['administer display modes', 'administer node form display', 'edit any article content']);
    $this->assertInstanceOf(User::class, $user);
    $this->drupalLogin($user);
    $this->drupalGet('admin/structure/display-modes/form/add/node');
    $assert->statusCodeEquals(200);

    $edit = [
      'id' => 'mode2',
      'label' => 'Mode 2',
      'bundles_by_entity[article]' => 'article',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains("Saved the Mode 2 form mode.");

    // The menu element should not appear in the 'mode2' form mode.
    $this->assertFormResponse($new_form_mode_path, FALSE);
  }

  private function assertFormResponse(string $path, bool $expected_menu_element): void {
    $response = $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $parsed_response = json_decode($response, TRUE);
    $html = $parsed_response['html'];

    // Ensure the `status` field has been removed.
    // @see \canvas_entity_form_display_alter()
    $this->assertStringNotContainsString('edit-status-value', $html);

    $crawler = new Crawler($html);
    self::assertCount(1, $crawler->filter('template[data-hyperscriptify]'));
    $form = $crawler->filter('drupal-form');
    self::assertCount(1, $form);

    $attributes = \json_decode($form->attr('attributes') ?? '{}', TRUE, flags: JSON_THROW_ON_ERROR);
    self::assertEquals(['node-article-form', 'node-form'], $attributes['class']);
    self::assertEquals('node-article-form', $attributes['data-drupal-selector']);
    self::assertEquals('multipart/form-data', $attributes['enctype']);

    self::assertGreaterThanOrEqual($expected_menu_element ? 1 : 0, $crawler->filter('div[data-drupal-selector="edit-menu"] drupal-input[attributes*="edit-menu-title"]')->count());
  }

}
