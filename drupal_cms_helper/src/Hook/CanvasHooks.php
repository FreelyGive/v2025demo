<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\canvas\Entity\Page;
use Drupal\canvas\EntityHandlers\ContentTemplateAwareViewBuilder;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\pathauto\PathautoState;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 *
 * @todo Remove when Canvas 1.0.0-alpha2 or later is released.
 */
final class CanvasHooks {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  #[Hook(
    'entity_type_alter',
    order: new OrderAfter(['canvas']),
  )]
  public function entityTypeAlter(array $definitions): void {
    // XB mistakenly uses its own view builder for webform submissions, not
    // accounting for the fact that Webform's view builder implements a
    // specialized interface. Since it's unlikely that webform submissions will
    // really need to be laid out with XB in the short term, we undo XB's work
    // here to prevent fatal errors, but this bug should really be fixed in XB
    // or Webform itself.
    if (isset($definitions['webform_submission']) && $this->moduleHandler->moduleExists('canvas')) {
      $definition = $definitions['webform_submission'];
      assert($definition instanceof ContentEntityTypeInterface);
      $definition->setViewBuilderClass($definition->getHandlerClass(ContentTemplateAwareViewBuilder::DECORATED_HANDLER_KEY));
    }
  }

  #[Hook('canvas_page_presave', module: 'pathauto')]
  public function preSaveCanvasPage(Page $page): void {
    $page->get('path')->first()->set('pathauto', PathautoState::SKIP);
  }

}
