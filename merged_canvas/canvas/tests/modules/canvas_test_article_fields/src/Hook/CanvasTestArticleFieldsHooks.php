<?php

declare(strict_types=1);

namespace Drupal\canvas_test_article_fields\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Defines hooks for the canvas_test_article_fields module.
 */
final class CanvasTestArticleFieldsHooks {

  public const string GRAVY_STORE = 'canvas_test_article_fields_gravy';
  public const string NO_MORE_GRAVY = 'no_more_gravy';
  public const string GRAVY_STATE = 'canvas_test_article_fields_gravy_state';
  public const string CANVAS_STATE = 'canvas_state';

  public function __construct(
    #[Autowire(service: 'keyvalue')]
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {
  }

  #[Hook('form_node_article_form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    if (!$this->keyValueFactory->get(self::CANVAS_STATE)->get(self::GRAVY_STATE, FALSE)) {
      return;
    }
    $form_object = $form_state->getFormObject();
    if (!($form_object instanceof EntityFormInterface)) {
      return;
    }

    $node = $form_object->getEntity();
    if (!($node instanceof NodeInterface)) {
      return;
    }
    $form[self::NO_MORE_GRAVY] = [
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('No more gravy please'),
      '#description' => new TranslatableMarkup('Check this if you would like anything but the gravy'),
      '#default_value' => $this->keyValueFactory->get(self::GRAVY_STORE)->get((string) $node->id(), TRUE),
    ];
    $form['#entity_builders'][] = [self::class, 'canvasPageEntityGravyBuilder'];
  }

  public static function canvasPageEntityGravyBuilder(string $entity_type_id, NodeInterface $node, array &$form, FormStateInterface $form_state): void {
    if ($form_state->hasValue(self::NO_MORE_GRAVY) && $form_state->isProcessingInput()) {
      $more_gravy = (bool) $form_state->getValue(self::NO_MORE_GRAVY);
      \Drupal::keyValue(self::GRAVY_STORE)->set((string) $node->id(), $more_gravy);
      $node->set('title', $more_gravy ? 'No more gravy' : 'Gravy!');
    }
  }

}
