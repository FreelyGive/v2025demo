<?php

namespace Drupal\canvas\EventSubscriber;

use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Render\RenderEvents;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Selects the Drupal Canvas page display variant.
 *
 * @see \Drupal\Core\Render\RenderEvents
 */
final class PageVariantSelectorSubscriber implements EventSubscriberInterface {

  /**
   * Selects the Drupal Canvas page display variant.
   *
   * @param \Drupal\Core\Render\PageDisplayVariantSelectionEvent $event
   *   The event to process.
   *
   * @see \Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant
   */
  public function onSelectPageDisplayVariant(PageDisplayVariantSelectionEvent $event): void {
    $regions = PageRegion::loadForActiveTheme();
    if (empty($regions)) {
      // No active page regions for this theme.
      return;
    }
    $event->setPluginId(CanvasPageVariant::PLUGIN_ID);
    $event->setPluginConfiguration([
      CanvasPageVariant::PREVIEW_KEY => $event->getRouteMatch()->getRouteObject()?->getOption('_canvas_use_template_draft'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // This must run after all other page variant subscribers.
    // @see \Drupal\block\EventSubscriber\BlockPageDisplayVariantSubscriber
    $events[RenderEvents::SELECT_PAGE_DISPLAY_VARIANT][] = ['onSelectPageDisplayVariant', -100];
    return $events;
  }

}
