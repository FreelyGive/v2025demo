<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\AutoSave;

use Drupal\Core\Url;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\PageRegion;
use Drupal\Tests\canvas\Kernel\ApiLayoutControllerTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests auto-save conflict handling for page regions.
 *
 * @see \Drupal\canvas\Entity\PageRegion
 * @covers \Drupal\canvas\Controller\ApiLayoutController::get()
 * @group canvas
 */
final class AutoSaveConflictPageRegionLayoutTest extends ApiLayoutControllerTestBase {

  use AutoSaveConflictTestTrait;

  private Page $page;

  protected function setUpEntity(): void {
    $this->page = Page::create([
      'title' => 'Test page',
      'status' => FALSE,
      'components' => [],
    ]);
    $this->page->save();
    $regions = PageRegion::createFromBlockLayout('stark');
    foreach ($regions as $region) {
      $region->save();
    }
    $sideBarRegion = PageRegion::load('stark.sidebar_first');
    assert($sideBarRegion instanceof PageRegion);
    $this->entity = $sideBarRegion;
  }

  protected static function getPermissions(): array {
    return [
      Page::CREATE_PERMISSION,
      Page::EDIT_PERMISSION,
      AutoSaveManager::PUBLISH_PERMISSION,
      PageRegion::ADMIN_PERMISSION,
    ];
  }

  protected function modifyJsonToSendAsAutoSave(array &$json, string $text): void {
    // Find the sidebar_first region.
    $regions = array_filter($json['layout'], fn ($region) =>
      $region['nodeType'] === 'region'
      && $region['id'] === 'sidebar_first'
    );
    self::assertCount(1, $regions);
    $region = reset($regions);
    // Assert the first component is the system messages block.
    self::assertStringStartsWith('block.system_messages_block@', $region['components'][0]['type']);
    $uuid = $region['components'][0]['uuid'];
    // The system messages block should have a label we can update.
    \assert(isset($json['model'][$uuid]['resolved']['label']));
    $json['model'][$uuid]['resolved']['label'] = $text;
  }

  protected function assertCurrentAutoSaveText(string $text): void {
    $autoSaveManager = $this->container->get(AutoSaveManager::class);
    \assert($autoSaveManager instanceof AutoSaveManager);
    $region = $autoSaveManager->getAutoSaveEntity($this->entity)->entity;
    \assert($region instanceof PageRegion);
    $regionTree = $region->getComponentTree()->getValue();
    // Assert the first component is the system messages block which is
    // component whose label we updated.
    // @see ::updateJson()
    self::assertSame('block.system_messages_block', $regionTree[0]['component_id']);
    $decoded_inputs = json_decode($regionTree[0]['inputs'], TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($decoded_inputs);
    self::assertArrayHasKey('label', $decoded_inputs);
    self::assertSame($text, $decoded_inputs['label']);
  }

  public function testRegionPermissionsNeeded(): void {
    $response = $this->request(Request::create($this->getAutoSaveUrl()));
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    $getJson = self::decodeResponse($response);
    // If we try to post back the same `autoSaves` including the regions
    // when the user does not have access to 'update' regions, we should get
    // a conflict.
    $permissions = array_diff(self::getPermissions(), [PageRegion::ADMIN_PERMISSION]);
    $this->setUpCurrentUser(permissions: $permissions);
    $this->assertRequestAutoSaveConflict($this->getUpdateAutoSaveRequest($getJson));

    $this->setUpCurrentUser(permissions: self::getPermissions());

    $getJsonMissingRegion = $getJson;
    unset($getJsonMissingRegion['autoSaves']['page_region:stark.sidebar_second']);
    $this->assertRequestAutoSaveConflict($this->getUpdateAutoSaveRequest($getJsonMissingRegion));
  }

  protected function getAutoSaveUrl(): string {
    return Url::fromRoute('canvas.api.layout.get', [
      'entity' => $this->page->id(),
      'entity_type' => Page::ENTITY_TYPE_ID,
    ])->toString();
  }

  protected function getUpdateAutoSaveRequest(array $json): Request {
    return Request::create($this->getAutoSaveUrl(), method: 'POST', content: $this->filterLayoutForPost(json_encode($json, JSON_THROW_ON_ERROR)));
  }

}
