<?php

namespace Drupal\ab_split_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

class AbSplitController extends ControllerBase {

  const COOKIE_NAME = 'ab_variant';
  const COOKIE_TTL_DAYS = 30;

  public function __construct(private RequestStack $requestStack) {}

  public static function create($container) {
    return new static(
      $container->get('request_stack')
    );
  }

  public function landing() {
    $request = $this->requestStack->getCurrentRequest();

    // If a variant cookie exists, keep it; else assign 50/50.
    $variant = $request->cookies->get(self::COOKIE_NAME);
    if ($variant !== 'a' && $variant !== 'b') {
      $variant = (mt_rand(0, 1) === 0) ? 'a' : 'b';
    }

    // Decide target.
    $targetPath = $variant === 'a' ? '/landing-a' : '/landing-b';

    // Preserve query string (?utm_..., etc.).
    $qs = $request->getQueryString();
    $location = $targetPath . ($qs ? ('?' . $qs) : '');

    // 302 during the test (switch to 301 only after you pick a winner).
    $response = new RedirectResponse($location, 302);

    // Sticky cookie.
    $expire = (new \DateTimeImmutable('+' . self::COOKIE_TTL_DAYS . ' days'))->getTimestamp();
    $cookie = Cookie::create(
      self::COOKIE_NAME,
      $variant,
      $expire,
      '/',
      null,
      $request->isSecure(),
      true,
      false,
      Cookie::SAMESITE_LAX
    );
    $response->headers->setCookie($cookie);

    // Ensure origin/edge don't cache this router page.
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');

    return $response;
  }
}
