<?php

declare(strict_types=1);

namespace Drupal\canvas\EventSubscriber;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\Core\DefaultContent\PreImportEvent;
use Drupal\Core\Recipe\RecipeAppliedEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Ensures components are generated during and after recipe application.
 */
final class RecipeSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface[]
   */
  private array $componentSources = [];

  public function __construct(
    #[Autowire(service: 'plugin.manager.config_action')]
    private readonly ConfigActionManager $configActionManager,
  ) {}

  public function addComponentSource(CachedDiscoveryInterface $discovery): void {
    $this->componentSources[] = $discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreImportEvent::class => 'ensureComponentsExist',
      RecipeAppliedEvent::class => 'onApply',
    ];
  }

  /**
   * Creates component entities as needed, during and after recipe application.
   */
  public function ensureComponentsExist(): void {
    foreach ($this->componentSources as $source) {
      // Ensure that all component information is fully up-to-date before
      // we import content that might be using them, and after the recipe has
      // finished applying (since it may have run config actions which affected
      // extant components).
      $source->clearCachedDefinitions();
      $source->getDefinitions();
    }
  }

  /**
   * Reacts when a recipe is applied.
   *
   * @param \Drupal\Core\Recipe\RecipeAppliedEvent $event
   *   The event object.
   */
  public function onApply(RecipeAppliedEvent $event): void {
    $this->ensureComponentsExist();

    // Re-run any config actions that target Component entities.
    $items = array_filter(
      $event->recipe->config->config['actions'] ?? [],
      // @see \Drupal\canvas\Entity\Component
      fn (string $name): bool => str_starts_with($name, 'canvas.component.'),
      ARRAY_FILTER_USE_KEY,
    );
    foreach ($items as $name => $actions) {
      foreach ($actions as $action_id => $data) {
        $this->configActionManager->applyAction($action_id, $name, $data);
      }
    }
  }

}
