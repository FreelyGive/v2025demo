<?php

declare(strict_types=1);

namespace Drupal\canvas_personalization\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\canvas_personalization\Entity\Segment;
use Drupal\canvas_personalization\Entity\SegmentInterface;

/**
 * Personalization Segment form.
 *
 * ⚠️ This is highly experimental and *will* be refactored or even removed.
 *
 * @todo Revisit in https://www.drupal.org/i/3527086
 */
final class SegmentForm extends EntityForm {

  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    assert($this->entity instanceof SegmentInterface);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [Segment::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
    ];

    $form['rules_table'] = [
      '#type' => 'table',
      '#empty' => $this->t('No rules added yet.'),
      '#header' => [
        'rule' => $this->t('Name'),
        'summary' => $this->t('Summary'),
        'operations' => $this->t('Operations'),
      ],
      '#attributes' => [
        'id' => 'rules-id',
      ],
    ];
    foreach ($this->entity->getSegmentRulesPluginCollection() as $rule) {
      assert($rule instanceof ConditionInterface);
      $rule_id = $rule->getPluginId();
      $plugin_definition = $rule->getPluginDefinition();
      assert(\is_array($plugin_definition) && \array_key_exists('label', $plugin_definition));
      $rule_label = $plugin_definition['label'];
      $summary = $rule->summary();

      $form['rules_table'][$rule_id] = [
        '#plugin_id' => $rule_id,
        '#settings' => [],
        'rule' => [
          '#type' => 'item',
          '#markup' => $rule_label,
        ],
        'summary' => [
          '#markup' => $summary,
        ],
        'operations' => [
          'delete' => [
            '#type' => 'link',
            '#title' => $this->t('Delete %rule', ['%rule' => $rule_label]),
            '#url' => Url::fromRoute('entity.segment.delete_segment_rule', ['segment' => $this->entity->id(), 'rule' => $rule_id]),
          ],
        ],
      ];
    }

    if (!$this->entity->isNew()) {
      $form['_new_rule'] = [
        '#type' => 'link',
        '#title' => $this->t('New segment rule'),
        '#url' => Url::fromRoute('entity.segment.add_rule_form', ['segment' => $this->entity->id()]),
        '#id' => Html::getId('toolbar-item-announcement'),
        '#attributes' => [
          'title' => $this->t('New segment rule'),
          'data-drupal-announce-trigger' => '',
          'class' => [
            'use-ajax',
            'announce-canvas-link',
            'announce-default',
          ],
          'data-dialog-renderer' => 'off_canvas',
          'data-dialog-type' => 'dialog',
          'data-dialog-options' => Json::encode(
            [
              'announce' => TRUE,
              'width' => '25%',
              'classes' => [
                'ui-dialog' => 'announce-dialog',
                'ui-dialog-titlebar' => 'announce-titlebar',
                'ui-dialog-title' => 'announce-title',
                'ui-dialog-titlebar-close' => 'announce-close',
                'ui-dialog-content' => 'announce-body',
              ],
            ]),
        ],
      ];
    }
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    return $form;
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new personalization segment %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated personalization segment %label.', $message_args),
        // See https://www.drupal.org/i/3264370
        default => $this->t('Saved personalization segment %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
