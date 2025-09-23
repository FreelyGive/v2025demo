<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\AutoSave;

use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Page;
use Drupal\Tests\canvas\Kernel\ApiLayoutControllerTestBase;
use Symfony\Component\HttpFoundation\Request;

final class AutoSaveConflictPageLayoutTest extends ApiLayoutControllerTestBase {

  use AutoSaveConflictTestTrait;

  protected function setUpEntity(): void {
    $this->entity = Page::create([
      'title' => 'Test page',
      'status' => FALSE,
      'components' => [],
    ]);
    $this->entity->save();
  }

  protected static function getPermissions(): array {
    return [Page::CREATE_PERMISSION, Page::EDIT_PERMISSION, AutoSaveManager::PUBLISH_PERMISSION];
  }

  protected function modifyJsonToSendAsAutoSave(array &$json, string $text): void {
    $json['entity_form_fields']['title[0][value]'] = $text;
  }

  protected function assertCurrentAutoSaveText(string $text): void {
    $page = $this->getAutoSaveManager()->getAutoSaveEntity($this->entity)->entity;
    self::assertInstanceOf(Page::class, $page);
    self::assertSame($text, $page->label());
  }

  protected function getAutoSaveUrl(): string {
    return Url::fromRoute('canvas.api.layout.get', [
      'entity' => $this->entity->id(),
      'entity_type' => Page::ENTITY_TYPE_ID,
    ])->toString();
  }

  protected function getUpdateAutoSaveRequest(array $json): Request {
    return Request::create($this->getAutoSaveUrl(), method: 'POST', content: $this->filterLayoutForPost(json_encode($json, JSON_THROW_ON_ERROR)));
  }

}
