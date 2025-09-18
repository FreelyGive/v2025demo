<?php

declare(strict_types=1);

namespace Drupal\canvas_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: "canvas_test_block_optional_contexts",
  admin_label: new TranslatableMarkup("Test Block with optional contexts"),
  context_definitions: [
    'name' => new ContextDefinition(required: FALSE),
  ],
)]
final class CanvasTestBlockOptionalContexts extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      // @todo Update this in https://www.drupal.org/i/3485502 to present the information from the optional context.
      '#markup' => 'Test Block with optional context value: @todo in https://www.drupal.org/i/3485502',
    ];
  }

}
