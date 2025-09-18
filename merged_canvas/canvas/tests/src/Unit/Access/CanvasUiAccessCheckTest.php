<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit\Access;

use Drupal\canvas\Access\CanvasUiAccessCheck;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\canvas\Entity\Pattern;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\canvas\Access\CanvasUiAccessCheck
 * @group canvas
 */
class CanvasUiAccessCheckTest extends UnitTestCase {

  /**
   * Tests access based on some permissions.
   *
   * @param ?string $permission
   * @param bool $accessGranted
   *
   * @covers ::access
   * @dataProvider provider
   */
  public function testAccess(?string $permission, bool $accessGranted): void {
    $cacheContextsManager = $this->prophesize(CacheContextsManager::class);
    $cacheContextsManager->assertValidTokens(['user.permissions'])->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cacheContextsManager->reveal());
    \Drupal::setContainer($container);

    $entityFieldManager = $this->prophesize(EntityFieldManagerInterface::class);
    // We purposely return no fields, because that requires mocking a lot of the stack.
    $entityFieldManager
      ->getFieldMapByFieldType(ComponentTreeItem::PLUGIN_ID)
      ->willReturn([]);
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $account = $this->createMock(AccountInterface::class);
    if ($permission) {
      $account->expects($this->atLeastOnce())
        ->method('hasPermission')
        ->willReturnCallback(fn (string $argPermission) => match ($argPermission) {
          $permission => TRUE,
          default => FALSE
        });
    }
    $accessChecker = new CanvasUiAccessCheck($entityFieldManager->reveal(), $entityTypeManager->reveal());
    $result = $accessChecker->access($account);
    $this->assertEquals($accessGranted, $result->isAllowed());
  }

  /**
   * Data provider for testing access based on permissions.
   */
  public static function provider(): array {
    return [
      [NULL, FALSE],
      [Pattern::ADMIN_PERMISSION, FALSE],
      [JavaScriptComponent::ADMIN_PERMISSION, TRUE],
      [ContentTemplate::ADMIN_PERMISSION, TRUE],
    ];
  }

}
