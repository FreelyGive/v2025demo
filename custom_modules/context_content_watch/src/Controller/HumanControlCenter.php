<?php

namespace Drupal\context_content_watch\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

/**
 * Returns responses for Human Control Center routes.
 */
class HumanControlCenter extends ControllerBase {

  /**
   * Builds the response.
   */
  public function content() {
    // Load the context data.
    $context_data = \Drupal::state()->get('context_content_watch.context_data', []);
    // Create a table to display the context data.
    $header = [
      'title' => $this->t('Title'),
      'entity_type' => $this->t('Entity Type'),
      'agent' => $this->t('Agent'),
      'status' => $this->t('Status'),
      'last_updated' => $this->t('Last Updated'),
      'operations' => $this->t('Operations'),
    ];
    $rows = [];
    $row_pair_index = 0;
    foreach ($context_data as $data) {
      $agent_definition = \Drupal::service('plugin.manager.ai_agents')->getDefinition($data['agent']);
      // Load the entity for the link.
      $entity = \Drupal::entityTypeManager()->getStorage($data['entity_type'])->load($data['id']);

      // Determine row class for grouping pairs.
      $row_class = ($row_pair_index % 2 === 0) ? 'row-pair-even' : 'row-pair-odd';

      // Make one row with the information, and then the messages on a second
      // row that has full colspan.
      $rows[] = [
        'data' => [
          'title' => Link::fromTextAndUrl($data['label'], $entity->toUrl()),
          'entity_type' => $data['entity_type'],
          'agent' => $agent_definition['label'],
          'status' => $data['status'] ?? 'N/A',
          'last_updated' => \Drupal::service('date.formatter')->format($data['timestamp']),
          'operations' => Link::createFromRoute($this->t('View Draft'), 'canvas.boot.entity', [
            'entity_type' => $data['entity_type'],
            'entity' => $data['id'],
          ])->toString(),
        ],
        'class' => [$row_class, 'main-row'],
      ];
      if (!empty($data['change']) && is_array($data['change'])) {
        $message_output = '<ul>';
        foreach ($data['change'] as $message) {
          $message_output .= '<li>' . $message . '</li>';
        }
        $message_output .= '</ul>';
        $rows[] = [
          'data' => [
            [
              'data' => [
                '#markup' => $message_output,
              ],
              'colspan' => 5,
            ],
          ],
          'class' => [$row_class, 'message-row'],
        ];
      }
      $row_pair_index++;
    }
    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No content found.'),
      '#attributes' => ['class' => ['context-content-watch-table']],
      '#cache' => ['max-age' => 0],
    ];
    // Add a library for styling.
    $build['#attached']['library'][] = 'context_content_watch/hcc';
    return $build;
  }

}
