<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Functional;

use Drupal\Core\Url;
use Drupal\canvas\Entity\Page;
use Drupal\Tests\canvas\Functional\HttpApiTestBase;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\user\UserInterface;
use Drupal\canvas_personalization\Entity\Segment;
use GuzzleHttp\RequestOptions;

/**
 * @covers \Drupal\canvas\Controller\ApiConfigControllers
 * @covers \Drupal\canvas\Controller\ApiConfigAutoSaveControllers
 * @see \Drupal\Tests\canvas\Functional\CanvasConfigEntityHttpApiTest
 * @group canvas
 * @group canvas_personalization
 * @internal
 */
class SegmentHttpApiTest extends HttpApiTestBase {

  use ContribStrictConfigSchemaTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'canvas',
    'canvas_personalization',
    'node',
    // @todo Remove once ComponentSourceInterface is a public API, i.e. after https://www.drupal.org/i/3520484#stable is done.
    'canvas_dev_mode',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected readonly UserInterface $httpApiUser;

  protected readonly array $expectedDefaultSegment;

  protected readonly UserInterface $limitedPermissionsUser;

  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser([
      Page::EDIT_PERMISSION,
      Segment::ADMIN_PERMISSION,
    ]);
    assert($user instanceof UserInterface);
    $this->httpApiUser = $user;

    $this->expectedDefaultSegment = [
      'id' => Segment::DEFAULT_ID,
      'label' => 'Default segment',
      'description' => 'The negotiation fallback locked segment for personalization.',
      'rules' => [],
      'weight' => 2147483647,
      'status' => TRUE,
    ];

    // Create a user with an arbitrary permission that is not related to
    // accessing any Canvas resources.
    $user2 = $this->createUser(['view own unpublished content']);
    assert($user2 instanceof UserInterface);
    $this->limitedPermissionsUser = $user2;
  }

  /**
   * @see \Drupal\canvas_personalization\Entity\Segment
   */
  public function testSegment(): void {
    $this->assertAuthenticationAndAuthorization(Segment::ENTITY_TYPE_ID);

    $base = rtrim(base_path(), '/');
    $list_url = Url::fromUri("base:/canvas/api/v0/config/segment");

    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];
    // Create a segment via the Canvas HTTP API, but forget crucial data (label)
    // that causes the required shape to be violated: 500, courtesy of OpenAPI.
    $segment_to_send = [
      'id' => 'my_segment',
      'label' => 'My segment',
      'rules' => 'incorrect type',
    ];
    $request_options[RequestOptions::JSON] = $segment_to_send;
    $body = $this->assertExpectedResponse('POST', $list_url, $request_options, 500, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'message' => 'Body does not match schema for content-type "application/json" for Request [post /canvas/api/v0/config/segment]. [Value expected to be \'object\', but \'string\' given in rules]',
    ], $body, 'Fails with missing data.');

    // Add missing crucial data, but leave a required shape violation: 500,
    // courtesy of OpenAPI.
    $segment_to_send['label'] = NULL;
    $request_options[RequestOptions::JSON] = $segment_to_send;
    $body = $this->assertExpectedResponse('POST', $list_url, $request_options, 500, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'message' => 'Body does not match schema for content-type "application/json" for Request [post /canvas/api/v0/config/segment]. [Keyword validation failed: Value cannot be null in label]',
    ], $body, 'Fails with invalid shape.');

    // Meet data shape requirements, but violate internal consistency for
    // `rules`: 422 (i.e. validation constraint violation).
    $segment_to_send['label'] = 'My segment';
    $segment_to_send['rules'] = [
      'user_role' => [
        'id' => 'user_role',
        'roles' => [
          'fake',
          'role',
        ],
        'negate' => FALSE,
      ],
    ];
    $request_options[RequestOptions::JSON] = $segment_to_send;
    $body = $this->assertExpectedResponse('POST', $list_url, $request_options, 422, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'errors' => [
        [
          'detail' => "The 'user.role.fake' config does not exist.",
          'source' => ['pointer' => 'rules.user_role.roles.0'],
        ],
        [
          'detail' => "The 'user.role.role' config does not exist.",
          'source' => ['pointer' => 'rules.user_role.roles.1'],
        ],
      ],
    ], $body);

    // Re-retrieve list: 200, unchanged, but now is a Dynamic Page Cache hit.
    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, ['user.permissions'], ['config:segment_list', 'http_response'], 'UNCACHEABLE (request policy)', 'HIT');
    $this->assertSame([
      Segment::DEFAULT_ID => $this->expectedDefaultSegment,
    ], $body);

    // Create a segment via the Canvas HTTP API, correctly: 201.
    $segment_to_send['rules'] = [
      'utm_parameters' => [
        'id' => 'utm_parameters',
        'parameters' => [
          [
            "key" => "utm_source",
            "value" => "my-source-id",
            "matching" => "exact",
          ],
          [
            "key" => "utm_campaign",
            "value" => "HALLOWEEN",
            "matching" => "starts_with",
          ],
        ],
        'negate' => FALSE,
        'all' => TRUE,
      ],
    ];

    // Send it enabled, and ensure it will be disabled anyway.
    $request_options[RequestOptions::JSON] = $segment_to_send + ['status' => FALSE];
    $body = $this->assertExpectedResponse('POST', $list_url, $request_options, 201, NULL, NULL, NULL, NULL, [
      'Location' => [
        "$base/canvas/api/v0/config/segment/my_segment",
      ],
    ]);
    $expected_segment_normalization = [
      'id' => 'my_segment',
      'label' => 'My segment',
      'description' => NULL,
      'rules' => [
        'utm_parameters' => [
          'id' => 'utm_parameters',
          'negate' => FALSE,
          'all' => TRUE,
          'parameters' => [
            [
              "key" => "utm_source",
              "value" => "my-source-id",
              "matching" => "exact",
            ],
            [
              "key" => "utm_campaign",
              "value" => "HALLOWEEN",
              "matching" => "starts_with",
            ],
          ],
        ],
      ],
      'weight' => 0,
      'status' => FALSE,
    ];
    $this->assertSame($expected_segment_normalization, $body);

    // Ensure it's disabled no matter what we sent in status.
    /** @var \Drupal\Core\Entity\EntityStorageInterface $segment_storage */
    $segment_storage = \Drupal::service('entity_type.manager')->getStorage(Segment::ENTITY_TYPE_ID);
    $segment = $segment_storage->loadUnchanged('my_segment');
    assert($segment instanceof Segment);
    self::assertFalse($segment->status());

    // Creating a segment with an already-in-use ID: 409.
    $request_options[RequestOptions::JSON] = $segment_to_send;
    $body = $this->assertExpectedResponse('POST', $list_url, $request_options, 409, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'errors' => [
        "'segment' entity with ID 'my_segment' already exists.",
      ],
    ], $body);

    // Create a (more complex) segment with multiple rules, but missing rule configuration value: 422.
    $complex_segment = $segment_to_send;
    $complex_segment['id'] = 'complex_segment';
    $complex_segment['label'] = 'Complex Segment';
    $complex_segment['description'] = '<p>Complex Segment Description</p>';
    $complex_segment['rules'] = [
      'utm_parameters' => [
        'id' => 'utm_parameters',
        'parameters' => [
          [
            "key" => "utm_source",
            "value" => "my-source-id",
            "matching" => "exact",
          ],
          [
            "key" => "utm_campaign",
            "value" => "This should not contain spaces",
            "matching" => "starts_with",
          ],
        ],
        'negate' => FALSE,
        'all' => TRUE,
      ],
      'user_role' => [
        'id' => 'user_role',
        'roles' => [
          'authenticated' => 'authenticated',
          'anonymous' => 'anonymous',
        ],
        'negate' => TRUE,
      ],
    ];
    $request_options[RequestOptions::JSON] = $complex_segment;
    $body = $this->assertExpectedResponse('POST', $list_url, $request_options, 422, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'errors' => [
        [
          'detail' => 'This value is not valid.',
          'source' => ['pointer' => 'rules.utm_parameters.parameters.1.value'],
        ],
      ],
    ], $body);

    // Add missing rule configuration value: 201.
    $complex_segment['rules']['utm_parameters']['parameters'][1]['value'] = 'Halloween';
    $request_options[RequestOptions::JSON] = $complex_segment;
    $this->assertExpectedResponse('POST', $list_url, $request_options, 201, NULL, NULL, NULL, NULL, [
      'Location' => [
        "$base/canvas/api/v0/config/segment/complex_segment",
      ],
    ]);

    // Delete the complex segment via the Canvas HTTP API: 204.
    $this->assertExpectedResponse('DELETE', Url::fromUri('base:/canvas/api/v0/config/segment/complex_segment'), [], 204, NULL, NULL, NULL, NULL);

    // Re-retrieve list: 200, non-empty list. Dynamic Page Cache miss.
    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, ['user.permissions'], ['config:segment_list', 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    $this->assertSame([
      "my_segment" => $expected_segment_normalization,
      Segment::DEFAULT_ID => $this->expectedDefaultSegment,
    ], $body);
    // Use the individual URL in the list response body.
    $individual_body = $this->assertExpectedResponse('GET', Url::fromUri('base:/canvas/api/v0/config/segment/my_segment'), [], 200, ['user.permissions'], ['config:canvas_personalization.segment.my_segment', 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    $expected_individual_body_normalization = $expected_segment_normalization;
    $this->assertSame($expected_individual_body_normalization, $individual_body);

    // Modify a segment incorrectly (shape-wise): 500.
    $request_options[RequestOptions::JSON] = [
      'id' => $segment_to_send['id'],
      'label' => ['this', 'is', 'the', 'wrong', 'type'],
      'rules' => [],
    ];
    $body = $this->assertExpectedResponse('PATCH', Url::fromUri('base:/canvas/api/v0/config/segment/my_segment'), $request_options, 500, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'message' => 'Body does not match schema for content-type "application/json" for Request [patch /canvas/api/v0/config/segment/{configEntityId}]. [Value expected to be \'string\', but \'array\' given in label]',
    ], $body, 'Fails with an invalid segment.');

    // Modify a segment incorrectly (consistency-wise): 422.
    $request_options[RequestOptions::JSON] = [
      'id' => $segment_to_send['id'],
      'label' => $segment_to_send['label'],
      'rules' => [
        'utm_parameters' => [
          'id' => 'utm_parameters',
          'parameters' => [
            [
              "key" => "This should not contain spaces",
              "value" => "",
              "matching" => "invalid-matching",
            ],
          ],
          'negate' => FALSE,
          'all' => TRUE,
        ],
      ],
    ];
    $body = $this->assertExpectedResponse('PATCH', Url::fromUri('base:/canvas/api/v0/config/segment/my_segment'), $request_options, 422, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'errors' => [
        [
          'detail' => 'This value is not valid.',
          'source' => ['pointer' => 'rules.utm_parameters.parameters.0.key'],
        ],
        [
          'detail' => 'This value should not be blank.',
          'source' => ['pointer' => 'rules.utm_parameters.parameters.0.value'],
        ],
        [
          'detail' => 'The value you selected is not a valid choice.',
          'source' => ['pointer' => 'rules.utm_parameters.parameters.0.matching'],
        ],
      ],
    ], $body);

    // Modify a segment correctly: 200.
    $request_options[RequestOptions::JSON] = $segment_to_send;
    $body = $this->assertExpectedResponse('PATCH', Url::fromUri('base:/canvas/api/v0/config/segment/my_segment'), $request_options, 200, NULL, NULL, NULL, NULL);
    $this->assertSame($expected_individual_body_normalization, $body);

    // Partially modify a segment: 200.
    $segment_to_send['label'] = 'Updated test segment name';
    $expected_individual_body_normalization['label'] = $segment_to_send['label'];
    $expected_segment_normalization['label'] = $segment_to_send['label'];
    $request_options[RequestOptions::JSON] = [
      'id' => $segment_to_send['id'],
      'label' => $segment_to_send['label'],
    ];
    $body = $this->assertExpectedResponse('PATCH', Url::fromUri('base:/canvas/api/v0/config/segment/my_segment'), $request_options, 200, NULL, NULL, NULL, NULL);
    $this->assertSame($expected_individual_body_normalization, $body);

    // Re-retrieve list: 200, non-empty list. Dynamic Page Cache miss.
    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, ['user.permissions'], ['config:segment_list', 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    $this->assertSame([
      "my_segment" => $expected_segment_normalization,
      Segment::DEFAULT_ID => $this->expectedDefaultSegment,
    ], $body);

    // Disable the segment.
    Segment::load('my_segment')?->disable()->save();
    // Assert that disabled segments are still showing in the list.
    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, [
      'user.permissions',
    ], [
      'config:segment_list',
      'http_response',
    ], 'UNCACHEABLE (request policy)', 'MISS');
    $this->assertSame([
      "my_segment" => $expected_segment_normalization,
      Segment::DEFAULT_ID => $this->expectedDefaultSegment,
    ], $body);

    // Attempt to update the default segment which is "locked".
    $body = $this->assertExpectedResponse('PATCH', Url::fromUri('base:/canvas/api/v0/config/segment/default'), $request_options, 403, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'errors' => [
        "The default segment cannot be deleted or updated.",
      ],
    ], $body);

    // Attempt to delete the default segment which is "locked".
    $body = $this->assertExpectedResponse('DELETE', (Url::fromUri('base:/canvas/api/v0/config/segment/default')), [], 403, NULL, NULL, NULL, NULL);
    $this->assertSame([
      'errors' => [
        "The default segment cannot be deleted or updated.",
      ],
    ], $body);

    $this->assertDeletionAndDefaultOnly(Url::fromUri('base:/canvas/api/v0/config/segment/my_segment'), $list_url, 'config:segment_list');

    // This was now tested full circle! ✅
  }

  private function assertAuthenticationAndAuthorization(string $entity_type_id): void {
    $list_url = Url::fromUri("base:/canvas/api/v0/config/$entity_type_id");

    // Anonymous user: 401.
    $body = $this->assertExpectedResponse('GET', $list_url, [], 401, ['user.roles:anonymous'], ['4xx-response', 'config:system.site', 'config:user.role.anonymous', 'http_response'], 'MISS', NULL);
    $this->assertSame([
      'errors' => [
        "You must be logged in to access this resource.",
      ],
    ], $body);

    // Limited Permissions: 403.
    $this->drupalLogin($this->limitedPermissionsUser);
    $body = $this->assertExpectedResponse('GET', $list_url, [], 403, ['user.permissions'], ['4xx-response', 'http_response'], 'UNCACHEABLE (request policy)', NULL);
    $this->assertSame([
      'errors' => [
        "Requires >=1 content entity type with an Canvas field that can be created or edited.",
      ],
    ], $body);

    // Authenticated & authorized: 200, list includes the default segment.
    $this->drupalLogin($this->httpApiUser);
    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, ['user.permissions'], ["config:{$entity_type_id}_list", 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    $this->assertSame([
      Segment::DEFAULT_ID => $this->expectedDefaultSegment,
    ], $body);

    // Send a POST request without the CSRF token.
    $request_options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json',
      ],
    ];
    $response = $this->makeApiRequest('POST', $list_url, $request_options);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame([
      'errors' => [
        "X-CSRF-Token request header is missing",
      ],
    ], json_decode((string) $response->getBody(), TRUE));
  }

  public function testSegmentWeight(): void {
    $this->drupalLogin($this->httpApiUser);
    $list_url = Url::fromUri("base:/canvas/api/v0/config/segment");

    Segment::create([
      'id' => 'test_segment',
      'label' => 'Test Segment',
      'rules' => [],
      // Segments default to a weight of zero.
    ])->save();

    Segment::create([
      'id' => 'test_segment_2',
      'label' => 'Test Segment 2',
      'rules' => [],
      'weight' => 1,
    ])->save();

    $test_segment_1_expected_data = [
      'id' => 'test_segment',
      'label' => 'Test Segment',
      'description' => NULL,
      'rules' => [],
      'weight' => 0,
      'status' => TRUE,
    ];

    $test_segment_2_expected_data = [
      'id' => 'test_segment_2',
      'label' => 'Test Segment 2',
      'description' => NULL,
      'rules' => [],
      'weight' => 1,
      'status' => TRUE,
    ];

    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, ['user.permissions'], ['config:segment_list', 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    // Check the order of the segments based on their weights.
    $this->assertSame([
      "test_segment" => $test_segment_1_expected_data,
      "test_segment_2" => $test_segment_2_expected_data,
      Segment::DEFAULT_ID => $this->expectedDefaultSegment,
    ], $body);

    // Update the weight of the first segment.
    $segment_to_send = [
      'id' => 'test_segment',
      'label' => 'Test Segment',
      'description' => NULL,
      'rules' => [],
      'weight' => 3,
      'status' => FALSE,
    ];

    $request_options[RequestOptions::JSON] = $segment_to_send;
    $this->assertExpectedResponse('PATCH', Url::fromUri('base:/canvas/api/v0/config/segment/test_segment'), $request_options, 200, NULL, NULL, NULL, NULL);

    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, ['user.permissions'], ['config:segment_list', 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    // Re-check the order of the segments based on the updated weight.
    $this->assertSame([
      "test_segment_2" => $test_segment_2_expected_data,
      "test_segment" => [
        'id' => 'test_segment',
        'label' => 'Test Segment',
        'description' => NULL,
        'rules' => [],
        'weight' => 3,
        'status' => FALSE,
      ],
      Segment::DEFAULT_ID => $this->expectedDefaultSegment,
    ], $body);
  }

  /**
   * Asserts we can delete a resource, and we get only the default afterward.
   */
  protected function assertDeletionAndDefaultOnly(Url $resource_url, Url $list_url, string $list_cache_tag): void {
    // Delete the sole remaining segment via the Canvas HTTP API: 204.
    $body = $this->assertExpectedResponse('DELETE', $resource_url, [], 204, NULL, NULL, NULL, NULL);
    $this->assertNull($body);

    // Re-retrieve list: 200, empty list. Dynamic Page Cache miss.
    $body = $this->assertExpectedResponse('GET', $list_url, [], 200, ['user.permissions'], [$list_cache_tag, 'http_response'], 'UNCACHEABLE (request policy)', 'MISS');
    $this->assertSame([
      Segment::DEFAULT_ID => $this->expectedDefaultSegment,
    ], $body);
    $individual_body = $this->assertExpectedResponse('GET', $resource_url, [], 404, NULL, NULL, 'UNCACHEABLE (request policy)', 'UNCACHEABLE (no cacheability)');
    $this->assertSame([], $individual_body);
  }

}
