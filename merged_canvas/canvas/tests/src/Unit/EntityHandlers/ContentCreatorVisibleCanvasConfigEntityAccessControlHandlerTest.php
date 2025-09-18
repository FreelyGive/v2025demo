<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit\EntityHandlers;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigDependencyManager;
use Drupal\Core\Config\Entity\ConfigEntityDependency;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Access\CanvasUiAccessCheck;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Entity\Pattern;
use Drupal\canvas\EntityHandlers\ContentCreatorVisibleCanvasConfigEntityAccessControlHandler;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\canvas\EntityHandlers\ContentCreatorVisibleCanvasConfigEntityAccessControlHandler
 * @group canvas
 */
final class ContentCreatorVisibleCanvasConfigEntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * @covers ::checkAccess
   * @dataProvider viewPermissionProvider
   */
  public function testCanViewWithoutCheckingPermissions(string $entityTypeId, string $entityTypeLabel, bool $status, bool $hasAccessToCanvas, string $permission, string $expectedAccessResult): void {
    $cacheContextsManager = $this->prophesize(CacheContextsManager::class);
    $cacheContextsManager->assertValidTokens(['user.permissions'])->willReturn(TRUE);
    $cacheContextsManager->assertValidTokens(['context:one', 'context:two'])->willReturn(TRUE);
    $cacheContextsManager->assertValidTokens(['user.permissions', 'context:one', 'context:two'])->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cacheContextsManager->reveal());
    \Drupal::setContainer($container);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->any())->method('invokeAll')->willReturn([]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->expects($this->never())->method('getAdminPermission')->willReturn($permission);
    $entity = $this->createMock(ConfigEntityInterface::class);

    $entity->expects($this->once())
      ->method('status')
      ->willReturn($status);
    $entity->expects($this->once())
      ->method('getCacheContexts')
      ->willReturn(['context:one', 'context:two']);
    $entity->expects($this->once())
      ->method('getCacheTags')
      ->willReturn(['tag:one', 'tag:two']);
    $entity->expects($this->once())
      ->method('getCacheMaxAge')
      ->willReturn(Cache::PERMANENT);
    $configManager = $this->createMock(ConfigManagerInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->never())->method('hasPermission')->willReturn(TRUE);
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())->method('getId')->willReturn('en');
    $entity->expects($this->any())->method('language')->willReturn($language);

    $canvasUiAccessCheck = $this->createMock(CanvasUiAccessCheck::class);
    $canvasUiAccessCheck->expects($this->once())
      ->method('access')
      ->willReturn($hasAccessToCanvas ? (new AccessResultAllowed())->addCacheContexts(['user.permissions']) : (new AccessResultNeutral())->addCacheContexts(['user.permissions']));

    $sut = new ContentCreatorVisibleCanvasConfigEntityAccessControlHandler($entityType, $configManager, $this->prophesize(EntityTypeManagerInterface::class)->reveal(), $canvasUiAccessCheck);
    $sut->setModuleHandler($moduleHandler);
    $result = $sut->access($entity, 'view', $account, TRUE);
    $this->assertTrue($result::class == $expectedAccessResult);
  }

  public static function viewPermissionProvider(): array {
    return [
      'component, enabled, authenticated is allowed' => [Component::ENTITY_TYPE_ID, 'component', TRUE, TRUE, Component::ADMIN_PERMISSION, AccessResultAllowed::class],
      'pattern, enabled, authenticated is allowed' => [Pattern::ENTITY_TYPE_ID, 'pattern', TRUE, TRUE, Pattern::ADMIN_PERMISSION, AccessResultAllowed::class],
      'region, enabled, authenticated is allowed' => [PageRegion::ENTITY_TYPE_ID, 'page region', TRUE, TRUE, PageRegion::ADMIN_PERMISSION, AccessResultAllowed::class],
      'component, disabled, authenticated is neutral' => [Component::ENTITY_TYPE_ID, 'component', FALSE, TRUE, Component::ADMIN_PERMISSION, AccessResultNeutral::class],
      'pattern, disabled, authenticated is neutral' => [Pattern::ENTITY_TYPE_ID, 'pattern', FALSE, TRUE, Pattern::ADMIN_PERMISSION, AccessResultNeutral::class],
      'region, disabled, authenticated is neutral' => [PageRegion::ENTITY_TYPE_ID, 'page region', FALSE, TRUE, PageRegion::ADMIN_PERMISSION, AccessResultNeutral::class],
      'component, enabled, not authenticated is neutral' => [Component::ENTITY_TYPE_ID, 'component', TRUE, FALSE, PageRegion::ADMIN_PERMISSION, AccessResultNeutral::class],
      'component, disabled, not authenticated is neutral' => [PageRegion::ENTITY_TYPE_ID, 'page region', FALSE, FALSE, PageRegion::ADMIN_PERMISSION, AccessResultNeutral::class],
    ];
  }

  /**
   * @covers ::checkAccess
   * @dataProvider dependentsProvider
   */
  public function testCannotDeleteWhenThereAreDependents(string $entityTypeId, string $entityTypeLabel, bool $hasDependents, string $expectedAccessResult, ?string $expectedErrorReason): void {
    $cacheContextsManager = $this->prophesize(CacheContextsManager::class);
    $cacheContextsManager->assertValidTokens(['user.permissions'])->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cacheContextsManager->reveal());
    \Drupal::setContainer($container);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->any())->method('invokeAll')->willReturn([]);
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->expects($this->once())->method('getAdminPermission')->willReturn(JavaScriptComponent::ADMIN_PERMISSION);
    if ($hasDependents) {
      $entityType->expects($this->once())
        ->method('getSingularLabel')
        ->willReturn($entityTypeLabel);
    }

    $entity = $this->createMock(ConfigEntityInterface::class);
    $entity->expects($this->once())->method('getConfigDependencyName')->willReturn("canvas.$entityTypeId.test");
    $configManager = $this->createMock(ConfigManagerInterface::class);
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())->method('hasPermission')->willReturn(TRUE);
    $language = $this->createMock(LanguageInterface::class);
    $language->expects($this->any())->method('getId')->willReturn('en');
    $entity->expects($this->any())->method('language')->willReturn($language);
    $configDependencyManager = $this->createMock(ConfigDependencyManager::class);
    $configManager->expects($this->once())->method('getConfigDependencyManager')
      ->willReturn($configDependencyManager);

    $configDependencyManager->expects($this->any())->method('getDependentEntities')
      ->with('config', "canvas.$entityTypeId.test")
      ->willReturn($hasDependents ? [new ConfigEntityDependency('one_dependent', [])] : []);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getDefinition(Component::ENTITY_TYPE_ID)->willReturn(new ConfigEntityType([
      'id' => Component::ENTITY_TYPE_ID,
      'provider' => 'canvas',
      'config_prefix' => 'component',
    ]));
    $canvasUiAccessCheck = $this->prophesize(CanvasUiAccessCheck::class);
    $sut = new ContentCreatorVisibleCanvasConfigEntityAccessControlHandler($entityType, $configManager, $entityTypeManager->reveal(), $canvasUiAccessCheck->reveal());
    $sut->setModuleHandler($moduleHandler);
    $result = $sut->access($entity, 'delete', $account, TRUE);
    $this->assertTrue($result::class == $expectedAccessResult);
    if ($result instanceof AccessResultReasonInterface) {
      $this->assertSame($expectedErrorReason, $result->getReason());
    }
  }

  public static function dependentsProvider(): array {
    return [
      ['component', 'component', TRUE, AccessResultForbidden::class, 'There is other configuration depending on this component.'],
      ['component', 'component', FALSE, AccessResultAllowed::class, NULL],
      ['pattern', 'pattern', TRUE, AccessResultForbidden::class, 'There is other configuration depending on this pattern.'],
      ['pattern', 'pattern', FALSE, AccessResultAllowed::class, NULL],
      ['page region', 'page region', TRUE, AccessResultForbidden::class, 'There is other configuration depending on this page region.'],
      ['page region', 'page region', FALSE, AccessResultAllowed::class, NULL],
    ];
  }

}
