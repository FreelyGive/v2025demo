<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Provides a service to expose site metadata to drupalSettings for JS components.
 *
 * This includes site branding, breadcrumbs, page title and base URL.
 * Intended for use with dynamic JavaScript components such as those in Drupal Canvas.
 */
readonly final class CodeComponentDataProvider {

  public const string V0 = 'v0';
  public const string CANVAS_DATA_KEY = 'canvasData';

  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private RequestStack $requestStack,
    private RouteMatchInterface $routeMatch,
    private TitleResolverInterface $titleResolver,
    private ChainBreadcrumbBuilderInterface $breadcrumbManager,
    private ContainerInterface $container,
  ) {}

  /**
   * Returns the BaseUrl for V0 of drupalSettings.canvasData.
   *
   * @return array[]
   */
  public function getCanvasDataBaseUrlV0(): array {
    $request = $this->requestStack->getCurrentRequest();
    \assert($request instanceof Request);

    return [
      self::V0 => [
        // ⚠️ Not the same as `drupalSettings.path.baseUrl` nor Symfony's
        // definition of a base URL.
        // JavaScript tools like @drupal-api-client/json-api-client usually need
        // a full absolute URL.
        // @see \Symfony\Component\HttpFoundation\Request::getBaseUrl()
        // @see \Drupal\system\Hook\SystemHooks::jsSettingsAlter()
        'baseUrl' => $request->getSchemeAndHttpHost() . $request->getBaseUrl(),
      ],
    ];
  }

  /**
   * Returns the Branding array for V0 of drupalSettings.canvasData.
   *
   * @return array[]
   */
  public function getCanvasDataBrandingV0(): array {
    $site_config = $this->configFactory->get('system.site');
    return [
      self::V0 => [
        'branding' => [
          'homeUrl' => $site_config->get('page')['front'] ?? '',
          'siteName' => $site_config->get('name') ?? '',
          'siteSlogan' => $site_config->get('slogan') ?? '',
        ],
      ],
    ];
  }

  /**
   * Returns the Breadcrumbs for V0 of drupalSettings.canvasData.
   *
   * @return array[]
   */
  public function getCanvasDataBreadcrumbsV0(): array {
    return [
      self::V0 => [
        'breadcrumbs' => array_map(static function (Link $link) {
          $url = $link->getUrl();
          return [
            'key' => $url->getRouteName() ?? '',
            'text' => $link->getText(),
            'url' => $url->toString() ?? '',
          ];
        }, $this->breadcrumbManager->build($this->routeMatch)->getLinks()),
      ],
    ];
  }

  /**
   * Returns the PageTitle for V0 of drupalSettings.canvasData.
   *
   * @return array[]
   */
  public function getCanvasDataPageTitleV0(): array {
    $request = $this->requestStack->getCurrentRequest();
    \assert($request instanceof Request);
    $route = $this->routeMatch->getRouteObject();
    \assert($route instanceof Route);
    return [
      self::V0 => [
        // @todo improve title in https://www.drupal.org/i/3502371
        'pageTitle' => $this->titleResolver->getTitle($request, $route) ?: '',
      ],
    ];
  }

  /**
   * Returns settings for using JSON:API for V0 of drupalSettings.canvasData.
   *
   * @return array
   */
  public function getCanvasDataJsonApiSettingsV0(): array {
    if (!$this->container->hasParameter('jsonapi.base_path')) {
      // If the `jsonapi.base_path` service parameter is not available, it means
      // the JSON:API module is not installed.
      // In contrast to the other settings, this may hence not change the
      // placeholder values in `canvas/canvasData.v0.jsonapiSettings` at
      // all.
      return [
        self::V0 => [
          'jsonapiSettings' => NULL,
        ],
      ];
    }
    $jsonapi_base_path = $this->container->getParameter('jsonapi.base_path');
    \assert(is_string($jsonapi_base_path));
    return [
      self::V0 => [
        'jsonapiSettings' => [
          'apiPrefix' => ltrim($jsonapi_base_path, '/'),
        ],
      ],
    ];
  }

  /**
   * Parses the js code and attach the associated library.
   *
   * @param string $jsCode
   *   The JavaScript code.
   *
   * @return array|string[]
   *   The array of the drupalSettings libraries.
   */
  public static function getRequiredCanvasDataLibraries(string $jsCode): array {
    @trigger_error('Calling ' . __METHOD__ . '() is deprecated in canvas:0.7.3-alpha1 and will be removed from canvas:1.0.0. There is no replacement. See https://www.drupal.org/node/3538276', E_USER_DEPRECATED);
    return [];
  }

}
