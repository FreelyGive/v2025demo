<?php

declare(strict_types=1);

namespace Drupal\canvas_oauth\Routing;

use Drupal\Core\Authentication\AuthenticationCollector;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouteCollection;

class CanvasOauthRouteSubscriber extends RouteSubscriberBase {

  /**
   * Name of route option Canvas uses to mark a route as an external API endpoint.
   */
  private const ROUTE_OPTION_EXTERNAL_API = 'canvas_external_api';

  public function __construct(
    #[Autowire(service: 'authentication_collector')]
    private readonly AuthenticationCollector $authenticationCollector,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // The Canvas routes don't define an `_auth` option, which means that all global
    // authentication providers are allowed to authenticate on these routes. We
    // intend to keep that behavior, but explicitly adding an `_auth` option
    // means only the providers listed in the option are allowed to authenticate.
    // At the same time, we don't want to mark this module's authentication
    // provider as global.
    // So let's collect all global providers, and place `canvas_oauth` at the
    // beginning of the list.
    // One exclusion we make is the `oauth2` provider by the Simple OAuth
    // module, which would be redundant with `canvas_oauth`.
    // @see \Drupal\canvas_oauth\Authentication\Provider\CanvasOauthAuthenticationProvider
    $providers = array_filter(
      array_keys($this->authenticationCollector->getSortedProviders()),
      fn($provider_id) => $this->authenticationCollector->isGlobal($provider_id) && $provider_id !== 'oauth2'
    );

    foreach ($collection->all() as $route_id => $route) {
      if (str_starts_with($route_id, 'canvas.') && $route->getOption(self::ROUTE_OPTION_EXTERNAL_API)) {
        // @see \Drupal\canvas_oauth\Authentication\Provider\CanvasOauthAuthenticationProvider
        $route->setOption('_auth', ['canvas_oauth', ...$providers]);
      }
    }
  }

}
