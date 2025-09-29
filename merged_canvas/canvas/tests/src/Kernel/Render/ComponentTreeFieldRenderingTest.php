<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Render;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests rendering of live and preview component tree is consistent.
 *
 * @group canvas
 */
final class ComponentTreeFieldRenderingTest extends KernelTestBase {

  use RequestTrait;
  use UserCreationTrait;
  use GenerateComponentConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'big_pipe',
    // Canvas's dependencies (modules providing field types + widgets).
    'ckeditor5',
    'editor',
    'field',
    'file',
    'filter',
    'image',
    'link',
    'path_alias',
    'path',
    'media',
    'options',
    'text',
    'user',
    'system',
    'canvas_test_sdc',
  ];

  protected function setUp(): void {
    parent::setUp();
    // Drupal Canvas configuration (creates the global AssetLibrary).
    $this->installConfig('canvas');
    $this->installConfig('system');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('canvas_page');
    $this->installConfig('canvas');

    $themes = ['olivero', 'stark', 'claro'];
    \Drupal::service('theme_installer')->install($themes);
    $this->config('system.theme')->set('default', 'olivero')->save();

    $this->generateComponentConfig();
  }

  public function testRenderingComponent(): void {
    $permissions = [
      Page::EDIT_PERMISSION,
    ];
    $account = $this->createUser($permissions);
    self::assertInstanceOf(AccountInterface::class, $account);
    $this->setCurrentUser($account);

    $page = Page::create([
      'title' => 'Test page',
      'description' => 'This is a test page.',
      'path' => ['alias' => "/page-1"],
      'status' => TRUE,
      'components' => [
        [
          'uuid' => CanvasTestSetup::UUID_COMPONENT_SDC,
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'inputs' => [
            'heading' => [
              'sourceType' => 'static:field_item:string',
              'value' => 'Welcome to the site!',
              'expression' => 'ℹ︎string␟value',
            ],
          ],
        ],
      ],
    ]);
    $page->save();

    $live_url = Url::fromRoute('entity.canvas_page.canonical', [
      'canvas_page' => $page->id(),
    ]);
    $preview_url = Url::fromRoute('canvas.api.layout.get', [
      'entity' => $page->id(),
      'entity_type' => Page::ENTITY_TYPE_ID,
    ]);

    $request = Request::create($live_url->toString());
    $response = $this->request($request);
    assert($response instanceof HtmlResponse);
    $this->assertCount(0, $this->cssSelect('.field--type-component-tree'));
    $this->assertText('Welcome to the site!');

    $request = Request::create($preview_url->toString());
    // As in this case we get a JsonResponse, we need to set the contents.
    $response = $this->request($request);
    assert($response instanceof JsonResponse);
    $contents = $this->decodeResponse($response);
    assert(\array_key_exists('html', $contents));
    $this->setRawContent($contents['html']);
    $this->assertCount(0, $this->cssSelect('.field--type-component-tree'));
    $this->assertText('Welcome to the site!');
  }

}
