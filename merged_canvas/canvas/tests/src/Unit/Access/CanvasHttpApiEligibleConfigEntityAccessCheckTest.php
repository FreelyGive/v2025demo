<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit\Access;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\canvas\Access\CanvasHttpApiEligibleConfigEntityAccessCheck;
use Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\canvas\Access\CanvasHttpApiEligibleConfigEntityAccessCheck
 * @group canvas
 */
class CanvasHttpApiEligibleConfigEntityAccessCheckTest extends UnitTestCase {

  protected RouteMatchInterface $routeMatch;

  protected function setUp(): void {
    parent::setUp();
    $routeMatch = $this->prophesize(RouteMatchInterface::class);
    $routeMatch->getParameter('canvas_config_entity_type_id')->willReturn('my_entity_type');
    $this->routeMatch = $routeMatch->reveal();
  }

  /**
   * Tests access based on entity type.
   *
   * @param class-string $className
   * @param bool $accessGranted
   *
   * @covers ::access
   * @dataProvider provider
   */
  public function testAccess(string $className, bool $accessGranted): void {
    $entityType = $this->prophesize($className);
    $entityType->willImplement(ConfigEntityTypeInterface::class);
    $entityType->getClass()->willReturn($className);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getDefinition('my_entity_type')->willReturn($entityType);

    $access = new CanvasHttpApiEligibleConfigEntityAccessCheck($entityTypeManager->reveal());
    $result = $access->access($this->routeMatch);
    $this->assertEquals($accessGranted, $result->isAllowed());
  }

  /**
   * Data provider for testing access based on the entity type.
   */
  public static function provider(): array {
    return [
      [CanvasHttpApiEligibleConfigEntityInterface::class, TRUE],
      [ConfigEntityInterface::class, FALSE],
    ];
  }

}
