<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\PageRegion;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\Kernel\Traits\VfsPublicStreamUrlTrait;
use Drupal\Tests\canvas\Traits\AutoSaveManagerTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-import-type ComponentConfigEntityId from \Drupal\canvas\Entity\Component
 */
class ApiLayoutControllerTestBase extends KernelTestBase {

  use AutoSaveManagerTestTrait;

  const REGION_PATTERN = '/<!-- canvas-region-start-%1$s -->([\n\s\S]*)<!-- canvas-region-end-%1$s -->/';

  use RequestTrait {
    request as parentRequest;
  }
  use UserCreationTrait;
  use VfsPublicStreamUrlTrait;

  protected ?ContentEntityInterface $previewEntity;

  protected static function getAdminPermission(EntityInterface $entity): string {
    if ($entity instanceof Node) {
      return 'edit any ' . $entity->bundle() . ' content';
    }
    if ($entity instanceof ContentTemplate) {
      return ContentTemplate::ADMIN_PERMISSION;
    }
    throw new \LogicException('Unsupported entity type: ' . $entity->getEntityTypeId());
  }

  /**
   * Unwrap the JSON response so we can perform assertions on it.
   */
  protected function request(Request $request): Response {
    $request->headers->set('Content-Type', 'application/json');
    $response = $this->parentRequest($request);
    $decodedResponse = static::decodeResponse($response);
    if (isset($decodedResponse['html'])) {
      $this->setRawContent($decodedResponse['html']);
    }
    return $response;
  }

  /**
   * Omit information received in the GET response that cannot be POSTed.
   */
  protected function filterLayoutForPost(string $content): string {
    $json = \json_decode($content, TRUE);
    unset($json['isNew'], $json['isPublished'], $json['html']);
    $json += ['clientInstanceId' => $this->randomString(100)];
    return \json_encode($json, JSON_THROW_ON_ERROR);
  }

  protected function getLayoutUrl(EntityInterface $entity): Url {
    if ($entity instanceof ContentTemplate) {
      $route_name = 'canvas.api.layout.get.content_template';
      self::assertInstanceOf(ContentEntityInterface::class, $this->previewEntity);
      $url_args = [
        'entity' => $entity->id(),
        'preview_entity' => $this->previewEntity->id(),
      ];
    }
    else {
      $url_args = [
        'entity' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
      ];
      $route_name = 'canvas.api.layout.get';
    }
    return Url::fromRoute($route_name, $url_args);
  }

  /**
   * Uses regex to find regions "wrapped" by inline HTML comments in content.
   *
   * @param string $region
   *
   * @return ?string
   */
  protected function getRegion(string $region): ?string {
    $matches = [];

    $content = $this->getRawContent() ?: '';
    // Covers 'application/json' endpoint responses with 'html' property.
    if (json_validate($content)) {
      $decoded = \json_decode($content, TRUE);
      $content = $decoded['html'] ?? $content;
    }

    \preg_match_all(sprintf(self::REGION_PATTERN, $region), $content, $matches);
    return array_key_exists(0, $matches[1]) ? $matches[1][0] : NULL;
  }

  /**
   * Uses regex to find component instances "wrapped" by inline HTML comments.
   *
   * @param ?string $html
   *   The HTML to search; if none provided will use the current raw content.
   *
   * @return array
   */
  protected function getComponentInstances(?string $html): array {
    $html ??= $this->getRawContent();
    // Covers 'application/json' endpoint responses with 'html' property.
    if (json_validate($html)) {
      $decoded = \json_decode($html, TRUE);
      $html = $decoded['html'] ?? $html;
    }
    $matches = [];
    \preg_match_all('/(canvas-start-)(.*?)[\/ \t](.*?)(-->)(.*?)/', $html, $matches);
    return $matches[2];
  }

  protected function assertResponseAutoSaves(Response $response, array $expectedEntities, bool $expectRegions = FALSE): void {
    if ($expectRegions) {
      $expectedEntities += PageRegion::loadForActiveTheme();
    }
    $data = self::decodeResponse($response);
    self::assertArrayHasKey('autoSaves', $data);
    self::assertIsArray($data['autoSaves']);
    self::assertCount(\count($expectedEntities), $data['autoSaves']);
    self::assertCount(\count($expectedEntities), array_filter($data['autoSaves']));
    foreach ($expectedEntities as $entity) {
      self::assertArrayHasKey(AutoSaveManager::getAutoSaveKey($entity), $data['autoSaves']);
      self::assertSame(
        $data['autoSaves'][AutoSaveManager::getAutoSaveKey($entity)],
        $this->container->get(AutoSaveManager::class)->getClientAutoSaveData($entity),
      );
    }
  }

  /**
   * Gets a test entity of the given type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|\Drupal\canvas\Entity\ContentTemplate
   *   The test entity.
   *
   * @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup::setup()
   */
  protected function getTestEntity(string $entity_type_id): ContentEntityInterface|ContentTemplate {
    if ($entity_type_id === 'node') {
      $entity = Node::load(1);
      $this->previewEntity = NULL;
    }
    elseif ($entity_type_id === ContentTemplate::ENTITY_TYPE_ID) {
      $entity = ContentTemplate::load('node.article.full');
      $this->previewEntity = Node::load(1);
    }
    else {
      throw new \InvalidArgumentException('Unsupported entity type: ' . $entity_type_id);
    }
    self::assertNotNull($entity);
    return $entity;
  }

  public static function providerEntityTypes(): array {
    return [
      'Content entity type with component tree: Node' => ['node'],
      'Config entity type with component tree: ContentTemplate' => [ContentTemplate::ENTITY_TYPE_ID],
    ];
  }

}
