<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Config;

use Drupal\canvas\Entity\Pattern;

/**
 * Tests the component tree aspects of the Pattern config entity type.
 *
 * @group canvas
 * @coversDefaultClass \Drupal\canvas\Entity\Pattern
 */
final class PatternComponentTreeTest extends ConfigWithComponentTreeTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entity = Pattern::create([
      'id' => 'test_pattern',
      'label' => 'Test pattern',
    ]);
  }

}
