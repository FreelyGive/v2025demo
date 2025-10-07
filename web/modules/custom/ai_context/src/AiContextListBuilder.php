<?php

declare(strict_types=1);

namespace Drupal\ai_context;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * List builder for AI Context config entities.
 */
final class AiContextListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['description'] = $this->t('Description');
    $header['tags'] = $this->t('Tags');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ai_context\Entity\AiContext $entity */
    $row['label'] = $entity->label();
    $row['description'] = $entity->get('description');

    // Load tag labels instead of showing IDs
    $tag_ids = $entity->get('tags') ?? [];
    $tag_labels = [];
    if (!empty($tag_ids)) {
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tag_ids);
      foreach ($terms as $term) {
        $tag_labels[] = $term->label();
      }
    }
    $row['tags'] = implode(', ', $tag_labels);

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['#empty'] = $this->t('There are no AI Contexts yet. <a href=":url">Add one</a>.', [
      ':url' => $this->entityType->getLinkTemplate('add-form'),
    ]);
    return $build;
  }

}
