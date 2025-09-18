<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit\DataType;

use Drupal\Component\Serialization\Json;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\canvas\ComponentSource\ComponentSourceInterface;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\MissingComponentInputsException;
use Drupal\canvas\Plugin\DataType\ComponentInputs;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\canvas\Plugin\DataType\ComponentInputs
 * @see \Drupal\Tests\canvas\Kernel\DataType\ComponentInputsDependenciesTest
 * @group canvas
 */
class ComponentInputsTest extends UnitTestCase {

  /**
   * @covers ::getValues
   */
  public function testGetValues(): void {
    // Create test data.
    $test_inputs = [
      'title' => [
        'sourceType' => 'static:text',
        'value' => 'Test Title',
        'expression' => '',
      ],
      'body' => [
        'sourceType' => 'static:text',
        'value' => 'Test Body',
        'expression' => '',
      ],
    ];
    $component_source = $this->prophesize(ComponentSourceInterface::class);
    $component_source->requiresExplicitInput()->willReturn(FALSE);
    $component = $this->prophesize(ComponentInterface::class);
    $component->getComponentSource()->willReturn($component_source->reveal());

    $item = $this->prophesize(ComponentTreeItem::class);
    $item->onChange(NULL)->shouldBeCalled();
    $item->getComponent()->willReturn($component->reveal());
    $item->getUuid()->willReturn('abcd-1234');

    $component_inputs = new ComponentInputs(
      $this->prophesize(DataDefinitionInterface::class)->reveal(),
      NULL,
      $item->reveal()
    );
    $component_inputs->setValue(Json::encode($test_inputs));

    // Test getting values for a existing UUID.
    $this->assertEquals(
      $test_inputs,
      $component_inputs->getValues()
    );

    // Test getting empty values without requiring explicit input.
    $component_inputs->setValue('{}');
    $values = $component_inputs->getValues();
    $this->assertEquals([], $values);

    // Test getting values when explicit input is required.
    $component_source->requiresExplicitInput()->willReturn(TRUE);
    $this->expectException(MissingComponentInputsException::class);
    $component_inputs->getValues();
  }

}
