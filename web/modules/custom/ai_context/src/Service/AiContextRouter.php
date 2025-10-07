<?php

declare(strict_types=1);

namespace Drupal\ai_context\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\ai\Utility\Tokenizer;

/**
 * Selects relevant AI Contexts for a given task and pool.
 */
final class AiContextRouter {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Tokenizer $tokenizer,
    private readonly LoggerChannelInterface $logger,
    private readonly AiProviderPluginManager $aiProviderManager,
  ) {}

  /**
   * Returns a ranked, trimmed list of AI Context IDs.
   *
   * @param string $task
   *   Task description / user prompt.
   * @param string[] $poolIds
   *   Candidate ai_context IDs.
   *
   * @return string[]
   *   Selected ai_context IDs ordered by relevance.
   */
  public function getRelevantContexts(string $task, array $poolIds): array {
    $poolIds = array_values(array_unique(array_filter($poolIds)));
    if (!$poolIds) {
      return [];
    }

    $settings = $this->configFactory->get('ai_context.settings');
    $strategy = $settings->get('strategy') ?? 'keyword';
    $max = (int) ($settings->get('max_contexts') ?? 3);

    $contexts = $this->entityTypeManager->getStorage('ai_context')->loadMultiple($poolIds);
    if (!$contexts) {
      return [];
    }

    $ranked = [];
    switch ($strategy) {
      case 'llm':
        $ranked = $this->rankWithLlm($task, $contexts, $max);
        break;

      case 'keyword':
      default:
        $ranked = $this->rankWithKeyword($task, $contexts, $max);
        break;
    }

    return array_slice($ranked, 0, $max);
  }

  /**
   * Simple keyword ranking with tag and term frequency overlap.
   */
  private function rankWithKeyword(string $task, array $contexts, int $max): array {
    $taskL = mb_strtolower($task);
    $scores = [];
    foreach ($contexts as $id => $entity) {
      $score = 0;
      $content = (string) $entity->get('content');
      $label = (string) $entity->label();
      $tags = (array) ($entity->get('tags') ?? []);
      foreach ($tags as $tag) {
        if ($tag !== '') {
          $score += str_contains($taskL, mb_strtolower((string) $tag)) ? 2 : 0;
        }
      }
      $score += substr_count($taskL, mb_strtolower($label)) * 3;
      // Rough TF by counting overlapping words.
      $words = preg_split('/\W+/u', mb_strtolower($content));
      $unique = array_unique(array_filter($words));
      foreach ($unique as $w) {
        if ($w && strlen($w) > 3 && str_contains($taskL, $w)) {
          $score += 1;
        }
      }
      $scores[$id] = $score;
    }
    arsort($scores, SORT_NUMERIC);
    return array_keys($scores);
  }

  /**
   * LLM-assisted ranking using compact prompt; falls back to keyword.
   */
  private function rankWithLlm(string $task, array $contexts, int $max): array {
    try {
      $config = $this->configFactory->get('ai_context.settings');
      $providerId = (string) ($config->get('provider_id') ?? '');
      $modelId = (string) ($config->get('model_id') ?? '');
      if (!$providerId || !$modelId) {
        return $this->rankWithKeyword($task, $contexts, $max);
      }

      $provider = $this->aiProviderManager->createInstance($providerId);
      $summaries = [];
      foreach ($contexts as $id => $entity) {
        $content = (string) $entity->get('content');
        $label = (string) $entity->label();
        $snippet = mb_substr(trim($content), 0, 280);
        $summaries[] = [
          'id' => $id,
          'title' => $label,
          'snippet' => $snippet,
        ];
      }
      $instruction = "Given the task, pick up to $max relevant context IDs. Respond only with a JSON array of IDs.";
      $prompt = json_encode([
        'task' => $task,
        'candidates' => $summaries,
      ], JSON_UNESCAPED_UNICODE);

      $provider->setChatSystemRole($instruction);
      $response = $provider->chat(new \Drupal\ai\OperationType\Chat\ChatInput([
        new \Drupal\ai\OperationType\Chat\ChatMessage('user', $prompt),
      ]), $modelId, ['ai_context','ai_context_router']);

      $text = $response->getNormalized()->getText();
      $ids = json_decode($text, TRUE);
      if (is_array($ids)) {
        $ids = array_values(array_intersect(array_keys($contexts), array_map('strval', $ids)));
        if ($ids) {
          return $ids;
        }
      }
    }
    catch (\Throwable $e) {
      $this->logger->warning('LLM routing failed, falling back to keyword: @m', ['@m' => $e->getMessage()]);
    }
    return $this->rankWithKeyword($task, $contexts, $max);
  }
}

