<?php

declare(strict_types=1);

namespace Drupal\ai_context\Plugin\AiFunctionGroup;

use Drupal\ai\Attribute\FunctionGroup;
use Drupal\ai\Service\FunctionCalling\FunctionGroupInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[FunctionGroup(
  id: 'context_tools',
  group_name: new TranslatableMarkup('Context Tools'),
  description: new TranslatableMarkup('Tools for selecting and injecting site context.'),
)]
final class ContextTools implements FunctionGroupInterface {}

