<?php

declare(strict_types=1);

namespace Drupal\canvas_personalization\Hook;

use Drupal\Core\Hook\Attribute\Hook;

final class SegmentationRulesHooks {

  const string CONDITION_PLUGIN = 'condition.plugin';
  const string CONDITION_CURRENT_THEME = 'condition.plugin.current_theme';
  const string CONDITION_USER_ROLE = 'condition.plugin.user_role';

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   *
   * Filters out the conditions that we want to allow for personalization
   * segmentation rules.
   */
  #[Hook('plugin_filter_condition__canvas_personalization_alter')]
  public function conditionPersonalizationAlter(array &$definitions, array $extra): void {
    $allowlist = [
      // @todo Will remove these when we have the ones we need, but
      //   using these temporarily for testing. See https://drupal.org/i/3527076, which
      //   will be the second one.
      'current_theme',
      'user_role',
    ];
    $valid_providers = ['canvas', 'canvas_personalization'];
    foreach ($definitions as $key => $definition) {
      if (!in_array($key, $allowlist, TRUE) && !in_array($definition['provider'], $valid_providers)) {
        unset($definitions[$key]);
      }
    }
    // Re-order them, as we depend on their sorting (by provider) in our Drupal UI.
    // @todo Revisit in https://www.drupal.org/i/3527086, probably removing this sorting.
    ksort($definitions);
  }

  /**
   * Implements hook_config_schema_info_alter().
   *
   * For condition plugins.
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    // This allows conditions to be used for now, but the schema should actually
    // be FullyValidatable.
    // @todo Fix this in core in https://www.drupal.org/i/3525391
    if (isset($definitions[self::CONDITION_PLUGIN])) {
      $definitions[self::CONDITION_PLUGIN]['constraints']['FullyValidatable'] = \NULL;
      $definitions[self::CONDITION_PLUGIN]['mapping']['context_mapping']['requiredKey'] = FALSE;
      unset($definitions[self::CONDITION_PLUGIN]['mapping']['uuid']);
      // @todo Missing `context_mapping`: https://www.drupal.org/i/3526758
    }

    if (isset($definitions[self::CONDITION_CURRENT_THEME])) {
      /*
       * NotBlank: [ ]
       * ExtensionName: [ ]
       * ExtensionExists: theme
       */
      // @todo Fix this in core in https://www.drupal.org/i/3527385
      $definitions[self::CONDITION_CURRENT_THEME]['constraints']['FullyValidatable'] = \NULL;
      $definitions[self::CONDITION_CURRENT_THEME]['mapping']['theme']['constraints']['NotBlank'] = [];
      $definitions[self::CONDITION_CURRENT_THEME]['mapping']['theme']['constraints']['ExtensionName'] = [];
      $definitions[self::CONDITION_CURRENT_THEME]['mapping']['theme']['constraints']['ExtensionExists'] = 'theme';
    }
    if (isset($definitions[self::CONDITION_USER_ROLE])) {
      /*
       * ConfigExists:
       *  prefix: user.role.
       */
      // @todo Fix this in core in https://www.drupal.org/i/3527382
      $definitions[self::CONDITION_USER_ROLE]['constraints']['FullyValidatable'] = \NULL;
      $definitions[self::CONDITION_USER_ROLE]['mapping']['roles']['sequence']['constraints']['ConfigExists'] = ['prefix' => 'user.role.'];
    }
  }

}
