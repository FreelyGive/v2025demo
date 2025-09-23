<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\AutoSave;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\Tests\canvas\Kernel\ApiLayoutControllerTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests auto-save conflict handling for content templates.
 *
 * @see \Drupal\canvas\Entity\PageRegion
 * @covers \Drupal\canvas\Controller\ApiLayoutController::get()
 * @group canvas
 */
class AutoSaveConflictContentTemplateLayoutTest extends ApiLayoutControllerTestBase {

  use AutoSaveConflictTestTrait;

  protected static function getPermissions(): array {
    return [
      ContentTemplate::ADMIN_PERMISSION,
      AutoSaveManager::PUBLISH_PERMISSION,
      'edit any article content',
    ];
  }

  protected function setUpEntity(): void {
    $this->entity = ContentTemplate::create([
      'content_entity_type_id' => 'node',
      'content_entity_type_bundle' => 'article',
      'content_entity_type_view_mode' => 'full',
      // Create a tree with only a single heading component.
      'component_tree' => [
        [
          'uuid' => '09365c2d-1ee1-47fd-b5a3-7e4f34866186',
          'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
          'component_version' => '95f4f1d5ee47663b',
          'inputs' => ['heading' => 'Hello, world!'],
        ],
      ],
    ]);
    $violations = $this->entity->getTypedData()->validate();
    $this->assertCount(0, $violations, "Expected no violations, found: " . $violations);
    $this->entity->save();
    $this->previewEntity = Node::create([
      'type' => 'article',
      'title' => 'Test node',
    ]);
    $this->previewEntity->save();
  }

  protected function modifyJsonToSendAsAutoSave(array &$json, string $text): void {
    self::assertCount(1, $json['model']);
    $uuid = array_keys($json['model'])[0];
    $json['model'][$uuid]['resolved']['heading'] = $text;
  }

  protected function assertCurrentAutoSaveText(string $text): void {
    $contentTemplate = $this->getAutoSaveManager()->getAutoSaveEntity($this->entity)->entity;
    self::assertInstanceOf(ContentTemplate::class, $contentTemplate);
    $inputs = $contentTemplate->getComponentTree()->first()?->getInputs();
    self::assertIsArray($inputs);
    self::assertArrayHasKey('heading', $inputs);
    self::assertSame($text, $inputs['heading']);
  }

  protected function getUpdateAutoSaveRequest(array $json): Request {
    return Request::create($this->getAutoSaveUrl(), method: 'POST', content: $this->filterLayoutForPost(json_encode($json, JSON_THROW_ON_ERROR)));
  }

  protected function getAutoSaveUrl(): string {
    self::assertInstanceOf(ContentEntityInterface::class, $this->previewEntity);
    return Url::fromRoute('canvas.api.layout.get.content_template', [
      'entity' => $this->entity->id(),
      'preview_entity' => $this->previewEntity->id(),
    ])->toString();
  }

}
