<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropShape\StorablePropShape;

/**
 * @covers \Drupal\canvas\PropShape\PropShape::getStorage()
 * @group canvas
 */
class HookStoragePropAlterTest extends PropShapeRepositoryTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // @see canvas_test_storage_prop_shape_alter_storage_prop_shape_alter()
    // @see canvas_test_storage_prop_shape_alter_field_widget_info_alter()
    'canvas_test_storage_prop_shape_alter',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getExpectedStorablePropShapes(): array {
    $storable_prop_shapes = parent::getExpectedStorablePropShapes();
    $storable_prop_shapes['type=string&format=uri'] = new StorablePropShape(
      shape: $storable_prop_shapes['type=string&format=uri']->shape,
      fieldTypeProp: new FieldTypePropExpression('uri', 'value'),
      fieldWidget: 'uri',
    );
    return $storable_prop_shapes;
  }

}
