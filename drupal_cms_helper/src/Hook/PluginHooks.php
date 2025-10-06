<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\Hook;

use Drupal\Core\Config\Action\Exists;
use Drupal\Core\Config\Action\Plugin\ConfigAction\Deriver\EntityMethodDeriver;
use Drupal\Core\Config\Action\Plugin\ConfigAction\EntityMethod;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
    foreach ($definitions as &$definition) {
      if ($definition['id'] === 'entity_method' && $definition['constructor_args']['method'] === 'disable') {
        $definition['constructor_args']['exists'] = Exists::ReturnEarlyIfNotExists;
      }
    }

    // @todo Remove this when https://www.drupal.org/i/3510657 is released.
    if ($this->moduleHandler->moduleExists('linkit')) {
      $definitions['entity_method:linkit.linkit_profile:addMatcher'] ??= [
        'class' => EntityMethod::class,
        'provider' => 'core',
        'id' => 'entity_method',
        'deriver' => EntityMethodDeriver::class,
        'admin_label' => $this->t('Add matcher to profile'),
        'entity_types' => ['linkit_profile'],
        'constructor_args' => [
          'method' => 'addMatcher',
          'exists' => Exists::ErrorIfNotExists,
          'numberOfParams' => 1,
          'numberOfRequiredParams' => 1,
          'pluralized' => FALSE,
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
