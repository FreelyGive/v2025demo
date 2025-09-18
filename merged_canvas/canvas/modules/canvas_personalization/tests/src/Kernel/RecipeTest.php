<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Kernel;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\CrawlerTrait;
use Drupal\canvas_personalization\Entity\Segment;

/**
 * @see \Drupal\Tests\canvas\Kernel\ApiAutoSaveControllerTest
 * @group canvas
 * @group canvas_personalization
 * @covers \Drupal\canvas\EventSubscriber\RecipeSubscriber
 */
final class RecipeTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use RecipeTestTrait;
  use CrawlerTrait;

  private const string FIXTURES_DIR = __DIR__ . '/../../../../../tests/fixtures/recipes';

  public function testRecipe(): void {
    // The recipe should apply without errors, because the components used by
    // the content should be available by the time the content is imported.
    $recipe = Recipe::createFromDirectory(self::FIXTURES_DIR . '/test_site_personalization');
    RecipeRunner::processRecipe($recipe);

    // Demo content should have been created.
    $this->assertSame([
      1 => ['Personalization demo', '/personalization-test'],
    ], array_map(
      // @phpstan-ignore-next-line
      fn (Page $page) => [$page->label(), $page->get('path')->alias],
      Page::loadMultiple()
    ));
    $this->assertSame('/personalization-test', $this->config('system.site')->get('page.front'));

    $page = $this->container->get(EntityRepositoryInterface::class)
      ->loadEntityByUuid('canvas_page', 'f6ab99d8-0d6f-48ed-97f3-04f08cf705d1');
    $this->assertInstanceOf(FieldableEntityInterface::class, $page);
    $components_field = $page->get('components');
    assert($components_field instanceof ComponentTreeItemList);
    $switch_component = $components_field
      ->getComponentTreeItemByUuid('8e28dfca-c1b1-4aa2-8c19-b8a0c13e9bf4');
    assert($switch_component instanceof ComponentTreeItem);
    $this->assertIsArray($switch_component->getInputs());
    $this->assertEquals([
      'variants' => [
        'halloween',
        'default',
      ],
    ], $switch_component->getInputs());

    $halloween_case = $components_field
      ->getComponentTreeItemByUuid('5e9d5b61-595c-4785-8af6-b78317e52c64');
    assert($halloween_case instanceof ComponentTreeItem);
    $this->assertIsArray($halloween_case->getInputs());
    $this->assertEquals([
      'variant_id' => 'halloween',
      'segments' => [
        'halloween',
      ],
    ], $halloween_case->getInputs());
    $this->assertSame('Halloween', $halloween_case->getLabel());

    $default_case = $components_field
      ->getComponentTreeItemByUuid('1c29b6e6-02c5-4bfc-99e0-88894609390e');
    assert($default_case instanceof ComponentTreeItem);
    $this->assertIsArray($default_case->getInputs());
    $this->assertEquals([
      'variant_id' => 'default',
      'segments' => [
        Segment::DEFAULT_ID,
      ],
    ], $default_case->getInputs());
    $this->assertSame('Default', $default_case->getLabel());
  }

}
