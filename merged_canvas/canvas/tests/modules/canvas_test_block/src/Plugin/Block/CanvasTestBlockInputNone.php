<?php

declare(strict_types=1);

namespace Drupal\canvas_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: "canvas_test_block_input_none",
  admin_label: new TranslatableMarkup("Test block with no settings."),
)]
final class CanvasTestBlockInputNone extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(AccountInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#prefix' => '<div>',
      '#markup' => $this->t('Hello :username, from Canvas!', [':username' => $this->currentUser->getAccountName()]),
      '#suffix' => '</div>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // @phpstan-ignore-next-line argument.type
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['user:' . $this->currentUser->id()]);
  }

}
