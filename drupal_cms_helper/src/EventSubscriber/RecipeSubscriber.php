<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class RecipeSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RecipeAppliedEvent::class => 'onApply',
    ];
  }

  public function onApply(): void {
    $config = $this->configFactory->getEditable('system.site');

    $front_saved_path = $config->get('page.front');
    $front_system_path = $this->aliasManager->getPathByAlias($front_saved_path);
    if ($front_system_path !== $front_saved_path) {
      $config->set('page.front', $front_system_path)->save();
    }
  }

}
