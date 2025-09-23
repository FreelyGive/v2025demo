<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit\EntityHandlers;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultReasonInterface;
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
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\EntityHandlers\CanvasConfigEntityAccessControlHandler;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\canvas\EntityHandlers\CanvasConfigEntityAccessControlHandler
 * @group canvas
 */
final class CanvasConfigEntityAccessControlHandlerTest extends UnitTestCase {

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
      // Ensure there is a dependent other than a Component config entity.
      ->willReturn($hasDependents ? [new ConfigEntityDependency('one_dependent', [])] : []);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getDefinition(Component::ENTITY_TYPE_ID)->willReturn(new ConfigEntityType([
      'id' => Component::ENTITY_TYPE_ID,
      'provider' => 'canvas',
      'config_prefix' => 'component',
    ]));
    $sut = new CanvasConfigEntityAccessControlHandler($entityType, $configManager, $entityTypeManager->reveal());
    $sut->setModuleHandler($moduleHandler);
    $result = $sut->access($entity, 'delete', $account, TRUE);
    $this->assertTrue($result::class == $expectedAccessResult);
    if ($result instanceof AccessResultReasonInterface) {
      $this->assertSame($expectedErrorReason, $result->getReason());
    }
  }

  public static function dependentsProvider(): array {
    return [
      ['asset_library', 'in-browser code library', TRUE, AccessResultForbidden::class, 'There is other configuration depending on this in-browser code library.'],
      ['asset_library', 'in-browser code library', FALSE, AccessResultAllowed::class, NULL],
    ];
  }

}
