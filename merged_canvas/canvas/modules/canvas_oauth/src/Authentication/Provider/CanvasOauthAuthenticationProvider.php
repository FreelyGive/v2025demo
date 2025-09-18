<?php

declare(strict_types=1);

namespace Drupal\canvas_oauth\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\canvas\Entity\AssetLibrary;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * OAuth2 authentication provider for Canvas API routes.
 *
 * Conditionally delegates the authentication to the Simple OAuth module's
 * OAuth2 authentication provider.
 *
 * @see \Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider
 *
 * This authentication provider is added to a subset of the Canvas API routes.
 * Currently, they are the routes to work with Canvas's config entities.
 * @see \Drupal\canvas_oauth\Routing\CanvasOauthRouteSubscriber
 *
 * Because those endpoints can handle all types of config entities, applying the
 * authentication provider is narrowed down to specific entity types.
 * @see \Drupal\canvas_oauth\Authentication\Provider\CanvasOauthAuthenticationProvider::applies()
 */
class CanvasOauthAuthenticationProvider implements AuthenticationProviderInterface {

  public function __construct(
    #[Autowire(service: '@simple_oauth.authentication.simple_oauth')]
    private readonly SimpleOauthAuthenticationProvider $simpleOauthAuthenticationProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Currently, this authentication provider is only applied to the routes that
    // work with Canvas's config entities.
    // @see \Drupal\canvas_oauth\Routing\CanvasOauthRouteSubscriber
    $route_match = RouteMatch::createFromRequest($request);
    $entity_type_id = $route_match->getRawParameter('canvas_config_entity_type_id');
    // Narrow down the entity types that are protected by this authentication
    // provider.
    $protected_entity_types = [
      JavaScriptComponent::ENTITY_TYPE_ID,
      AssetLibrary::ENTITY_TYPE_ID,
    ];

    if ($entity_type_id === NULL || !in_array($entity_type_id, $protected_entity_types)) {
      return FALSE;
    }

    // Let the Simple OAuth authentication provider decide if the request is
    // protected. It does so by checking if the request has an OAuth2 access
    // token.
    return $this->simpleOauthAuthenticationProvider->applies($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    // Delegate to the Simple OAuth authentication provider.
    return $this->simpleOauthAuthenticationProvider->authenticate($request);
  }

}
