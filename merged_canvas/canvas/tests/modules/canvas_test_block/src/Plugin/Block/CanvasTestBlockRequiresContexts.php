<?php

declare(strict_types=1);

namespace Drupal\canvas_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: "canvas_test_block_requires_contexts",
  admin_label: new TranslatableMarkup("Test Block with required contexts"),
  context_definitions: [
    'name' => new ContextDefinition(),
  ],
)]
final class CanvasTestBlockRequiresContexts extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}
