<?php

declare(strict_types=1);

namespace Drupal\canvas_ai_test\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Event subscriber to intercept Canvas AI API requests during tests.
 */
class CanvasAiRequestInterceptor implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 100];
    return $events;
  }

  /**
   * Intercepts Canvas AI API requests and returns fixture responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    if (str_starts_with($path, '/admin/api/canvas/ai')) {
      $response = $this->getFixtureResponse($request);
      $event->setResponse($response);
    }
  }

  /**
   * Gets the appropriate fixture response based on request content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The fixture response.
   */
  private function getFixtureResponse(Request $request): Response {
    $messages = Json::decode($request->getContent());
    $lastMessage = array_pop($messages['messages']);
    $user_message = $lastMessage['text'] ?? '';
    $user_message = strtolower($user_message);
    $user_message = preg_replace('/[^a-z0-9 ]/', '', $user_message) ?? '';
    $user_message = preg_replace('/\s+/', '_', $user_message) ?? '';
    return $this->loadFixtureResponse(dirname(__DIR__, 2) . '/fixtures/' . $user_message . '.json');
  }

  /**
   * Loads a fixture response from a file.
   *
   * @param string $fixture_path
   *   Path to the fixture file.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  private function loadFixtureResponse(string $fixture_path): Response {
    $data = file_get_contents($fixture_path);
    if ($data === FALSE) {
      throw new \RuntimeException("Failed to read fixture file: $fixture_path");
    }
    $json_data = json_decode($data, TRUE);
    return new JsonResponse($json_data, 200);
  }

}
