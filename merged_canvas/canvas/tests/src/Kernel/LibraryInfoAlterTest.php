<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\canvas\Controller\CanvasController;
use Drupal\canvas\Entity\Page;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * @covers \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::transformsLibraryInfoAlter()
 * @group canvas
 */
final class LibraryInfoAlterTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'system',
    'canvas_test_page',
    'media',
    'user',
    'image',
    'file',
    'path_alias',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
    $this->installConfig(['system']);

  }

  /**
   * Tests that libraries with canvas.transform prefix are dynamically added.
   */
  public function testTransformMounting(): void {
    $this->setUpCurrentUser([], [Page::CREATE_PERMISSION]);
    $page = Page::create([
      'title' => 'Test page',
      'description' => 'This is a test page.',
      'components' => [],
    ]);
    $page->save();
    $context = new RenderContext();
    $renderer = $this->container->get(RendererInterface::class);
    \assert($renderer instanceof RendererInterface);
    $out = $renderer->executeInRenderContext($context, fn () => $this->container->get(CanvasController::class)(Page::ENTITY_TYPE_ID, $page));
    \assert($out instanceof HtmlResponse);
    $attachments = $out->getAttachments();
    self::assertEquals([
      'canvas/canvas.transform.mainProperty',
      'canvas/canvas.transform.firstRecord',
      'canvas/canvas.transform.dateTime',
      'canvas/canvas.transform.mediaSelection',
      'canvas/canvas.transform.cast',
      'canvas/canvas.transform.link',
      'canvas_test_page/canvas.transform.diaclone',
    ], array_values(array_filter(
      $attachments['library'],
      fn (string $lib) => str_contains($lib, '/canvas.transform.'),
    )));
  }

}
