<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_oauth\Kernel;

use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Url;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\Traits\CreateTestJsComponentTrait;
use Drupal\Tests\simple_oauth\Kernel\AuthorizedRequestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\Pattern;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Exception\OAuthUnauthorizedHttpException;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests API endpoints where the Canvas OAuth authentication provider is applied.
 *
 * @group canvas_oauth
 */
class CanvasOauthAuthenticationProviderHttpTest extends AuthorizedRequestBase {

  use CreateTestJsComponentTrait;
  use RequestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'canvas',
    'media',
    'path',
    'canvas_oauth',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createTestCodeComponent();
    AssetLibrary::create([
      'id' => AssetLibrary::GLOBAL_ID,
      'label' => 'Global',
    ])->save();
    Pattern::create([
      'id' => 'test-pattern',
      'label' => 'Test pattern',
      'status' => TRUE,
      'component_tree' => [],
    ])->save();
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
  }

  /**
   * Data provider for testing routes with authenticated HTTP requests.
   *
   * @return array<string, array{
   *   0: string,
   *   1: array<string>,
   *   2: array<string>,
   *   3: string,
   *   4: array<string, mixed>
   *   }> Array of test cases where:
   *   - Index 0: Route name
   *   - Index 1: Route parameter values for `canvas_config_entity_type_id` and
   *     `canvas_config_entity`
   *   - Index 2: Required permissions
   *   - Index 3: HTTP method
   *   - Index 4: Request body data for POST/PATCH
   */
  public static function dataProviderRoutes(): array {
    return [
      'INDEX js components' => ['canvas.api.config.list', [JavaScriptComponent::ENTITY_TYPE_ID], [], 'GET', []],
      'GET js component' => ['canvas.api.config.get', [JavaScriptComponent::ENTITY_TYPE_ID, 'test-code-component'], [], 'GET', []],
      'GET asset library' => ['canvas.api.config.get', [AssetLibrary::ENTITY_TYPE_ID, AssetLibrary::GLOBAL_ID], [], 'GET', []],
      'POST js component' => [
        'canvas.api.config.post',
        [JavaScriptComponent::ENTITY_TYPE_ID],
        ['administer code components'],
        'POST',
        [
          'machineName' => 'new-test-code-component',
          'name' => 'New test code component',
          'status' => FALSE,
          'sourceCodeJs' => '// JS source',
          'sourceCodeCss' => '/* CSS source */',
          'compiledJs' => '// Compiled JS',
          'compiledCss' => '/* Compiled CSS */',
          'importedJsComponents' => [],
          'dataDependencies' => [],
        ],
      ],
      'PATCH js component' => [
        'canvas.api.config.patch',
        [JavaScriptComponent::ENTITY_TYPE_ID, 'test-code-component'],
        ['administer code components'],
        'PATCH',
        [
          'name' => 'Updated test code component',
        ],
      ],
      'PATCH asset library' => [
        'canvas.api.config.patch',
        [AssetLibrary::ENTITY_TYPE_ID, AssetLibrary::GLOBAL_ID],
        ['administer code components'],
        'PATCH',
        [
          'js' => [
            'original' => '// Updated JS',
            'compiled' => '// Updated compiled JS',
          ],
        ],
      ],
      'DELETE js component' => [
        'canvas.api.config.delete',
        [JavaScriptComponent::ENTITY_TYPE_ID, 'test-code-component'],
        ['administer code components'],
        'DELETE',
        [],
      ],
    ];
  }

  /**
   * Tests a route with a user with no permissions.
   *
   * This verifies that cookie-based authentication keeps working as expected
   * when the request doesn't contain an OAuth2 access token.
   *
   * @dataProvider dataProviderRoutes
   */
  public function testRouteWithUserWithNoPermissions(string $route_name, array $parameter_values, array $required_permissions, string $method, array $data): void {
    // Create a user with the minimum permissions: we use Page:CREATE_PERMISSION
    // for allowing `$user` to use Canvas, but not altering the
    // `$required_permissions` argument.
    /** @var \Drupal\Core\Session\AccountInterface $user */
    // @phpstan-ignore-next-line varTag.nativeType
    $user = $this->createUser([Page::CREATE_PERMISSION]);
    $this->setCurrentUser($user);
    $request = $this->createRequest($route_name, $parameter_values, $method, $data);
    if (!empty($required_permissions)) {
      // Expect an exception because the user has no permissions.
      $exception_class = $method === 'GET' ? CacheableAccessDeniedHttpException::class : AccessDeniedHttpException::class;
      $this->expectException($exception_class);
      $this->expectExceptionMessage(sprintf("The '%s' permission is required.", $required_permissions[0]));
    }
    $response = $this->request($request);
    if (empty($required_permissions)) {
      self::assertTrue($response->isSuccessful());
    }
  }

  /**
   * Tests a route with a user with appropriate permissions.
   *
   * This verifies that cookie-based authentication keeps working as expected
   * when the request doesn't contain an OAuth2 access token.
   *
   * @dataProvider dataProviderRoutes
   */
  public function testRouteWithUserWithPermissions(string $route_name, array $parameter_values, array $required_permissions, string $method, array $data): void {
    /** @var \Drupal\Core\Session\AccountInterface $user */
    // We need some Canvas-enabled content permission in every case for accessing
    // Canvas URLs.
    // @phpstan-ignore-next-line varTag.nativeType
    $user = $this->createUser([Page::CREATE_PERMISSION, ...$required_permissions]);
    $this->setCurrentUser($user);
    $request = $this->createRequest($route_name, $parameter_values, $method, $data);
    $response = $this->request($request);
    self::assertTrue($response->isSuccessful());
  }

  /**
   * Tests a route with an invalid access token.
   *
   * @dataProvider dataProviderRoutes
   */
  public function testRouteWithInvalidToken(string $route_name, array $parameter_values, array $required_permissions, string $method, array $data): void {
    $request = $this->createRequest($route_name, $parameter_values, $method, $data);
    $this->expectException(OAuthUnauthorizedHttpException::class);
    $this->expectExceptionMessage("The resource owner or authorization server denied the request");
    // Set an invalid access token.
    $request->headers->set('Authorization', 'Bearer wicked-witch-of-the-west');
    $this->request($request);
  }

  /**
   * Data provider for testing uncovered routes with authenticated HTTP requests.
   *
   * It's enough to test with one config entity type that's not covered. The goal
   * of this test is to verify that the authentication provider is NOT applied
   * to an API endpoint unless we allow it. The logic that evaluates this is
   * being directly tested with more test cases in
   * `\Drupal\Tests\canvas_oauth\Kernel\CanvasOauthAuthenticationProviderTest::testAppliesToRoutedRequest`.
   *
   * @return array<string, array{
   *   0: string,
   *   1: array<string>,
   *   2: array<string>,
   *   3: string,
   *   4: array<string, mixed>
   *   }> Array of test cases where:
   *   - Index 0: Route name
   *   - Index 1: Route parameter values for `canvas_config_entity_type_id` and
   *     `canvas_config_entity`
   *   - Index 2: Required permissions
   *   - Index 3: HTTP method
   *   - Index 4: Request body data for POST/PATCH
   */
  public static function dataProviderRoutesNotCovered(): array {
    return [
      'INDEX patterns' => ['canvas.api.config.list', [Pattern::ENTITY_TYPE_ID], [], 'GET', []],
      'GET pattern' => ['canvas.api.config.get', [Pattern::ENTITY_TYPE_ID, 'test-pattern'], [], 'GET', []],
      'POST pattern' => [
        'canvas.api.config.post',
        [Pattern::ENTITY_TYPE_ID],
        ['administer patterns'],
        'POST',
        [],
      ],
      'PATCH pattern' => [
        'canvas.api.config.patch',
        [Pattern::ENTITY_TYPE_ID, 'test-pattern'],
        ['administer patterns'],
        'PATCH',
        [],
      ],
      'DELETE pattern' => [
        'canvas.api.config.delete',
        [Pattern::ENTITY_TYPE_ID, 'test-pattern'],
        ['administer patterns'],
        'DELETE',
        [],
      ],
    ];
  }

  /**
   * Tests a route that is not covered by this module's auth provider.
   *
   * @dataProvider dataProviderRoutesNotCovered
   */
  public function testNotCoveredRoute(string $route_name, array $parameter_values, array $required_permissions, string $method, array $data): void {
    // Request an access token for scopes that get created with the required
    // permissions.
    // In case no permissions are required, we still need to pass a permission
    // for a scope to be created, and some Canvas-enabled content permission for
    // accessing any Canvas URL.
    $access_token = $this->requestAccessToken([Page::CREATE_PERMISSION, ...$required_permissions]);
    $request = $this->createRequest($route_name, $parameter_values, $method, $data);
    $request->headers->set('Authorization', 'Bearer ' . $access_token);
    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('The used authentication method is not allowed on this route.');
    $this->request($request);
  }

  /**
   * Creates a request for the given route.
   *
   * @param string $route_name
   *   The route name.
   * @param array $parameter_values
   *   The parameter values.
   * @param string $method
   *   The HTTP method.
   * @param array $data
   *   The data to send in the request body.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request.
   */
  private function createRequest(string $route_name, array $parameter_values, string $method, array $data): Request {
    $request = Request::create(
      Url::fromRoute($route_name, $this->getParameters($parameter_values))->toString(),
      $method,
      content: json_encode($data) ?: NULL,
    );
    if (in_array($method, ['POST', 'PATCH'])) {
      $request->headers->set('Content-Type', 'application/json');
    }
    return $request;
  }

  /**
   * Returns route parameters for a request based on an array of values.
   *
   * @param array $parameter_values
   *   The parameter values.
   *
   * @return array
   *   The parameters keyed as 'canvas_config_entity_type_id' and 'canvas_config_entity'.
   */
  private function getParameters(array $parameter_values): array {
    $parameters = ['canvas_config_entity_type_id' => $parameter_values[0]];
    if (isset($parameter_values[1])) {
      $parameters['canvas_config_entity'] = $parameter_values[1];
    }
    return $parameters;
  }

  /**
   * Requests OAuth2 access token with scopes created for the given permissions.
   *
   * @param array $permissions
   *   The required permissions. For each permission a scope is created, and the
   *   access token is requested for these scopes.
   *
   * @return string
   *   The access token.
   */
  private function requestAccessToken(array $permissions): string {
    $scopes = $this->createScopes($permissions);
    $client = $this->createClient($scopes);
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $client->getClientId(),
      'client_secret' => $this->clientSecret,
      // The `scope` parameter is a space-separated list of scope names.
      'scope' => implode(' ', array_map(fn($scope) => $scope->getName(), $scopes)),
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->request($request);
    $parsed_response = $this->assertValidTokenResponse($response);
    return $parsed_response['access_token'];
  }

  /**
   * Creates OAuth2 scopes for the given permissions.
   *
   * @param array $permissions
   *   The permissions. For each permission a scope is created where the
   *   permission is configured as the scope's permission.
   *
   * @return array
   *   The scopes.
   */
  private function createScopes(array $permissions): array {
    $scopes = [];
    foreach ($permissions as $index => $permission) {
      $scope = Oauth2Scope::create([
        'name' => 'canvas:scope' . ($index + 1),
        'grant_types' => [
          'client_credentials' => [
            'status' => TRUE,
          ],
        ],
        'umbrella' => FALSE,
        'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
        'granularity_configuration' => [
          'permission' => $permission,
        ],
      ]);
      $scope->save();
      $scopes[] = $scope;
    }
    return $scopes;
  }

  /**
   * Creates an OAuth2 client with the given scopes enabled for the client.
   *
   * The client is configured with the client credentials grant type enabled.
   *
   * @param array $scopes
   *   The scopes.
   *
   * @return \Drupal\consumers\Entity\Consumer
   *   The client.
   */
  private function createClient(array $scopes): Consumer {
    $client = Consumer::create([
      'client_id' => 'canvas_oauth_client',
      'is_default' => FALSE,
      'label' => 'Canvas OAuth Client',
      'grant_types' => [
        'client_credentials',
      ],
      'scopes' => array_map(fn($scope) => $scope->id(), $scopes),
      'secret' => $this->clientSecret,
      'user_id' => $this->user,
    ]);
    $client->save();
    return $client;
  }

}
