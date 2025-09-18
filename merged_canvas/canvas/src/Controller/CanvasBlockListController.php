<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\block\Controller\BlockListController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\canvas\Entity\PageRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Show warning message in Block UI when using Drupal Canvas's PageRegions.
 *
 * @see \Drupal\canvas\EventSubscriber\CanvasBlockListingRouteSubscriber
 */
final class CanvasBlockListController extends BlockListController implements EventSubscriberInterface {

  /**
   * Constructs a new CanvasBlockListController.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    ThemeHandlerInterface $theme_handler,
  ) {
    parent::__construct($theme_handler);
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get(ConfigFactoryInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(MessengerInterface::class),
      $container->get(ThemeHandlerInterface::class),
    );
  }

  /**
   * Overrides the block listing.
   *
   * @param string|null $theme
   *   (Optional) The theme name.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   (Optional) The request object.
   *
   * @return array
   *   A renderable array for the block listing.
   */
  public function listing($theme = NULL, ?Request $request = NULL): array {
    $build = parent::listing($theme, $request);
    assert(is_array($build));

    // Load the editable page regions for the current default theme.
    $theme = $theme ?? $this->configFactory->get('system.theme')->get('default');
    $regions = $this->entityTypeManager->getStorage(PageRegion::ENTITY_TYPE_ID)->loadByProperties(['theme' => $theme]);
    if (!empty($regions)) {
      $theme_settings_url = Url::fromRoute('system.theme_settings_theme', ['theme' => $theme]);
      $link = Link::fromTextAndUrl($this->t('theme'), $theme_settings_url)->toString();
      $this->messenger->addWarning($this->t('This form currently has no effect because the @link has been configured to use Drupal Canvas for managing the block layout.', ['@link' => $link]));
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[RoutingEvents::ALTER] = 'onAlterRoutes';
    return $events;
  }

  public function onAlterRoutes(RouteBuildEvent $event): void {
    $collection = $event->getRouteCollection();
    if ($route = $collection->get('block.admin_display')) {
      $route->setDefault('_controller', static::class . '::listing');
    }
    if ($route = $collection->get('block.admin_display_theme')) {
      $route->setDefault('_controller', static::class . '::listing');
    }
  }

}
