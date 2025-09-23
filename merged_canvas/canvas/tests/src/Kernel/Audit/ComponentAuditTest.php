<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Audit;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Entity\Pattern;

/**
 * @coversDefaultClass \Drupal\canvas\Audit\ComponentAudit
 * @group canvas
 * @todo Improve in
 *   https://www.drupal.org/project/canvas/issues/3522953
 */
class ComponentAuditTest extends ComponentAuditTestBase {

  /**
   * @covers ::getContentRevisionsUsingComponent
   */
  public function testGetContentRevisionsUsingComponent(): void {
    $audit = $this->container->get(ComponentAudit::class);
    $component = Component::load('sdc.canvas_test_sdc.my-cta');
    \assert($component instanceof ComponentInterface);
    self::assertCount(1, $component->getVersions());
    $old_version = $component->getActiveVersion();
    $content = $audit->getContentRevisionsUsingComponent($component);
    self::assertCount(0, $content);

    $page = Page::create([
      'title' => $this->randomMachineName(),
      'components' => $this->tree,
    ]);
    $page->save();
    $revisionId1 = $page->getRevisionId();
    $page->setNewRevision();
    $page->set('components', [])->save();
    $page->save();

    // Now enable the 'canvas_test_storage_prop_shape_alter' module to change the
    // field type used for populating the href prop.
    // @see \Drupal\canvas_test_storage_prop_shape_alter\Hook\CanvasTestStoragePropShapeAlterHooks::storagePropShapeAlter()
    \Drupal::service(ModuleInstallerInterface::class)
      ->install(['canvas_test_storage_prop_shape_alter']);
    $component = Component::load('sdc.canvas_test_sdc.my-cta');
    \assert($component instanceof ComponentInterface);
    self::assertCount(2, $component->getVersions());
    $new_version = $component->getActiveVersion();

    // 1. All versions.
    $content = $audit->getContentRevisionsUsingComponent($component);
    self::assertEquals([$page->uuid()], \array_map(static fn(ContentEntityInterface $page): string|null => $page->uuid(), $content));
    self::assertEquals([$revisionId1], \array_map(static fn(ContentEntityInterface $page): int|null|string => $page->getRevisionId(), $content));

    // 2. Active (i.e. new) version: no uses yet.
    $content = $audit->getContentRevisionsUsingComponent($component, [$new_version]);
    self::assertEquals([], \array_map(static fn(ContentEntityInterface $page): string|null => $page->uuid(), $content));
    self::assertEquals([], \array_map(static fn(ContentEntityInterface $page): int|null|string => $page->getRevisionId(), $content));

    // 3. Old version.
    $content = $audit->getContentRevisionsUsingComponent($component, [$old_version]);
    self::assertEquals([$page->uuid()], \array_map(static fn(ContentEntityInterface $page): string|null => $page->uuid(), $content));
    self::assertEquals([$revisionId1], \array_map(static fn(ContentEntityInterface $page): int|null|string => $page->getRevisionId(), $content));
  }

  protected function createTestPattern(array $tree): Pattern {
    $pattern = Pattern::create([
      'id' => 'test_pattern',
      'label' => 'Test Pattern',
      'component_tree' => $tree,
    ]);
    $pattern->save();
    return $pattern;
  }

  protected function createTestPageRegion(array $tree): PageRegion {
    $page_region = PageRegion::create([
      'theme' => 'stark',
      'region' => 'sidebar_first',
      'component_tree' => $tree,
    ]);
    $page_region->save();
    return $page_region;
  }

  protected function createTestContentTemplate(array $tree): ContentTemplate {
    $entity_type_id = Page::ENTITY_TYPE_ID;
    $view_mode = 'reverse';
    EntityViewMode::create([
      'id' => \implode('.', [$entity_type_id, $view_mode]),
      'label' => 'Reverse',
      'targetEntityType' => $entity_type_id,
    ])->save();
    $content_template = ContentTemplate::create([
      'id' => \implode('.', [$entity_type_id, $entity_type_id, $view_mode]),
      'content_entity_type_id' => $entity_type_id,
      'content_entity_type_bundle' => $entity_type_id,
      'content_entity_type_view_mode' => $view_mode,
      'component_tree' => $tree,
    ]);
    $content_template->save();
    return $content_template;
  }

  /**
   * @covers ::getConfigEntityDependenciesUsingComponent
   * @dataProvider configProvider
   */
  public function testGetConfigEntityDependenciesUsingComponent(string $config_entity_type_id): void {
    $audit = $this->container->get(ComponentAudit::class);
    $component = Component::load('sdc.canvas_test_sdc.my-cta');
    \assert($component instanceof ComponentInterface);
    self::assertCount(1, $component->getVersions());
    $old_version = $component->getActiveVersion();
    $config = $audit->getConfigEntityDependenciesUsingComponent($component, $config_entity_type_id);
    self::assertCount(0, $config);
    $entity = match ($config_entity_type_id) {
      PageRegion::ENTITY_TYPE_ID => $this->createTestPageRegion($this->tree),
      Pattern::ENTITY_TYPE_ID => $this->createTestPattern($this->tree),
      ContentTemplate::ENTITY_TYPE_ID => $this->createTestContentTemplate($this->tree),
      default => throw new \InvalidArgumentException()
    };

    // Now enable the 'canvas_test_storage_prop_shape_alter' module to change the
    // field type used for populating the href prop.
    // @see \Drupal\canvas_test_storage_prop_shape_alter\Hook\CanvasTestStoragePropShapeAlterHooks::storagePropShapeAlter()
    \Drupal::service(ModuleInstallerInterface::class)
      ->install(['canvas_test_storage_prop_shape_alter']);
    $component = Component::load('sdc.canvas_test_sdc.my-cta');
    \assert($component instanceof ComponentInterface);
    self::assertCount(2, $component->getVersions());
    $new_version = $component->getActiveVersion();

    // 1. All versions.
    $config = $audit->getConfigEntityDependenciesUsingComponent($component, $config_entity_type_id);
    self::assertCount(1, $config);
    self::assertEquals([$entity->id()], \array_values(\array_map(static fn(ConfigEntityInterface $entity): string|int|null => $entity->id(), $config)));

    // @todo Uncomment this in https://www.drupal.org/i/3530051.
    assert($new_version != $old_version);
    /*
    // 2. Active (i.e. new) version: no uses yet.
    $config = $audit->getConfigEntityDependenciesUsingComponent($component, $config_entity_type_id, [$new_version]);
    self::assertEquals([], \array_values(\array_map(static fn(ConfigEntityInterface $entity): string|int|null => $entity->id(), $config)));

    // 3. Old version.
    $config = $audit->getConfigEntityDependenciesUsingComponent($component, $config_entity_type_id, [$old_version]);
    self::assertEquals([$entity->id()], \array_values(\array_map(static fn(ConfigEntityInterface $entity): string|int|null => $entity->id(), $config)));
     */
  }

  public static function configProvider(): iterable {
    yield PageRegion::ENTITY_TYPE_ID => [PageRegion::ENTITY_TYPE_ID];
    yield Pattern::ENTITY_TYPE_ID => [Pattern::ENTITY_TYPE_ID];
    yield ContentTemplate::ENTITY_TYPE_ID => [ContentTemplate::ENTITY_TYPE_ID];
  }

}
