<?php

declare(strict_types=1);

namespace Drupal\ai_context\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_context\Entity\AiContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add/Edit form for AI Context config entities.
 */
final class AiContextForm extends EntityForm {

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\ai_context\Entity\AiContext $entity */
    $form = parent::form($form, $form_state);
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#default_value' => $entity->label(),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => [AiContext::class, 'load'],
      ],
      '#default_value' => $entity->id(),
      '#disabled' => !$entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description') ?? '',
      '#rows' => 3,
      '#description' => $this->t('Brief description of this context\'s purpose. Used for admin reference only.'),
    ];

    $form['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content (Markdown allowed)'),
      '#default_value' => $entity->get('content') ?? '',
      '#rows' => 12,
      '#description' => $this->t('Reusable site-specific guidance. Keep concise and actionable.'),
    ];

    // Tags via taxonomy term autocomplete, stored as term IDs in config.
    $default_tids = array_filter($entity->get('tags') ?? []);
    $default_terms = [];
    if (!empty($default_tids)) {
      $default_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($default_tids);
    }
    $form['tags'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Tags'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['ai_context_tags'],
      ],
      '#tags' => TRUE,
      '#default_value' => array_values($default_terms),
      '#description' => $this->t('Optional taxonomy tags from the AI Context Tags vocabulary.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    // Handle tags field manually to prevent type mismatch
    $tags_value = $form_state->getValue('tags');
    if ($tags_value !== NULL) {
      $form_state->unsetValue('tags');
    }

    parent::copyFormValuesToEntity($entity, $form, $form_state);

    // Process tags manually after parent call
    if ($tags_value !== NULL) {
      $terms = (array) $tags_value;
      $tids = [];
      foreach ($terms as $item) {
        if (is_array($item) && isset($item['target_id'])) {
          $tids[] = (string) $item['target_id'];
        }
        elseif (is_object($item) && method_exists($item, 'id')) {
          $tids[] = (string) $item->id();
        }
      }
      $entity->set('tags', array_values(array_unique(array_filter($tids))));
    }
  }

}
