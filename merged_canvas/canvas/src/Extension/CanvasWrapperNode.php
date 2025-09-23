<?php

declare(strict_types=1);

namespace Drupal\canvas\Extension;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * Defines a twig node for wrapping SDC props and slots.
 */
#[YieldReady]
final class CanvasWrapperNode extends Node {

  /**
   * {@inheritdoc}
   */
  public function __construct(string $variableName, bool $isStart, int $lineno) {
    parent::__construct([], ['name' => $variableName, 'isStart' => $isStart], $lineno);
  }

  /**
   * {@inheritdoc}
   */
  public function compile(Compiler $compiler): void {
    $compiler->addDebugInfo($this);
    $type = $this->getAttribute('isStart') ? 'start' : 'end';
    $compiler->write('if (')
      ->raw('(isset($context[')
      ->string('canvas_is_preview')
      ->raw(']) && $context[')
      ->string('canvas_is_preview')
      ->raw(']) && array_key_exists(')
      ->string('canvas_uuid')
      ->raw(', $context)')
      ->raw(") {\n")
      ->indent()
      ->write('if (')
      ->raw('array_key_exists(')
      ->string('canvas_slot_ids')
      ->raw(', $context) && ')
      ->raw('in_array(')
      ->string($this->getAttribute('name'))
      ->raw(', $context[')
      ->string('canvas_slot_ids')
      ->raw('], TRUE)')
      ->raw(") {\n")
      ->indent()
      ->write('yield ')
      ->raw("\sprintf('<!-- canvas-slot-%s-%s/%s -->', ")
      ->string($type)
      ->raw(', ')
      ->raw('$context[')
      ->string('canvas_uuid')
      ->raw('], ')
      ->string($this->getAttribute('name'))
      ->raw(");\n")
      ->outdent()
      ->write("} else {\n")
      ->indent()
      ->write('yield ')
      ->raw("\sprintf('<!-- canvas-prop-%s-%s/%s -->', ")
      ->string($type)
      ->raw(', ')
      ->raw('$context[')
      ->string('canvas_uuid')
      ->raw('], ')
      ->string($this->getAttribute('name'))
      ->raw(");\n")
      ->outdent()
      ->write("}\n")
      ->outdent()
      ->write("}\n");
  }

}
