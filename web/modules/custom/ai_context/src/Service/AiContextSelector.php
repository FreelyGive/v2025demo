<?php

declare(strict_types=1);

namespace Drupal\ai_context\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * High-level selection that merges always_include with router results.
 */
final class AiContextSelector {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AiContextRouter $router,
    private readonly AiContextRenderer $renderer,
  ) {}

  /**
   * Selects context IDs and renders a compact block.
   *
   * - If neither a pool nor always_include is configured for the agent, returns none.
   * - Always respects max_contexts; always_include entries are prepended.
   *
   * @return array{ids: string[], text: string}
   */
  public function select(string $task, string $agentId = '', array $alwaysParam = [], ?int $maxOverride = NULL): array {
    $poolIds = [];
    $alwaysCfg = [];
    if ($agentId !== '') {
      $maps = (array) $this->configFactory->get('ai_context.agent_pools')->get('agents') ?? [];
      foreach ($maps as $map) {
        if (($map['id'] ?? '') === $agentId) {
          $poolIds = array_values(array_filter((array) ($map['contexts'] ?? [])));
          $alwaysCfg = array_values(array_filter((array) ($map['always_include'] ?? [])));
          break;
        }
      }
    }

    // Merge always_include from config and tool param.
    $always = array_values(array_unique(array_merge($alwaysCfg, $alwaysParam)));

    // If nothing is defined at all, return none.
    if (empty($poolIds) && empty($always)) {
      return ['ids' => [], 'text' => ''];
    }

    // Exclude always from pool to maximize variety.
    $poolForSelection = array_values(array_diff($poolIds, $always));
    $ranked = [];
    if (!empty($poolForSelection)) {
      $ranked = $this->router->getRelevantContexts($task, $poolForSelection);
    }

    $limit = $maxOverride !== NULL && $maxOverride >= 1
      ? $maxOverride
      : (int) ($this->configFactory->get('ai_context.settings')->get('max_contexts') ?? 3);

    $final = array_slice(array_values(array_unique(array_merge($always, $ranked))), 0, max(1, $limit));

    // Render text (empty if none).
    $maxTokens = $maxOverride ?? (int) ($this->configFactory->get('ai_context.settings')->get('max_tokens') ?? 1200);
    $text = !empty($final) ? $this->renderer->render($final, $maxTokens) : '';

    return ['ids' => $final, 'text' => $text];
  }
}

