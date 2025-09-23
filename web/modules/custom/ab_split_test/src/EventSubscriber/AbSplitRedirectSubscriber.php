<?php

namespace Drupal\ab_split_test\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * 302-redirects /landing to /landing-a or /landing-b with cookie stickiness.
 */
class AbSplitRedirectSubscriber implements EventSubscriberInterface {

  const COOKIE_NAME = 'ab_variant';
  const COOKIE_TTL_DAYS = 30;

  public function __construct(
    private RequestStack $requestStack,
    private ConfigFactoryInterface $configFactory
  ) {}

  public static function getSubscribedEvents(): array {
    // Run early but after routing is available; adjust priority if needed.
    return [KernelEvents::REQUEST => ['onRequest', 28]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $this->requestStack->getCurrentRequest();
    $path = trim($request->getPathInfo(), '/');

    // Only act on the base path of the experiment.
    if ($path !== 'landing') {
      return;
    }

    // Avoid redirect loops for bots/crawlers if you like:
    $ua = $request->headers->get('User-Agent', '');
    if (stripos($ua, 'bot') !== false || stripos($ua, 'crawler') !== false) {
      return;
    }

    // If a variant cookie exists, keep it. Otherwise assign one.
    $variant = $request->cookies->get(self::COOKIE_NAME);
    if ($variant !== 'a' && $variant !== 'b') {
      // 50/50 split; tweak as needed.
      $variant = (mt_rand(0, 1) === 0) ? 'a' : 'b';
    }

    // Build target path based on variant.
    $targetPath = $variant === 'a' ? '/landing-a' : '/landing-b';

    // Preserve the original query string (?utm_..., etc.).
    $qs = $request->getQueryString();
    $location = $targetPath . ($qs ? ('?' . $qs) : '');

    $response = new RedirectResponse($location, 302);
    // Set sticky cookie.
    $expire = (new \DateTimeImmutable('+' . self::COOKIE_TTL_DAYS . ' days'))->getTimestamp();
    $cookie = Cookie::create(self::COOKIE_NAME, $variant, $expire, '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX);
    $response->headers->setCookie($cookie);

    // Make sure the base URL `/landing` isn’t cached at the edge.
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');

    $event->setResponse($response);
  }

}
