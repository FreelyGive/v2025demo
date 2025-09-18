<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Entity\Routing;

use Drupal\canvas\Entity\Page;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\PageTrait;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\Kernel\Traits\CanvasUiAssertionsTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group canvas
 */
final class CanvasHtmlRouteProviderTest extends KernelTestBase {

  use PageTrait;
  use RequestTrait;
  use UserCreationTrait;
  use CanvasUiAssertionsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'entity_test',
    ...self::PAGE_TEST_MODULES,
    'block',
    // Canvas's dependencies (modules providing field types + widgets).
    'datetime',
    'file',
    'image',
    'media',
    'options',
    'path',
    'link',
    'system',
    'user',
  ];

  protected function setUp(): void {
    parent::setUp();
    // Needed for date formats.
    $this->installConfig(['system']);
    $this->installPageEntitySchema();
  }

  public function testEditFormRoute(): void {
    $this->setUpCurrentUser([], [Page::EDIT_PERMISSION]);
    $page = Page::create([]);
    $page->save();
    $url = $page->toUrl('edit-form')->toString();
    $this->request(Request::create($url));
    $this->assertCanvasMount();
  }

}
