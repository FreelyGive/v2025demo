<?php

declare(strict_types=1);

namespace Drupal\canvas;

/**
 * Provides link relations for the internal HTTP API.
 *
 * @internal
 */
class CanvasUriDefinitions {

  const string LINK_REL_EDIT = 'edit-form';
  const string LINK_REL_DELETE = 'delete-form';
  const string LINK_REL_DUPLICATE = 'https://drupal.org/project/canvas#link-rel-duplicate';
  const string LINK_REL_SET_AS_HOMEPAGE = 'https://drupal.org/project/canvas#link-rel-set-as-homepage';
  // @see \Drupal\canvas\Controller\ApiUsageControllers::componentDetails()
  const string LINK_REL_USAGE_DETAILS = 'https://drupal.org/project/canvas#link-rel-usage-details';

}
