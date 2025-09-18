<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\CodeComponentDataProvider;
use Drupal\canvas\Entity\Page;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;

/**
 * @group canvas
 */
class CodeComponentDataProviderTest extends FunctionalTestBase {

  use ContribStrictConfigSchemaTestTrait;

  protected static $modules = [
    'canvas',
    'canvas_test_code_components',
  ];

  protected $defaultTheme = 'stark';

  /**
   * @covers \Drupal\canvas\CodeComponentDataProvider::getCanvasDataBrandingV0
   * @covers \Drupal\canvas\CodeComponentDataProvider::getRequiredCanvasDataLibraries
   * @covers \Drupal\canvas\CodeComponentDataProvider::getPartialCanvasDataFromSettingsV0
   */
  public function testV0UsingDrupalSettingsGetSiteData(): void {
    $page = Page::create([
      'title' => 'Test page',
      'type' => 'page',
      'components' => [
        [
          'uuid' => CanvasTestSetup::UUID_COMPONENT_SDC,
          'component_id' => 'js.canvas_test_code_components_using_drupalsettings_get_site_data',
        ],
      ],
    ]);
    $page->save();

    $regular_user = $this->drupalCreateUser(['access content']);
    $this->assertInstanceOf(AccountInterface::class, $regular_user);
    $this->drupalLogin($regular_user);

    $this->drupalGet($page->toUrl());

    $drupalSettings = $this->getDrupalSettings();
    $this->assertArrayHasKey(CodeComponentDataProvider::CANVAS_DATA_KEY, $drupalSettings);
    self::assertSame([
      'baseUrl' => \Drupal::request()->getSchemeAndHttpHost() . \Drupal::request()->getBaseUrl(),
      'branding' => [
        'homeUrl' => '/user/login',
        'siteName' => 'Drupal',
        'siteSlogan' => '',
      ],
    ], $drupalSettings[CodeComponentDataProvider::CANVAS_DATA_KEY][CodeComponentDataProvider::V0]);
  }

  /**
   * @covers \Drupal\canvas\CodeComponentDataProvider::getRequiredCanvasDataLibraries
   * @covers \Drupal\canvas\CodeComponentDataProvider::getPartialCanvasDataFromSettingsV0
   */
  public function testV0NotUsingDrupalSettings(): void {
    $page = Page::create([
      'title' => 'Test page',
      'type' => 'page',
      'components' => [
        [
          'uuid' => CanvasTestSetup::UUID_COMPONENT_SDC,
          'component_id' => 'js.canvas_test_code_components_using_imports',
        ],
      ],
    ]);
    $page->save();

    $regular_user = $this->drupalCreateUser(['access content']);
    $this->assertInstanceOf(AccountInterface::class, $regular_user);
    $this->drupalLogin($regular_user);

    $this->drupalGet($page->toUrl());

    $drupalSettings = $this->getDrupalSettings();
    $this->assertArrayNotHasKey(CodeComponentDataProvider::CANVAS_DATA_KEY, $drupalSettings);
  }

}
