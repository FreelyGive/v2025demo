<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\canvas\Entity\PageRegion;

/**
 * Tests the component tree aspects of the PageRegion config entity type.
 *
 * @group canvas
 * @coversDefaultClass \Drupal\canvas\Entity\PageRegion
 */
final class PageRegionComponentTreeTest extends ConfigWithComponentTreeTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service(ThemeInstallerInterface::class)->install(['stark']);
    $this->entity = PageRegion::create([
      'theme' => 'stark',
      'region' => 'sidebar_first',
    ]);
  }

}
