<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Url;
use Drupal\canvas\Entity\PageRegion;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;

/**
 * @group canvas
 * @covers \Drupal\canvas\Hook\PageRegionHooks::formSystemThemeSettingsAlter()
 * @covers \Drupal\canvas\Hook\PageRegionHooks::formSystemThemeSettingsSubmit()
 * @covers \Drupal\canvas\Controller\CanvasBlockListController
 * @covers \Drupal\canvas\Entity\PageRegion::createFromBlockLayout()
 */
class CanvasPageVariantEnableTest extends BrowserTestBase {

  use GenerateComponentConfigTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'canvas', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  public function test(): void {
    $assert = $this->assertSession();

    $this->drupalLogin($this->rootUser);
    $this->generateComponentConfig();

    // No Canvas settings on the global settings page.
    $this->drupalGet('/admin/appearance/settings');
    $this->assertSession()->pageTextNotContains('Drupal Canvas');
    $this->assertSession()->fieldNotExists('use_canvas');

    // Canvas checkbox on the Olivero theme page.
    $this->drupalGet('/admin/appearance/settings/olivero');
    $this->assertSession()->pageTextContains('Drupal Canvas');
    $this->assertSession()->fieldExists('use_canvas');

    // We start with no templates.
    $this->assertEmpty(PageRegion::loadMultiple());

    // No template is created if we do not enable Canvas; no warning messages on
    // block listing.
    $this->submitForm(['use_canvas' => FALSE], 'Save configuration');
    $this->assertEmpty(PageRegion::loadMultiple());
    $this->drupalGet('/admin/structure/block');
    $assert->elementsCount('css', '[aria-label="Warning message"]', 0);

    // Regions are created when we enable Canvas; warning message appears on block
    // listing.
    $this->drupalGet('/admin/appearance/settings/olivero');
    $this->submitForm(['use_canvas' => TRUE], 'Save configuration');
    $regions = PageRegion::loadMultiple();
    $this->assertCount(12, $regions);
    $this->drupalGet('/admin/structure/block');
    $assert->elementsCount('css', '[aria-label="Warning message"]', 1);
    $assert->elementTextContains('css', '[aria-label="Warning message"] .messages__content', 'configured to use Drupal Canvas for managing the block layout');

    // Check the regions are created correctly.
    $expected_page_region_ids = [
      'olivero.breadcrumb',
      'olivero.content_above',
      'olivero.content_below',
      'olivero.footer_bottom',
      'olivero.footer_top',
      'olivero.header',
      'olivero.hero',
      'olivero.highlighted',
      'olivero.primary_menu',
      'olivero.secondary_menu',
      'olivero.sidebar',
      'olivero.social',
    ];
    $regions_with_component_tree = [];
    foreach ($regions as $region) {
      $regions_with_component_tree[$region->id()] = $region->getComponentTree()->getValue();
    }
    $this->assertSame($expected_page_region_ids, array_keys($regions_with_component_tree));

    foreach ($regions_with_component_tree as $tree) {
      foreach ($tree as $component) {
        $this->assertTrue(Uuid::isValid($component['uuid']));
        $this->assertStringStartsWith('block.', $component['component_id']);
      }
    }
    $front = Url::fromRoute('<front>');
    $this->drupalGet($front);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementsCount('css', '#primary-tabs-title', 1);

    // The template is disabled again when we disable Canvas.
    $this->drupalGet('/admin/appearance/settings/olivero');
    $this->submitForm(['use_canvas' => FALSE], 'Save configuration');
    $regions = PageRegion::loadMultiple();
    $this->assertCount(12, $regions);
    foreach ($regions as $region) {
      $this->assertFalse($region->status());
    }

    $this->drupalGet($front);
    $this->assertSession()->statusCodeEquals(200);
  }

}
