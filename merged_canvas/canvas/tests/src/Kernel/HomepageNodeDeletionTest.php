<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * @group canvas
 */
class HomepageNodeDeletionTest extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;
  use GenerateComponentConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'canvas',
    'system',
    'user',
    'media',
    'field',
    'link',
    'text',
    'datetime',
    'file',
    'image',
    'options',
    'path',
    'media',
    'canvas_test_sdc',
    'canvas_test_config_node_article',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->generateComponentConfig();
    $this->installConfig('canvas_test_config_node_article');
  }

  public function testNodeForDeleteOperationInList(): void {
    // Create a node to be used in the test.
    $node = $this->createNode(['type' => 'article', 'title' => 'Test Node']);
    $this->config('system.site')
      ->set('page.front', '/' . $node->toUrl()->getInternalPath())
      ->save();

    $user = $this->createUser(['administer nodes', 'access content'], 'administer_node_user');
    assert($user instanceof AccountInterface);
    $access = $node->access('delete', $user, TRUE);
    $this->assertInstanceOf(AccessResultForbidden::class, $access);
    $this->assertSame('This entity cannot be deleted because its path is set as the homepage.', $access->getReason());
    $this->assertSame(['config:system.site', 'node:1'], $access->getCacheTags());
    // Provide bypass node access permission to the user.
    $user2 = $this->createUser(['bypass node access'], 'bypass_node_access_user');
    assert($user2 instanceof AccountInterface);
    $access = $node->access('delete', $user2, TRUE);
    $this->assertInstanceOf(AccessResult::class, $access);
    $this->assertTrue($access->isAllowed(), 'Bypass node access permission allows deletion of the node.');
    $this->assertSame([], $access->getCacheTags());
    // Check neutral status has system.site config cache tag too.
    $node = $this->createNode(['type' => 'article', 'title' => 'Test Node not-homepage']);
    $access = $node->access('delete', $user, TRUE);
    $this->assertInstanceOf(AccessResult::class, $access);
    $this->assertTrue($access->isNeutral(), 'Access in neutral.');
    $this->assertSame(['config:system.site'], $access->getCacheTags());
  }

}
