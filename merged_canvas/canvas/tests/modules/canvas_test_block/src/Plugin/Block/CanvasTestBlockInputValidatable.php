<?php

declare(strict_types=1);

namespace Drupal\canvas_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: "canvas_test_block_input_validatable",
  admin_label: new TranslatableMarkup("Test Block with settings"),
)]
final class CanvasTestBlockInputValidatable extends CanvasTestBlockInputUnvalidatable {}
