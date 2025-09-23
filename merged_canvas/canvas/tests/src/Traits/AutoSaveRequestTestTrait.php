<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\Core\Url;
use Drupal\canvas\Controller\ApiAutoSaveController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

trait AutoSaveRequestTestTrait {

  protected function getAutoSaveStatesFromServer(): array {
    $auto_save_controller = \Drupal::service(ApiAutoSaveController::class);
    $response = $auto_save_controller->get();
    assert($response instanceof JsonResponse);
    $content = $response->getContent();
    assert(is_string($content));
    $auto_saves = json_decode($content, TRUE);
    return $auto_saves;
  }

  protected function assertNoAutoSaveData(): void {
    $response = $this->makePublishAllRequest([]);
    $json = json_decode($response->getContent() ?: '', TRUE);
    self::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    self::assertEquals(['message' => 'No items to publish.'], $json);
  }

  protected function makePublishAllRequest(?array $data = NULL): JsonResponse {
    if (is_null($data)) {
      $data = $this->getAutoSaveStatesFromServer();
    }
    $request = Request::create(
      Url::fromRoute('canvas.api.auto-save.post')->toString(),
      'POST',
      content: (string) json_encode($data),
    );
    $request->headers->set('Content-Type', 'application/json');
    $response = $this->request($request);
    assert($response instanceof JsonResponse);
    return $response;
  }

  protected function assertRequestAutoSaveConflict(Request $request): void {
    try {
      $this->request($request);
      $this->fail('Expected exception');
    }
    catch (ConflictHttpException $exception) {
      self::assertSame('You do not have the latest changes, please refresh your browser.', $exception->getMessage());
    }
  }

}
