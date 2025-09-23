<?php

declare(strict_types=1);

namespace Drupal\canvas_test_block_form\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\Entity\Page;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: self::PLUGIN_ID,
  admin_label: new TranslatableMarkup('Test block form'),
  category: new TranslatableMarkup('Test')
)]
final class CanvasTestBlockForm extends BlockBase implements ContainerFactoryPluginInterface {
  public const string PLUGIN_ID = 'canvas_test_block_form';

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(EntityTypeManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'multiplier' => 0,
      'canvas_page' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => \sprintf('You selected page %d and 3 times that is %d', $this->configuration['canvas_page'], $this->configuration['multiplier']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    return [
      'canvas_page' => [
        // The entity_autocomplete plugin sets the value in a validation callback.
        // We use this element type for testing to ensure these validation
        // callbacks are set.
        // @see \Drupal\Core\Entity\Element\EntityAutocomplete::validateEntityAutocomplete
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Page'),
        '#target_type' => Page::ENTITY_TYPE_ID,
        '#required' => TRUE,
        '#default_value' => $this->configuration['canvas_page'] ? $this->entityTypeManager->getStorage(Page::ENTITY_TYPE_ID)
          ->load($this->configuration['canvas_page']) : NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $page = $form_state->getValue('canvas_page');
    \assert($page !== NULL);
    $this->setConfigurationValue('canvas_page', $page);
    // Set a configuration value that has no form equivalent and can only be set
    // from submitting the block form.
    $this->setConfigurationValue('multiplier', $page * 3);
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    $page_id = $form_state->getValue('canvas_page');
    if ($page_id === NULL) {
      return;
    }
    $page = $this->entityTypeManager->getStorage(Page::ENTITY_TYPE_ID)->load($page_id);
    if ($page !== NULL && $page->label() === 'Chatter') {
      $form_state->setErrorByName('canvas_page', $this->t('You better call me on the phone'));
    }
  }

}
