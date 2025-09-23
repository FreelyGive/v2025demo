<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\canvas\Traits\OpenApiSpecTrait;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group canvas
 */
final class ApiUiContentTemplateControllersTest extends HttpApiTestBase {

  use GenerateComponentConfigTrait;
  use OpenApiSpecTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'node',
    'canvas_test_sdc',
    'canvas_test_code_components',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected readonly UserInterface $limitedPermissionsUser;

  protected function setUp(): void {
    parent::setUp();
    $this->generateComponentConfig();
    $this->createContentType(['type' => 'article', 'name' => 'Article']);

    // Required, single-cardinality image field.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_silly_image',
      'type' => 'image',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_silly_image',
      'bundle' => 'article',
      'required' => TRUE,
    ])->save();

    // Required, multiple-cardinality image field.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_screenshots',
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_screenshots',
      'bundle' => 'article',
      'required' => TRUE,
    ])->save();

    // Optional, single-cardinality user profile picture field.
    FieldStorageConfig::create([
      'entity_type' => 'user',
      'field_name' => 'user_picture',
      'type' => 'image',
      'translatable' => FALSE,
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'label' => 'User Picture',
      'description' => '',
      'field_name' => 'user_picture',
      'entity_type' => 'user',
      'bundle' => 'user',
      'required' => FALSE,
    ])->save();

    $account = $this->createUser([
      ContentTemplate::ADMIN_PERMISSION,
      'edit any article content',
    ]);
    \assert($account instanceof UserInterface);
    $this->drupalLogin($account);

    $user2 = $this->createUser(['view media']);
    assert($user2 instanceof UserInterface);
    $this->limitedPermissionsUser = $user2;
  }

  /**
   * @dataProvider providerSuggestStructuredDataForPropShapes
   * @see \Drupal\Tests\canvas\Kernel\FieldForComponentSuggesterTest
   */
  public function testSuggestStructuredDataForPropShapes(string $component_config_entity_id, string $content_entity_type_id, string $bundle, array $expected): void {
    $json = $this->assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri("base:/canvas/api/v0/ui/content_template/suggestions/structured-data-for-prop_shapes/$content_entity_type_id/$bundle/$component_config_entity_id"),
      request_options: [],
      expected_status: Response::HTTP_OK,
      expected_cache_contexts: NULL,
      expected_cache_tags: NULL,
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'UNCACHEABLE (no cacheability)',
    );
    $this->assertSame($expected, $json);
  }

  public static function providerSuggestStructuredDataForPropShapes(): \Generator {
    $choice_article_title = [
      'label' => "This Article's Title",
      'source' => ['sourceType' => 'dynamic', 'expression' => 'ℹ︎␜entity:node:article␝title␞␟value'],
    ];
    $choice_article_image = [
      'label' => "Subset of this Article's field_silly_image: src_with_alternate_widths, alt, width, height (4 of 7 props — absent: entity, title, srcset_candidate_uri_template)",
      'source' => ['sourceType' => 'dynamic', 'expression' => 'ℹ︎␜entity:node:article␝field_silly_image␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}'],
    ];
    $hash_for_choice = fn (array $choice) =>  \hash('xxh64', $choice['source']['expression']);

    yield 'a simple primitive example (sdc.canvas_test_sdc.heading, entity:node:article)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.heading',
      'content_entity_type_id' => 'node',
      'bundle' => 'article',
      'expected' => [
        'text' => [
          $hash_for_choice($choice_article_title) => $choice_article_title,
        ],
        'style' => [],
        'element' => [],
      ],
    ];
    yield 'a simple primitive example (sdc.canvas_test_sdc.heading, entity:user:user)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.heading',
      'content_entity_type_id' => 'user',
      'bundle' => 'user',
      'expected' => [
        'text' => [],
        'style' => [],
        'element' => [],
      ],
    ];

    yield 'a propless example (sdc.canvas_test_sdc.druplicon, entity:node:article)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.druplicon',
      'content_entity_type_id' => 'node',
      'bundle' => 'article',
      'expected' => [],
    ];
    yield 'a propless example (sdc.canvas_test_sdc.druplicon, entity:user:user)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.druplicon',
      'content_entity_type_id' => 'user',
      'bundle' => 'user',
      'expected' => [],
    ];

    yield 'a simple object example (sdc.canvas_test_sdc.image-required-with-example, entity:node:article)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.image-required-with-example',
      'content_entity_type_id' => 'node',
      'bundle' => 'article',
      'expected' => [
        'image' => [
          $hash_for_choice($choice_article_image) => $choice_article_image,
        ],
      ],
    ];
    yield 'an OPTIONAL simple object example (sdc.canvas_test_sdc.image-optional-with-example, entity:node:article)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.image-optional-with-example',
      'content_entity_type_id' => 'node',
      'bundle' => 'article',
      'expected' => [
        'image' => [
          $hash_for_choice($choice_article_image) => $choice_article_image,
        ],
      ],
    ];
    yield 'a simple object example (sdc.canvas_test_sdc.image-required-with-example, entity:user:user)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.image-required-with-example',
      'content_entity_type_id' => 'user',
      'bundle' => 'user',
      'expected' => [
        'image' => [],
      ],
    ];
    yield 'an OPTIONAL simple object example (sdc.canvas_test_sdc.image-optional-with-example, entity:user:user)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.image-optional-with-example',
      'content_entity_type_id' => 'user',
      'bundle' => 'user',
      'expected' => [
        'image' => [
          // @todo This SHOULD find the `user_picture` field, fix in https://www.drupal.org/project/canvas/issues/3541361
        ],
      ],
    ];

    yield 'an array of object values example (sdc.canvas_test_sdc.image-gallery, entity:node:article)' => [
      'component_config_entity_id' => 'sdc.canvas_test_sdc.image-gallery',
      'content_entity_type_id' => 'node',
      'bundle' => 'article',
      'expected' => [
        'caption' => [
          '82ec95693bc89080' => [
            'label' => "Subset of this Article's field_silly_image: alt (1 of 7 props — absent: entity, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_silly_image␞␟alt',
            ],
          ],
          '1409e675864fd2e6' => [
            'label' => "Subset of this Article's field_silly_image: title (1 of 7 props — absent: entity, alt, width, height, srcset_candidate_uri_template, src_with_alternate_widths)",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_silly_image␞␟title',
            ],
          ],
          '7ca10058b43f4d0f' => [
            'label' => "This Article's Revision log message",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝revision_log␞␟value',
            ],
          ],
          $hash_for_choice($choice_article_title) => $choice_article_title,
        ],
        'images' => [
          '441f35fe6e2feefd' => [
            'label' => "Subset of this Article's field_screenshots: src_with_alternate_widths, alt, width, height (4 of 7 props — absent: entity, title, srcset_candidate_uri_template)",
            "source" => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_screenshots␞␟{src↠src_with_alternate_widths,alt↠alt,width↠width,height↠height}',
            ],
          ],
        ],
      ],
    ];

    yield 'a simple code component with link prop (js.canvas_test_code_components_with_link_prop, entity:node:article)' => [
      'component_config_entity_id' => 'js.canvas_test_code_components_with_link_prop',
      'content_entity_type_id' => 'node',
      'bundle' => 'article',
      'expected' => [
        'text' => [
          '82ec95693bc89080' => [
            'label' => "Subset of this Article's field_silly_image: alt (1 of 7 props — absent: entity, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_silly_image␞␟alt',
            ],
          ],
          '1409e675864fd2e6' => [
            'label' => "Subset of this Article's field_silly_image: title (1 of 7 props — absent: entity, alt, width, height, srcset_candidate_uri_template, src_with_alternate_widths)",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_silly_image␞␟title',
            ],
          ],
          '7ca10058b43f4d0f' => [
            'label' => "This Article's Revision log message",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝revision_log␞␟value',
            ],
          ],
          '256dc2b8dcec7355' => [
            'label' => "This Article's Title",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝title␞␟value',
            ],
          ],
        ],
        'link' => [
          '4a83ce0c963911b4' => [
            'label' => "Subset of this Article's field_silly_image: entity (1 of 7 props — absent: alt, title, width, height, srcset_candidate_uri_template, src_with_alternate_widths)",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_silly_image␞␟entity␜␜entity:file␝uri␞␟value',
            ],
          ],
          '4999dcb72722c69a' => [
            'label' => "Subset of this Article's field_silly_image: src_with_alternate_widths (1 of 7 props — absent: entity, alt, title, width, height, srcset_candidate_uri_template)",
            'source' => [
              'sourceType' => 'dynamic',
              'expression' => 'ℹ︎␜entity:node:article␝field_silly_image␞␟src_with_alternate_widths',
            ],
          ],
        ],
      ],
    ];

    yield 'a simple code component with no props (js.canvas_test_code_components_with_no_props, entity:node:article)' => [
      'component_config_entity_id' => 'js.canvas_test_code_components_with_no_props',
      'content_entity_type_id' => 'node',
      'bundle' => 'article',
      'expected' => [],
    ];
  }

  /**
   * @testWith ["a/b/c", 404, "The component c does not exist."]
   *           ["a/b/sdc.canvas_test_sdc.image", 404, "The `a` content entity type does not exist."]
   *           ["node/b/sdc.canvas_test_sdc.image", 404, "The `node` content entity type does not have a `b` bundle."]
   *           ["node/article/block.user_login_block", 400, "Only components that define their inputs using JSON Schema and use fields to populate their inputs are currently supported."]
   */
  public function testSuggestStructuredDataForPropShapesClientErrors(string $trail, int $expected_status_code, string $expected_error_message): void {
    $json = $this->assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri('base:/canvas/api/v0/ui/content_template/suggestions/structured-data-for-prop_shapes/' . $trail),
      request_options: [],
      expected_status: $expected_status_code,
      expected_cache_contexts: NULL,
      expected_cache_tags: NULL,
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'UNCACHEABLE (no cacheability)',
    );
    $this->assertSame(['errors' => [$expected_error_message]], $json);

    // When performing the same request without the necessary permission,
    // expect a 403 with a message stating which permission is needed.
    // Testing this for each client error case proves no information is divulged
    // to unauthorized requests. Note also that Page Cache accelerates these.
    $this->drupalLogin($this->limitedPermissionsUser);
    $json = $this->assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri('base:/canvas/api/v0/ui/content_template/suggestions/structured-data-for-prop_shapes/' . $trail),
      request_options: [],
      expected_status: Response::HTTP_FORBIDDEN,
      expected_cache_contexts: ['user.permissions'],
      expected_cache_tags: ['4xx-response', 'http_response'],
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: NULL,
    );
    $this->assertSame(['errors' => [sprintf("The '%s' permission is required.", ContentTemplate::ADMIN_PERMISSION)]], $json);
  }

  public function testSuggestPreviewContentEntities(): void {
    $content_entity_type_id = 'node';
    $bundle = 'article';

    // There are no entities, so we get an empty list.
    $json = $this->assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri("base:/canvas/api/v0/ui/content_template/suggestions/preview/$content_entity_type_id/$bundle"),
      request_options: [],
      expected_status: Response::HTTP_OK,
      expected_cache_contexts: [
        'user.node_grants:view',
        'user.permissions',
      ],
      expected_cache_tags: [
        'http_response',
        $content_entity_type_id . '_list:' . $bundle,
      ],
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'MISS',
    );
    $this->assertSame([], $json);

    // As soon as we create some, we are going to return those.
    $entity_storage = $this->container->get('entity_type.manager')->getStorage($content_entity_type_id);
    for ($i = 1; $i <= 5; ++$i) {
      $entity_storage->create([
        'title' => 'Entity ' . $i,
        'type' => $bundle,
        'changed' => \time() - $i * 1000,
      ])->save();
    }

    $expected = [
      1 => ['id' => '1', 'label' => 'Entity 1'],
      2 => ['id' => '2', 'label' => 'Entity 2'],
      3 => ['id' => '3', 'label' => 'Entity 3'],
      4 => ['id' => '4', 'label' => 'Entity 4'],
      5 => ['id' => '5', 'label' => 'Entity 5'],
    ];
    $json = $this->assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri("base:/canvas/api/v0/ui/content_template/suggestions/preview/$content_entity_type_id/$bundle"),
      request_options: [],
      expected_status: Response::HTTP_OK,
      expected_cache_contexts: [
        'user.node_grants:view',
        'user.permissions',
      ],
      expected_cache_tags: [
        'http_response',
        $content_entity_type_id . ':1',
        $content_entity_type_id . ':2',
        $content_entity_type_id . ':3',
        $content_entity_type_id . ':4',
        $content_entity_type_id . ':5',
        $content_entity_type_id . '_list:' . $bundle,
      ],
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'MISS',
    );
    $this->assertSame($expected, $json);

    // Just because there is a new node doesn't MISS the cache and returns the new one.
    $entity_storage->create([
      'title' => 'Entity LAST',
      'type' => $bundle,
    ])->save();
    $json = $this->assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri("base:/canvas/api/v0/ui/content_template/suggestions/preview/$content_entity_type_id/$bundle"),
      request_options: [],
      expected_status: Response::HTTP_OK,
      expected_cache_contexts: [
        'user.node_grants:view',
        'user.permissions',
      ],
      expected_cache_tags: [
        'http_response',
        $content_entity_type_id . ':1',
        $content_entity_type_id . ':2',
        $content_entity_type_id . ':3',
        $content_entity_type_id . ':4',
        $content_entity_type_id . ':5',
        $content_entity_type_id . ':6',
        $content_entity_type_id . '_list:' . $bundle,
      ],
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'MISS',
    );
    $expected = [6 => ['id' => '6', 'label' => 'Entity LAST']] + $expected;
    $this->assertSame($expected, $json);

    /** @var \Drupal\node\NodeInterface $updated_entity */
    $updated_entity = $entity_storage->load(3);
    $updated_entity->setTitle('Updated article')
      ->save();
    $json = $this->assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri("base:/canvas/api/v0/ui/content_template/suggestions/preview/$content_entity_type_id/$bundle"),
      request_options: [],
      expected_status: Response::HTTP_OK,
      expected_cache_contexts: [
        'user.node_grants:view',
        'user.permissions',
      ],
      expected_cache_tags: [
        'http_response',
        $content_entity_type_id . ':1',
        $content_entity_type_id . ':2',
        $content_entity_type_id . ':3',
        $content_entity_type_id . ':4',
        $content_entity_type_id . ':5',
        $content_entity_type_id . ':6',
        $content_entity_type_id . '_list:' . $bundle,
      ],
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'MISS',
    );
    $expected = [
      3 => ['id' => '3', 'label' => 'Updated article'],
      6 => ['id' => '6', 'label' => 'Entity LAST'],
      1 => ['id' => '1', 'label' => 'Entity 1'],
      2 => ['id' => '2', 'label' => 'Entity 2'],
      4 => ['id' => '4', 'label' => 'Entity 4'],
      5 => ['id' => '5', 'label' => 'Entity 5'],
    ];
    $this->assertSame($expected, $json);
  }

  public function testViewModesList(): void {
    // 1. Test endpoint response when no Template entities are available.
    $json = $this->assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri('base:/canvas/api/v0/ui/content_template/view_modes/node'),
      request_options: [],
      expected_status: Response::HTTP_OK,
      expected_cache_contexts: NULL,
      expected_cache_tags: NULL,
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'UNCACHEABLE (no cacheability)',
    );

    // All View Modes for Article bundle are returned, no ContentTemplates exist.
    self::assertEquals([
      'node' => [
        'article' => [
          'teaser' => [
            'label' => 'Teaser',
            'hasTemplate' => FALSE,
          ],
          'full' => [
            'label' => 'Full content',
            'hasTemplate' => FALSE,
          ],
          'rss' => [
            'label' => 'RSS',
            'hasTemplate' => FALSE,
          ],
          'search_index' => [
            'label' => 'Search index',
            'hasTemplate' => FALSE,
          ],
          'search_result' => [
            'label' => 'Search result highlighting input',
            'hasTemplate' => FALSE,
          ],
        ],
      ],
    ], $json);

    $template_data = [
      'id' => 'node.article.full',
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'article',
      'content_entity_type_view_mode' => 'full',
      'component_tree' => [],
    ];

    // 2. Create ContentTemplate for Full View Mode of Article bundle.
    $template = ContentTemplate::create($template_data);
    $template->save();

    // 3. Test endpoint response, validate Full View Mode `hasTemplate` property of TRUE.
    $json = self::assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri('base:/canvas/api/v0/ui/content_template/view_modes/node'),
      request_options: [],
      expected_status: Response::HTTP_OK,
      expected_cache_contexts: NULL,
      expected_cache_tags: NULL,
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'UNCACHEABLE (no cacheability)',
    );

    self::assertEquals([
      'node' => [
        'article' => [
          'teaser' => [
            'label' => 'Teaser',
            'hasTemplate' => FALSE,
          ],
          'full' => [
            'label' => 'Full content',
            'hasTemplate' => TRUE,
          ],
          'rss' => [
            'label' => 'RSS',
            'hasTemplate' => FALSE,
          ],
          'search_index' => [
            'label' => 'Search index',
            'hasTemplate' => FALSE,
          ],
          'search_result' => [
            'label' => 'Search result highlighting input',
            'hasTemplate' => FALSE,
          ],
        ],
      ],
    ], $json);

    // 4. Create ContentTemplate for Teaser View Mode.
    $template_data['content_entity_type_view_mode'] = 'teaser';
    $template_data['id'] = 'node.article.teaser';
    $template = ContentTemplate::create($template_data);
    $template->save();

    // 5. Test endpoint response, validate Full and Teaser View Modes have `hasTemplate` property values of TRUE.
    $json = self::assertExpectedResponse(
      method: 'GET',
      url: Url::fromUri('base:/canvas/api/v0/ui/content_template/view_modes/node'),
      request_options: [],
      expected_status: Response::HTTP_OK,
      expected_cache_contexts: NULL,
      expected_cache_tags: NULL,
      expected_page_cache: 'UNCACHEABLE (request policy)',
      expected_dynamic_page_cache: 'UNCACHEABLE (no cacheability)',
    );

    self::assertEquals([
      'node' => [
        'article' => [
          'teaser' => [
            'label' => 'Teaser',
            'hasTemplate' => TRUE,
          ],
          'full' => [
            'label' => 'Full content',
            'hasTemplate' => TRUE,
          ],
          'rss' => [
            'label' => 'RSS',
            'hasTemplate' => FALSE,
          ],
          'search_index' => [
            'label' => 'Search index',
            'hasTemplate' => FALSE,
          ],
          'search_result' => [
            'label' => 'Search result highlighting input',
            'hasTemplate' => FALSE,
          ],
        ],
      ],
    ], $json);
  }

}
