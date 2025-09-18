<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\canvas\Traits\OpenApiSpecTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Validate the fixtures in the UI against the OpenAPI schema.
 *
 * @group canvas
 *
 * @requires function \cebe\openapi\Reader::readFromYaml
 * @requires function \League\OpenAPIValidation\Schema\SchemaValidator::validate
 */
class UiFixturesValidationTest extends UnitTestCase {

  use OpenApiSpecTrait;

  /**
   * Gets the UI fixture data.
   *
   * @param string $filename
   *   Filename.
   *
   * @return array
   *   Fixture data.
   */
  protected function getUiFixtureData(string $filename): array {
    $fixturesDirectory = dirname(__FILE__, 4) . '/ui/tests/fixtures';
    $json = file_get_contents(sprintf('%s/%s', $fixturesDirectory, $filename));
    assert(is_string($json));
    return Json::decode($json);
  }

  /**
   * Tests the layout-default.json UI Fixture.
   */
  public function testUiLayoutDefaultFixture(): void {
    $uiFixture = $this->getUiFixtureData('layout-default.json');

    // Assert the main layout structure.
    $this->assertArrayHasKey('layout', $uiFixture);
    $this->assertDataCompliesWithApiSpecification($uiFixture['layout'][0], 'LayoutSlot');

    // Assert the layout components recursively.
    $this->assertLayoutComponents($uiFixture['layout'][0]['components']);

    // Assert the model structure.
    $this->assertArrayHasKey('model', $uiFixture);
    $this->assertDataCompliesWithApiSpecification($uiFixture['model'], 'Model');
  }

  /**
   * Helper function to traverse the layout components and validate them.
   *
   * @param array $components
   *   Array of layout components.
   */
  protected function assertLayoutComponents(array $components): void {
    foreach ($components as $component) {
      $this->assertDataCompliesWithApiSpecification($component, 'LayoutComponent');
      if (!empty($component['slots'])) {
        $this->assertLayoutSlots($component['slots']);
      }
    }
  }

  /**
   * Helper function to traverse the layout slots and validate them.
   *
   * @param array $slots
   *   Array of layout slots.
   */
  protected function assertLayoutSlots(array $slots): void {
    foreach ($slots as $child) {
      $this->assertDataCompliesWithApiSpecification($child, 'LayoutSlot');
      if (!empty($child['slots'])) {
        $this->assertLayoutComponents($child['slots']);
      }
    }
  }

}
