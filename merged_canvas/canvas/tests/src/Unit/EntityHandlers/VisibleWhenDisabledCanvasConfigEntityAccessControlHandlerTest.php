<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit\EntityHandlers;

use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Access\CanvasUiAccessCheck;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\EntityHandlers\VisibleWhenDisabledCanvasConfigEntityAccessControlHandler;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\canvas\EntityHandlers\VisibleWhenDisabledCanvasConfigEntityAccessControlHandler
 * @group canvas
 */
final class VisibleWhenDisabledCanvasConfigEntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * @covers ::checkAccess
   * @dataProvider viewPermissionProvider
   */
  public function testCanViewWithoutCheckingPermissions(string $entityTypeId, string $entityTypeLabel, bool $status, bool $hasAccessToCanvas, string $permission, string $expectedAccessResult): void {
    $cacheContextsManager = $this->prophesize(CacheContextsManager::class);
    $cacheContextsManager->assertValidTokens(['user.roles:authenticated'])->willReturn(TRUE);
    $cacheContextsManager->assertValidTokens(['user.permissions'])->willReturn(TRUE);
    $cacheContextsManager->assertValidTokens(['user.roles:authenticated', 'context:one', 'context:two'])->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cacheContextsManager->reveal());
    \Drupal::setContainer($container);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->any())->method('invokeAll')->willReturn([]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->expects($this->never())->method('getAdminPermission')->willReturn($permission);
    $entity = $this->createMock(ConfigEntityInterface::class);
    $entity->expects($this->never())
      ->method('status');
    $configManager = $this->createMock(ConfigManagerInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())->method('getId')->willReturn('en');
    $entity->expects($this->any())->method('language')->willReturn($language);

    $canvasUiAccessCheck = $this->createMock(CanvasUiAccessCheck::class);
    $canvasUiAccessCheck->expects($this->once())
      ->method('access')
      ->willReturn($hasAccessToCanvas ? (new AccessResultAllowed())->addCacheContexts(['user.permissions']) : (new AccessResultNeutral())->addCacheContexts(['user.permissions']));

    $component_audit_service = new ComponentAudit($configManager, $this->createMock(EntityTypeManagerInterface::class), $this->createMock(EntityFieldManagerInterface::class), $this->createMock(AutoSaveManager::class));
    $sut = new VisibleWhenDisabledCanvasConfigEntityAccessControlHandler(
      $entityType,
      $configManager,
      $this->createMock(EntityTypeManagerInterface::class),
      $canvasUiAccessCheck,
      $component_audit_service
    );
    $sut->setModuleHandler($moduleHandler);
    $result = $sut->access($entity, 'view', $account, TRUE);
    $this->assertTrue($result::class == $expectedAccessResult);
    assert($result instanceof RefinableCacheableDependencyInterface);
    $this->assertSame(['user.permissions'], $result->getCacheContexts());
    $this->assertSame([], $result->getCacheTags());
    $this->assertSame(Cache::PERMANENT, $result->getCacheMaxAge());
  }

  public static function viewPermissionProvider(): array {
    return [
      'js_component, enabled, authenticated is allowed' => [JavaScriptComponent::ENTITY_TYPE_ID, 'js_component', TRUE, TRUE, JavaScriptComponent::ADMIN_PERMISSION, AccessResultAllowed::class],
      'js_component, disabled, authenticated is allowed' => [JavaScriptComponent::ENTITY_TYPE_ID, 'js_component', FALSE, TRUE, JavaScriptComponent::ADMIN_PERMISSION, AccessResultAllowed::class],
      'js_component, enabled, not authenticated is neutral' => [JavaScriptComponent::ENTITY_TYPE_ID, 'js_component', TRUE, FALSE, JavaScriptComponent::ADMIN_PERMISSION, AccessResultNeutral::class],
      'js_component, disabled, not authenticated is neutral' => [JavaScriptComponent::ENTITY_TYPE_ID, 'js_component', FALSE, FALSE, JavaScriptComponent::ADMIN_PERMISSION, AccessResultNeutral::class],

      'content_template, enabled, authenticated is allowed' => [ContentTemplate::ENTITY_TYPE_ID, 'content_template', TRUE, TRUE, ContentTemplate::ADMIN_PERMISSION, AccessResultAllowed::class],
      'content_template, disabled, authenticated is allowed' => [ContentTemplate::ENTITY_TYPE_ID, 'content_template', FALSE, TRUE, ContentTemplate::ADMIN_PERMISSION, AccessResultAllowed::class],
      'content_template, enabled, not authenticated is neutral' => [ContentTemplate::ENTITY_TYPE_ID, 'content_template', TRUE, FALSE, ContentTemplate::ADMIN_PERMISSION, AccessResultNeutral::class],
      'content_template, disabled, not authenticated is neutral' => [ContentTemplate::ENTITY_TYPE_ID, 'content_template', FALSE, FALSE, ContentTemplate::ADMIN_PERMISSION, AccessResultNeutral::class],
    ];
  }

}
