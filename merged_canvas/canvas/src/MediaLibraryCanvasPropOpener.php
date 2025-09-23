<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Access\CanvasUiAccessCheck;
use Drupal\media_library\MediaLibraryFieldWidgetOpener;
use Drupal\media_library\MediaLibraryState;

/**
 * The media library opener for Canvas props.
 *
 * @see \Drupal\canvas\Form\ComponentInstanceForm
 * @see \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::fieldWidgetSingleElementMediaLibraryWidgetFormAlter()
 *
 * @internal
 *   This is an internal part of Media Library's Drupal Canvas integration.
 */
final class MediaLibraryCanvasPropOpener extends MediaLibraryFieldWidgetOpener {

  public function __construct(
    private readonly CanvasUiAccessCheck $canvasUiAccessCheck,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function checkAccess(MediaLibraryState $state, AccountInterface $account) {
    // `field_widget_id` is necessary for the inherited, unaltered
    // `::getSelectionResponse()` method.
    $parameters = $state->getOpenerParameters();
    if (!array_key_exists('field_widget_id', $parameters)) {
      return AccessResult::forbidden("field_widget_id parameter is missing.")->addCacheableDependency($state);
    }

    // No further access checking is necessary: this can only be reached if Canvas
    // triggered this, plus MediaLibraryState::fromRequest() already validated
    // the hash.
    // @see \Drupal\media_library\MediaLibraryState::fromRequest()
    // @see \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::fieldWidgetSingleElementMediaLibraryWidgetFormAlter()
    assert($state->isValidHash($state->getHash()));
    // Still, in case this URL is shared, still require that the current session
    // is for a user that has sufficient permissions to use Canvas.
    return $this->canvasUiAccessCheck->access($account);
  }

}
