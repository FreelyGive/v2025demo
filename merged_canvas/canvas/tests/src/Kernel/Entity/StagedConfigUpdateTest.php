<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Recipe\InvalidConfigException;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\StagedConfigUpdate;
use Drupal\canvas\EntityHandlers\StagedConfigUpdateStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(StagedConfigUpdate::class)]
#[CoversClass(StagedConfigUpdateStorage::class)]
final class StagedConfigUpdateTest extends KernelTestBase implements ServiceModifierInterface {

  use UserCreationTrait;

  protected bool $usesSuperUserAccessPolicy = FALSE;

  protected static $modules = [
    'canvas',
    'user',
    'system',
    'datetime',
    'file',
    'image',
    'options',
    'path',
    'link',
    'media',
    'system',
  ];

  private bool $markSystemSiteFullyValidated = FALSE;

  /**
   * {@inheritdoc}
   *
   * Allow marking `system.site` as FullyValidatable for this test.
   *
   * @see testSavingWhichLeadsToInvalidSchema()
   * @see makeSystemSiteValidated()
   */
  public function alter(ContainerBuilder $container): void {
    $container->register(self::class)
      ->setClass(self::class)
      ->addTag('kernel.event_listener', [
        'event' => 'drupal_hook.config_schema_info_alter',
        'method' => 'makeSystemSiteValidated',
        'priority' => 0,
      ]);
    $container->set(self::class, $this);

    $map = $container->getParameter('hook_implementations_map');
    self::assertIsArray($map);
    $map['config_schema_info_alter'][StagedConfigUpdateTest::class] = [
      'makeSystemSiteValidated' => 'canvas',
    ];
    $container->setParameter('hook_implementations_map', $map);
  }

  /**
   * Used to mark `system.site` as FullyValidatable.
   *
   * @see testSavingWhichLeadsToInvalidSchema()
   * @see https://www.drupal.org/project/drupal/issues/3443432
   */
  public function makeSystemSiteValidated(array &$definitions): void {
    if ($this->markSystemSiteFullyValidated === TRUE) {
      $definitions['system.site']['constraints']['FullyValidatable'] = NULL;
    }
  }

  public function testStorageWithAutoSaveManager(): void {
    $auto_save_manager = $this->container->get(AutoSaveManager::class);
    self::assertInstanceOf(AutoSaveManager::class, $auto_save_manager);

    $storage = $this->container->get('entity_type.manager')
      ->getStorage(StagedConfigUpdate::ENTITY_TYPE_ID);
    $sut = $storage->create([
      'id' => 'test_staged_config_update',
      'label' => 'Test Update',
      'target' => 'system.site',
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => ['key' => 'value'],
        ],
      ],
    ]);
    self::assertInstanceOf(StagedConfigUpdate::class, $sut);
    self::assertEquals('test_staged_config_update', $sut->id());
    self::assertFalse($sut->status());
    self::assertEquals('Test Update', $sut->label());
    self::assertEquals('system.site', $sut->getTarget());

    self::assertEquals([
      [
        'name' => 'simpleConfigUpdate',
        'input' => ['key' => 'value'],
      ],
    ], $sut->getActions());

    self::assertCount(0, $auto_save_manager->getAllAutoSaveList());
    self::assertNull($storage->load($sut->id()));

    $sut->save();
    self::assertCount(1, $auto_save_manager->getAllAutoSaveList());

    $sut->set('label', 'Test Update Modified');
    $sut->save();

    $loaded = $storage->load($sut->id());
    self::assertEquals($sut, $loaded);

    $sut->delete();
    self::assertCount(0, $auto_save_manager->getAllAutoSaveList());
  }

  public function testCreateFromClientSide(): void {
    $sut = StagedConfigUpdate::createFromClientSide([
      'id' => 'test_staged_config_update',
      'label' => 'Test Update',
      'target' => 'system.site',
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => ['key' => 'value'],
        ],
      ],
    ]);
    self::assertEquals('test_staged_config_update', $sut->id());
    self::assertFalse($sut->status());
    self::assertEquals('Test Update', $sut->label());
    self::assertEquals('system.site', $sut->getTarget());
    self::assertEquals([
      [
        'name' => 'simpleConfigUpdate',
        'input' => ['key' => 'value'],
      ],
    ], $sut->getActions());
  }

  public function testNormalizeForClientSide(): void {
    $sut = $this->container->get('entity_type.manager')
      ->getStorage(StagedConfigUpdate::ENTITY_TYPE_ID)
      ->create([
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => ['key' => 'value'],
          ],
        ],
      ]);
    self::assertInstanceOf(StagedConfigUpdate::class, $sut);
    self::assertEquals([
      'id' => 'test_staged_config_update',
      'label' => 'Test Update',
      'target' => 'system.site',
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => ['key' => 'value'],
        ],
      ],
    ], $sut->normalizeForClientSide()->values);
  }

  public function testUpdateFromClientSide(): void {
    $sut = $this->container->get('entity_type.manager')
      ->getStorage(StagedConfigUpdate::ENTITY_TYPE_ID)
      ->create([
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => ['key' => 'value'],
          ],
        ],
      ]);
    self::assertInstanceOf(StagedConfigUpdate::class, $sut);
    self::assertFalse($sut->status());

    $sut->updateFromClientSide([
      'label' => 'Updated Test',
      'target' => 'test.target.updated',
      'status' => TRUE,
      'actions' => [
        [
          'name' => 'complexConfigUpdate',
          'input' => ['new_key' => 'new_value'],
        ],
      ],
    ]);
    // Verify the client cannot change the status, target, or ID.
    self::assertEquals('test_staged_config_update', $sut->id());
    self::assertFalse($sut->status());
    self::assertEquals('Updated Test', $sut->label());
    self::assertEquals('system.site', $sut->getTarget());
    self::assertEquals([
      [
        'name' => 'complexConfigUpdate',
        'input' => ['new_key' => 'new_value'],
      ],
    ], $sut->getActions());

    $sut->updateFromClientSide([
      'label' => 'Updated Test',
      'target' => 'test.target.updated',
      'status' => TRUE,
      'actions' => [
        [
          'name' => 'simpleConfigUpdate',
          'input' => ['key' => 'value'],
        ],
        [
          'name' => 'complexConfigUpdate',
          'input' => ['new_key' => 'new_value'],
        ],
      ],
    ]);
    self::assertEquals([
      [
        'name' => 'simpleConfigUpdate',
        'input' => ['key' => 'value'],
      ],
      [
        'name' => 'complexConfigUpdate',
        'input' => ['new_key' => 'new_value'],
      ],
    ], $sut->getActions());
  }

  private function assertSiteConfig(array $expected): void {
    $expected = NestedArray::mergeDeepArray([
      [
        '_core' => [
          'default_config_hash' => 'HlN7eAN2N4JIHsYv56V4E7sqC9bS609KwvGFjyD_mgk',
        ],
        'langcode' => 'en',
        'uuid' => '',
        'name' => '',
        'mail' => '',
        'slogan' => '',
        'page' => [
          '403' => '',
          '404' => '',
          'front' => '/user/login',
        ],
        'admin_compact_mode' => FALSE,
        'weight_select_max' => 100,
        'default_langcode' => 'en',
        'mail_notification' => NULL,
      ],
      $expected,
    ], TRUE);
    self::assertSame($expected, $this->config('system.site')->get());
  }

  public function testSavingUpdatesConfig(): void {
    $this->installConfig(['system']);
    $this->assertSiteConfig([]);

    $sut = $this->container->get('entity_type.manager')
      ->getStorage(StagedConfigUpdate::ENTITY_TYPE_ID)
      ->create([
        'id' => 'canvas_change_site_name',
        'label' => 'Change the site name',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => [
              'name' => 'My awesome site',
              'page.front' => '/home',
            ],
          ],
        ],
      ]);
    $sut->save();
    self::assertInstanceOf(StagedConfigUpdate::class, $sut);

    $this->assertSiteConfig([]);

    $sut->enable()->save();

    $this->assertSiteConfig([
      'name' => 'My awesome site',
      'page' => [
        '403' => '',
        '404' => '',
        'front' => '/home',
      ],
    ]);
  }

  /**
   * ConfigActions allow saving data which is invalid.
   *
   * A config action is allowed to save a configuration object, but then the
   * configuration action manager validates the configuration object. This
   * can cause an exception to be thrown even though the configuration is wrong.
   *
   * This test proves this case and the need for ApiAutoSaveController::post to
   * use a database transaction to roll back the changes if an exception is
   * thrown.
   */
  public function testSavingWhichLeadsToInvalidSchema(): void {
    $this->markSystemSiteFullyValidated = TRUE;
    $this->installConfig(['system']);
    $this->assertSiteConfig([]);

    $sut = $this->container->get('entity_type.manager')
      ->getStorage(StagedConfigUpdate::ENTITY_TYPE_ID)
      ->create([
        'id' => 'canvas_change_site_email',
        'label' => 'Change the site email',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => [
              'mail' => 'not.a.valid.email',
              'mail_notification' => '',
            ],
          ],
        ],
      ]);
    $sut->save();
    self::assertInstanceOf(StagedConfigUpdate::class, $sut);

    $this->assertSiteConfig([]);

    try {
      $sut->enable()->save();
      $this->fail('Expected InvalidConfigException to be thrown');
    }
    catch (InvalidConfigException $e) {
      self::assertEquals(
        '<em class="placeholder">&quot;not.a.valid.email&quot;</em> is not a valid email address.',
        (string) $e->violations->get(0)->getMessage()
      );
    }

    $this->assertSiteConfig([
      'mail' => 'not.a.valid.email',
      'mail_notification' => '',
    ]);
  }

  public function testCacheability(): void {
    $sut = $this->container->get('entity_type.manager')
      ->getStorage(StagedConfigUpdate::ENTITY_TYPE_ID)
      ->create([
        'id' => 'canvas_change_site_name',
        'label' => 'Change the site name',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => ['name' => 'My awesome site'],
          ],
        ],
      ]);
    $cacheability = CacheableMetadata::createFromObject($sut);
    self::assertEquals([], $cacheability->getCacheContexts());
    self::assertEquals(Cache::PERMANENT, $cacheability->getCacheMaxAge());
    self::assertEquals(
      ['config:system.site'],
      $cacheability->getCacheTags()
    );
  }

  #[DataProvider('accessProvider')]
  public function testAccess(array $data, array $permissions, string $op, bool $allowed, string $reason): void {
    $this->installConfig(['system']);

    $this->installEntitySchema('user');
    $account = $this->createUser($permissions);
    self::assertNotFalse($account);

    $sut = $this->container->get(EntityTypeManagerInterface::class)
      ->getAccessControlHandler(StagedConfigUpdate::ENTITY_TYPE_ID);

    $entity = StagedConfigUpdate::create($data);
    $entity->save();

    $result = $sut->access($entity, $op, $account, TRUE);
    self::assertEquals(
      $allowed,
      $result->isAllowed()
    );
    self::assertEquals($reason, $result instanceof AccessResultReasonInterface ? $result->getReason() : '');
  }

  public static function accessProvider(): \Generator {
    yield 'update: valid' => [
      [
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => ['key' => 'value'],
          ],
        ],
      ],
      [
        'administer site configuration',
      ],
      'update',
      TRUE,
      '',
    ];
    yield 'cannot modify config entity type config' => [
      [
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'core.date_format.medium',
        'actions' => [
          [
            'name' => 'disable',
            'input' => [],
          ],
        ],
      ],
      [],
      'update',
      FALSE,
      "The 'administer site configuration' permission is required.",
    ];
    yield 'cannot modify unsupported simple config' => [
      [
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'system.date',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => ['default.default' => 'America/Chicago'],
          ],
        ],
      ],
      [
        'administer site configuration',
      ],
      'update',
      FALSE,
      'Unsupported simple configuration object.',
    ];
    yield 'delete: valid' => [
      [
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => ['key' => 'value'],
          ],
        ],
      ],
      [
        'administer site configuration',
      ],
      'delete',
      TRUE,
      '',
    ];
    yield 'delete: invalid' => [
      [
        'id' => 'test_staged_config_update',
        'label' => 'Test Update',
        'target' => 'system.site',
        'actions' => [
          [
            'name' => 'simpleConfigUpdate',
            'input' => ['key' => 'value'],
          ],
        ],
      ],
      [],
      'delete',
      FALSE,
      "The 'administer site configuration' permission is required.",
    ];
  }

}
