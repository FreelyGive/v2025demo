<?php

declare(strict_types=1);

namespace Drupal\canvas_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: "canvas_test_block_input_validatable_crash",
  admin_label: new TranslatableMarkup("Test Block with settings, crashes when 'crash' setting is TRUE"),
)]
final class CanvasTestBlockInputValidatableCrash extends CanvasTestBlockInputUnvalidatable {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'name' => 'Canvas',
      // Do not crash by default 😇
      // @see \Drupal\Tests\canvas\Kernel\Plugin\Canvas\ComponentSource\ComponentSourceTestBase::testRenderComponentFailure()
      'crash' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if ($this->configuration['crash']) {
      throw new \Exception('Intentional test exception.');
    }
    return [
      '#markup' => $this->t('<div>Hello, :name!</div>', [':name' => $this->configuration['name']]),
    ];
  }

}
