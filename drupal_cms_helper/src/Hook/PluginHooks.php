<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\Action\Exists;
use Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver\EntityMethodDeriver;
use Drupal\Core\Config\Action\Plugin\ConfigAction\EntityMethod;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class PluginHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  #[Hook('config_action_alter')]
  public function configActionAlter(array &$definitions): void {
    if ($this->moduleHandler->moduleExists('search_api')) {
      $definitions += [
        'entity_method:search_api_index:reindex' => [
          'class' => EntityMethod::class,
          'provider' => 'drupal_cms_helper',
          'id' => 'entity_method',
          'deriver' => EntityMethodDeriver::class,
          'admin_label' => $this->t('Mark all items for reindexing'),
          'entity_types' => ['search_api_index'],
          'constructor_args' => [
            'method' => 'reindex',
            'exists' => Exists::ErrorIfNotExists,
            'numberOfParams' => 0,
            'numberOfRequiredParams' => 0,
            'pluralized' => FALSE,
          ],
        ],
      ];
    }
  }

  #[Hook('project_browser_source_info_alter')]
  public function projectBrowserSourceInfoAlter(array &$definitions): void {
    $definition = &$definitions['drupalorg_jsonapi'];
    if (strval($definition['label']) === strval($definition['local_task']['title'])) {
      $definition['local_task']['title'] = $this->t('Browse modules');
    }
  }

}
