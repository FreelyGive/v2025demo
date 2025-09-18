<?php

declare(strict_types=1);

namespace Drupal\canvas_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * We can't modify the canvas_test_block module to execute a real schema update.
 *
 * The way to execute a real schema update is to install a v2 of the same module and
 * execute a hook_update. This can't be done with the current infrastructure, but
 * we can simulate it with a second module, canvas_test_block_simulate_input_schema_change.
 * This second module, when enabled, brings a different schema for this block plugin
 * canvas_test_block_input_schema_change_poc, and with this new schema we can simulate
 * and test if the updates of the schema could fail or not.
 */
#[Block(
  id: "canvas_test_block_input_schema_change_poc",
  admin_label: new TranslatableMarkup("Test block for Input Schema Change POC."),
)]
class CanvasTestBlockInputSchemaChangePoc extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'foo' => 'bar',
    ];
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
        'bar' => 'Bar',
        'baz' => 'Baz',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['foo'] = $form_state->getValue('foo');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => $this->t('Current foo value: @foo', ['@foo' => $this->configuration['foo']]),
    ];
  }

}
