<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent;
use Drupal\user\UserInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Defines a functional test for API layout controller.
 *
 * @group canvas
 */
final class ApiLayoutControllerTest extends HttpApiTestBase {

  const UUID_IN_CONTENT_REGION = '/<!-- canvas-region-start-content -->.*(uid="%s").*<!-- canvas-region-end-content -->/';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Note: drafts are loaded in the Canvas UI, not on the live site.
   *
   * @see \Drupal\Tests\canvas\Functional\CanvasPageVariantTest
   */
  public function testWithDraftCodeComponent(): void {
    $account = $this->createUser([
      'administer url aliases',
      JavaScriptComponent::ADMIN_PERMISSION,
      'administer themes',
      PageRegion::ADMIN_PERMISSION,
      Page::EDIT_PERMISSION,
    ]);
    \assert($account instanceof UserInterface);
    $this->drupalLogin($account);

    $page = Page::create([
      'title' => 'Test page',
    ]);
    $page->save();
    assert($page instanceof Page);

    // Create the saved (published) javascript component.
    $saved_component_values = [
      'machineName' => 'hey_there',
      'name' => 'Hey there',
      'status' => TRUE,
      'props' => [
        'name' => [
          'type' => 'string',
          'title' => 'Name',
          'examples' => ['Garry'],
        ],
      ],
      'slots' => [],
      'js' => [
        'original' => 'console.log("Hey there")',
        'compiled' => 'console.log("Hey there")',
      ],
      'css' => [
        'original' => '.foo{color:red}',
        'compiled' => '.foo{color:red}',
      ],
      'dataDependencies' => [],
    ];
    $code_component = JavaScriptComponent::create($saved_component_values);
    $code_component->save();
    $saved_component_values['props']['voice'] = [
      'type' => 'string',
      'enum' => [
        'polite',
        'shouting',
        'toddler on a sugar high',
      ],
      'title' => 'Voice',
      'examples' => ['polite'],
    ];
    $saved_component_values['name'] = 'Here comes the';
    // But store an overridden version in auto-save (draft).
    /** @var \Drupal\canvas\AutoSave\AutoSaveManager $autoSave */
    $autoSave = $this->container->get(AutoSaveManager::class);
    // Auto-save entries should match the format sent by the client.
    $saved_component_values['sourceCodeJs'] = $saved_component_values['js']['original'];
    $saved_component_values['compiledJs'] = $saved_component_values['js']['compiled'];
    $saved_component_values['sourceCodeCss'] = $saved_component_values['css']['original'];
    $saved_component_values['compiledCss'] = $saved_component_values['css']['compiled'];
    // 'importedJsComponents' is a value sent by the client that is used to
    // determine Javascript Code component dependencies and is not saved
    // directly on the backend.
    // @see \Drupal\canvas\Entity\JavaScriptComponent::addJavaScriptComponentsDependencies().
    $saved_component_values['importedJsComponents'] = [];
    unset($saved_component_values['js'], $saved_component_values['css']);
    $code_component->updateFromClientSide($saved_component_values);
    $autoSave->saveEntity($code_component);

    // Load the test data from the layout controller.
    $content = $this->drupalGet('/canvas/api/v0/layout/canvas_page/1');
    $this->assertJson($content);
    $json = json_decode($content, TRUE, JSON_THROW_ON_ERROR);
    // These are allowed in GET response but not in POST/PATCH.
    unset($json['isNew'], $json['isPublished'], $json['html']);

    // Add the code component into the layout.
    $uuid = 'ccf36def-3f87-4b7d-bc20-8f8594274818';
    $component_id = JsComponent::componentIdFromJavascriptComponentId((string) $code_component->id());
    $component = Component::load($component_id);
    assert($component instanceof Component);
    $json['layout'][0]['components'][] = [
      'nodeType' => 'component',
      'uuid' => $uuid,
      'type' => $component_id . '@' . $component->getActiveVersion(),
      'slots' => [],
    ];
    $props = [
      'name' => 'Hot stepper',
      'voice' => 'shouting',
    ];
    $json['model'][$uuid] = [
      'resolved' => $props,
      'source' => [
        'name' => [
          'sourceType' => 'static:field_item:string',
          'expression' => 'ℹ︎string␟value',
        ],
        'voice' => [
          'sourceType' => 'static:field_item:list_string',
          'expression' => 'ℹ︎list_string␟value',
          'sourceTypeSettings' => [
            'storage' => [
              'allowed_values_function' => 'canvas_load_allowed_values_for_component_prop',
            ],
          ],
        ],
      ],
    ];
    $json += $this->getClientAutoSaves([$page]);
    $json['clientInstanceId'] = 'sample-client-instance-id';
    $original_request_json = $json;

    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
      RequestOptions::BODY => Json::encode($json),
    ];
    $request_options['headers']['X-CSRF-Token'] = $this->drupalGet('session/token');
    $response = $this->makeApiRequest('POST', Url::fromRoute('canvas.api.layout.post', [
      'entity_type' => Page::ENTITY_TYPE_ID,
      'entity' => $page->id(),
    ]), $request_options);

    $body = (string) $response->getBody();
    $json = \json_decode($body, TRUE, JSON_THROW_ON_ERROR);
    $crawler = new Crawler($json['html']);
    $element = $crawler->filter('canvas-island');
    self::assertCount(1, $element);
    self::assertEquals($uuid, $element->attr('uid'));
    // Validate element is in content region.
    $this->assertMatchesRegularExpression(sprintf(self::UUID_IN_CONTENT_REGION, $uuid), $element->ancestors()->html());

    // Should see the new (draft) props.
    self::assertJsonStringEqualsJsonString(Json::encode(\array_map(static fn(mixed $value): array => [
      'raw',
      $value,
    ], $props)), $element->attr('props') ?? '');
    // And the new component label.
    self::assertJsonStringEqualsJsonString(Json::encode([
      'name' => 'Here comes the',
      'value' => 'preact',
    ]), $element->attr('opts') ?? '');
    // And we should have our style tag.
    $links = \array_map(static fn (mixed $link) => \parse_url((string) $link, \PHP_URL_PATH), $crawler->filter('link[rel="stylesheet"]')->extract(['href']));
    $draft_css_url = Url::fromRoute('canvas.api.config.auto-save.get.css', [
      'canvas_config_entity_type_id' => JavaScriptComponent::ENTITY_TYPE_ID,
      'canvas_config_entity' => 'hey_there',
    ])->toString();
    self::assertContains($draft_css_url, $links);

    // Updating the auto-save should invalidate the config cache and cause the
    // new value to be used on a subsequent POST.
    $saved_component_values['name'] = 'Rodney';
    $code_component->updateFromClientSide($saved_component_values);
    $autoSave->saveEntity($code_component);

    $original_request_json['autoSaves'] = $json['autoSaves'];
    $request_options[RequestOptions::BODY] = Json::encode($original_request_json);
    $response = $this->makeApiRequest('POST', Url::fromRoute('canvas.api.layout.post', [
      'entity_type' => Page::ENTITY_TYPE_ID,
      'entity' => $page->id(),
    ]), $request_options);

    $body = (string) $response->getBody();
    $json = \json_decode($body, TRUE, JSON_THROW_ON_ERROR);
    $crawler = new Crawler($json['html']);

    $element = $crawler->filter('canvas-island');
    // Validate element is in content region.
    $this->assertMatchesRegularExpression(sprintf(self::UUID_IN_CONTENT_REGION, $uuid), $element->ancestors()->html());

    self::assertCount(1, $element);
    $updated_opts_json = Json::encode([
      'name' => 'Rodney',
      'value' => 'preact',
    ]);
    self::assertJsonStringEqualsJsonString($updated_opts_json, $element->attr('opts') ?? '');

    // Enable Drupal Canvas for all regions on the Stark theme.
    $this->drupalGet('/admin/appearance/settings/stark');
    $this->assertSession()->pageTextContains('Drupal Canvas');
    $this->assertSession()->fieldExists('use_canvas');
    $this->submitForm(['use_canvas' => TRUE], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $json = $original_request_json;
    // Move the component out of the Page content entity's component tree, into a PageRegion config entity.
    $json['layout'][1] = [
      'nodeType' => 'region',
      'id' => 'sidebar_first',
      'name' => 'Sidebar first',
      'components' => $json['layout'][0]['components'],
    ];
    $json['layout'][0]['components'] = [];
    unset($json['autoSaves']);
    $json += $this->getClientAutoSaves([$page]);
    $request_options[RequestOptions::BODY] = Json::encode($json);
    $response = $this->makeApiRequest('POST', Url::fromRoute('canvas.api.layout.post', [
      'entity_type' => Page::ENTITY_TYPE_ID,
      'entity' => $page->id(),
    ]), $request_options);

    // Ensure the auto-saved version is also used in the sidebar.
    $body = (string) $response->getBody();
    $json = \json_decode($body, TRUE, JSON_THROW_ON_ERROR);
    $crawler = new Crawler($json['html']);
    $sidebar_first_region = $crawler->filter('.layout-sidebar-first');
    self::assertCount(1, $sidebar_first_region);
    $element = $sidebar_first_region->filter('canvas-island');
    self::assertCount(1, $element);
    self::assertJsonStringEqualsJsonString($updated_opts_json, $element->attr('opts') ?? '');
  }

}
