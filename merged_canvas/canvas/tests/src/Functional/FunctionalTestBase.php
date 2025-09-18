<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\canvas\Traits\CanvasFieldCreationTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Base class for functional tests of Canvas, ensures OpenAPI validation is active.
 *
 * Provides common setup and helper methods for functional tests.
 *
 * @group canvas
 */
abstract class FunctionalTestBase extends BrowserTestBase {

  use TestFileCreationTrait;
  use CanvasFieldCreationTrait;

  protected function setUp(): void {
    parent::setUp();
    $config = $this->container->get(ConfigFactoryInterface::class)->getEditable('system.performance');
    $config->set('js.preprocess', TRUE);
    $config->set('css.preprocess', TRUE);
    $config->save();
    if ($this->container->get('module_handler')->moduleExists('canvas')) {
      $response_validator = $this->container->get('canvas.openapi.http_response_validator.subscriber');
      $request_validator = $this->container->get('canvas.openapi.http_request_validator.subscriber');
      if (!($request_validator->isValidationEnabled() && $response_validator->isValidationEnabled())) {
        $this->fail('OpenAPI validation must be enabled to run functional tests. See the CONTRIBUTING.md file.');
      }
    }
  }

  protected function createTestNode(): Node {
    $nodes = $this->container->get('entity_type.manager')->getStorage('node')->loadMultiple();
    $expected_nid = count($nodes) + 1;
    $this->assertNull(Node::load($expected_nid));
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // The `thumbnail` image style already exists.
    $this->assertInstanceOf(ImageStyle::class, ImageStyle::load('thumbnail'));

    // Navigate to `/node/add/article` and press `Save`, do nothing else.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('node/add/article');
    $assert_session->statusCodeEquals(200);
    $page->pressButton('Save');
    $this->assertStringEndsWith('node/add/article', $this->getSession()->getCurrentUrl());
    // @todo For some reason, specifying `type: 'error'` fails: the expected HTML structure is different?! 🤯
    $this->assertSession()->statusMessageContains('Title field is required.');

    // One entity fields is required: `Title`. Fill it, press `Save`.
    $page->fillField('title[0][value]', 'The first entity using Canvas!');
    $page->pressButton('Save');

    // Success!
    $this->assertStringEndsWith("node/$expected_nid", $this->getSession()->getCurrentUrl());

    $node = Node::load($expected_nid);
    // @phpstan-ignore-next-line
    $this->assertInstanceOf(Node::class, $node);
    return $node;
  }

}
