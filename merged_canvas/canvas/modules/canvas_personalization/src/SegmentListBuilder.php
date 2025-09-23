<?php

declare(strict_types=1);

namespace Drupal\canvas_personalization;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\canvas_personalization\Entity\SegmentInterface;

/**
 * Provides a listing of personalization segments.
 */
final class SegmentListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('Machine name');
    $header['label'] = $this->t('Label');
    $header['summary'] = $this->t('Summary');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof SegmentInterface);
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $summary_items = $entity->summary();
    $summary_items = \array_map(fn(\Stringable|string $value) => ['#type' => 'item', '#markup' => $value], $summary_items);
    $row['summary']['data'] = [
      '#theme' => 'item_list',
      '#items' => $summary_items,
    ];
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
