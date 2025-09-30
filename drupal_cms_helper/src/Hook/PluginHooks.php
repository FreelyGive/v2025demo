<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class PluginHooks {

  use StringTranslationTrait;

  #[Hook('project_browser_source_info_alter')]
  public function projectBrowserSourceInfoAlter(array &$definitions): void {
    $definition = &$definitions['drupalorg_jsonapi'];
    if (strval($definition['label']) === strval($definition['local_task']['title'])) {
      $definition['local_task']['title'] = $this->t('Browse modules');
    }
  }

}
