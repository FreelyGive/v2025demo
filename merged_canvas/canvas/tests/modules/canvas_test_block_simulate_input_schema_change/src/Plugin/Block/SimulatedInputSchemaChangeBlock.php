<?php

declare(strict_types=1);

namespace Drupal\canvas_test_block_simulate_input_schema_change\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\canvas_test_block\Plugin\Block\CanvasTestBlockInputSchemaChangePoc;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extends the canvas_test_block_input_schema_change_poc block.
 */
final class SimulatedInputSchemaChangeBlock extends CanvasTestBlockInputSchemaChangePoc implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly StateInterface $state,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(StateInterface::class),
    );
  }

  public function defaultConfiguration(): array {
    return ['foo' => 2, 'change' => 'is scary'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['foo'] = [
      '#type' => 'select',
      '#title' => $this->t('Foo'),
      '#default_value' => $this->configuration['foo'],
      '#required' => TRUE,
      '#options' => [
        1 => 'One',
        2 => 'Two',
      ],
    ];

    $form['change'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Change'),
      '#default_value' => $this->configuration['change'],
      '#required' => FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['foo'] = $form_state->getValue('foo');
    $this->configuration['change'] = $form_state->getValue('change');
  }

  public function build(): array {
    // Check if "v2 block incompatible with v1 schema" mode is enabled.
    if ($this->state->get('canvas_test_block.schema_update_break', FALSE)) {
      // Check if current 'foo' value type is different to default 'foo' type.
      if (get_debug_type($this->configuration['foo']) !== get_debug_type($this::defaultConfiguration()['foo'])) {
        // Throw exception, to simulate code breaking.
        throw new \Exception("Simulated schema incompatibility exception.");
      }
    }
    return [
      '#markup' => $this->t('Modified block! Current foo value: @foo. Change … @change.', [
        '@foo' => $this->configuration['foo'],
        '@change' => $this->configuration['change'],
      ]),
    ];
  }

}
