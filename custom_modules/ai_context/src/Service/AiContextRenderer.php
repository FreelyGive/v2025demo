<?php

declare(strict_types=1);

namespace Drupal\ai_context\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai\Utility\TextChunker;

/**
 * Renders a compact, token-budgeted context block.
 */
final class AiContextRenderer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TextChunker $textChunker,
  ) {}

  /**
   * @param string[] $ids
   *   ai_context entity IDs.
   * @param int $maxTokens
   *   Rough token budget; this method applies naive truncation.
   */
  public function render(array $ids, int $maxTokens = 1200): string {
    $entities = $this->entityTypeManager->getStorage('ai_context')->loadMultiple($ids);
    if (!$entities) {
      return '';
    }
    $out = [];
    $acc = 0;
    foreach ($ids as $id) {
      if (!isset($entities[$id])) {
        continue;
      }
      $e = $entities[$id];
      $title = (string) $e->label();
      $tags = implode(', ', (array) ($e->get('tags') ?? []));
      $content = trim((string) $e->get('content'));
      // 3.5 does do token padding for all newer models.
      $this->textChunker->setModel('gpt-3.5-turbo');
      $snippets = $this->textChunker->chunkText($content, $maxTokens, 0);
      $snippet = $snippets[0] ?? '';
      $block = "- ID: $id\n  Title: $title\n  Tags: $tags\n  Guidance:\n" . $this->indent($snippet, 4);
      $tokens = (int) (mb_strlen($block) / 4.0);
      if ($acc + $tokens > $maxTokens) {
        break;
      }
      $out[] = $block;
      $acc += $tokens;
    }

    if (!$out) {
      return '';
    }

    return "Site Context (selected):\n" . implode("\n\n", $out) . "\n";
  }

  private function indent(string $text, int $spaces): string {
    $pad = str_repeat(' ', $spaces);
    return $pad . str_replace("\n", "\n$pad", $text);
  }
}

