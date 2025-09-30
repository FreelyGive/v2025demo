<?php

namespace Drupal\canvas_ai;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Yaml\Yaml;
use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Plugin\Canvas\ComponentSource\BlockComponent;
use Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent;
use Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent;
use Drupal\Core\Plugin\PluginWithFormsInterface;

/**
 * Provides helper methods for AI page builder.
 */
class CanvasAiPageBuilderHelper {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Theme\ComponentPluginManager $componentPluginManager
   *   The component plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
   *   The HTTP kernel.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The stack of requests.
   */
  public function __construct(
    private readonly ComponentPluginManager $componentPluginManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly HttpKernelInterface $httpKernel,
    private readonly RequestStack $requestStack,
  ) {
  }

  /**
   * Gets the data of all the usable component entities.
   *
   * The output will be used as the context for the AI agent.
   */
  public function getComponentContextForAi(): string {
    $component_context = [];
    $component_context_from_config = $this->getComponentContextFromConfig();
    $available_components = $this->getAllComponentsKeyedBySource();

    foreach ($component_context_from_config as $components) {
      foreach ($components as $component_id => $data) {
        $component_context[$component_id] = $data;
      }
    }

    foreach ($available_components as $components) {
      // Component info would be under 'components' key, when not loaded from
      // config.
      if (isset($components['components'])) {
        foreach ($components['components'] as $component_id => $data) {
          // Check if the data differs from the config data.
          if (!isset($component_context[$component_id]) || $component_context[$component_id] !== $data) {
            // Merge the data, giving preference to the non-config data.
            $component_context[$component_id] = ($component_context[$component_id] ?? []) + $data;
          }
        }
      }
      else {
        foreach ($components as $component_id => $data) {
          if (!isset($component_context[$component_id]) || $component_context[$component_id] !== $data) {
            $component_context[$component_id] = ($component_context[$component_id] ?? []) + $data;
          }
        }
      }
    }
    return Yaml::dump($component_context, 4, 2);
  }

  /**
   * Converts a YAML string to an array format with calculated nodePaths.
   *
   * @param string $yaml_string
   *   The YAML string to convert.
   *
   * @return array
   *   Structured array with calculated nodePaths for components.
   */
  public function customYamlToArrayMapper(string $yaml_string): array {
    $parsed_yaml = Yaml::parse($yaml_string);

    $result = [
      'operations' => [
        [
          'operation' => 'ADD',
          'components' => [],
        ],
      ],
      'message' => '',
    ];

    $reference_path = $parsed_yaml['reference_nodepath'] ?? [];
    $result['message'] = $parsed_yaml['message'] ?? 'The changes have been made.';
    $placement = $parsed_yaml['placement'] ?? 'below';
    $components = $parsed_yaml['components'] ?? [];

    switch ($placement) {
      case 'below':
        $this->processComponentsBelow($components, $reference_path, $result['operations'][0]['components']);
        break;

      case 'above':
        $this->processComponentsAbove($components, $reference_path, $result['operations'][0]['components']);
        break;
    }

    return $result;
  }

  /**
   * Process components for 'below' placement.
   *
   * @param array $components
   *   The components to process.
   * @param array $reference_path
   *   The reference nodePath.
   * @param array &$result_components
   *   The array to store processed components.
   */
  protected function processComponentsBelow(array $components, array $reference_path, array &$result_components): void {
    $first_node_path = $reference_path;
    $first_node_path[count($first_node_path) - 1]++;

    $this->processComponents($components, $first_node_path, $result_components);
  }

  /**
   * Process components for 'above' placement.
   *
   * @param array $components
   *   The components to process.
   * @param array $reference_path
   *   The reference nodePath.
   * @param array &$result_components
   *   The array to store processed components.
   */
  protected function processComponentsAbove(array $components, array $reference_path, array &$result_components): void {
    $this->processComponents($components, $reference_path, $result_components);
  }

  /**
   * Process component slots recursively.
   *
   * @param array $slots
   *   The slots to process.
   * @param array $parent_node_path
   *   The parent component's nodePath.
   * @param array &$result_components
   *   The array to store processed components.
   * @param string $component_id
   *   The component ID for the component having this slot.
   */
  protected function processSlots(array $slots, array $parent_node_path, array &$result_components, $component_id): void {

    foreach ($slots as $slot_name => $slot_components) {
      if (!is_array($slot_components)) {
        continue;
      }

      $slot_index = $this->getSlotIndexFromSlotName($slot_name, $component_id);

      foreach ($slot_components as $component_index => $component) {
        foreach ($component as $component_type => $component_data) {
          $node_path = $parent_node_path;
          $node_path[] = $slot_index;
          $node_path[] = $component_index;

          $component_structure = [
            'id' => $component_type,
            'nodePath' => $node_path,
            'fieldValues' => $component_data['props'] ?? [],
          ];

          $result_components[] = $component_structure;

          if (isset($component_data['slots'])) {
            $this->processSlots($component_data['slots'], $node_path, $result_components, $component_type);
          }
        }
      }
    }
  }

  /**
   * Process components and calculate nodePaths.
   *
   * @param array $components
   *   Components to process.
   * @param array $first_node_path
   *   First component's nodePath.
   * @param array &$result_components
   *   Array to store results.
   */
  protected function processComponents(array $components, array $first_node_path, array &$result_components): void {
    $current_node_path = $first_node_path;

    foreach ($components as $component) {
      foreach ($component as $component_type => $component_data) {
        $component_structure = [
          'id' => $component_type,
          'nodePath' => $current_node_path,
          'fieldValues' => $component_data['props'] ?? [],
        ];

        $result_components[] = $component_structure;

        if (isset($component_data['slots'])) {
          $this->processSlots($component_data['slots'], $current_node_path, $result_components, $component_type);
        }

        $current_node_path[count($current_node_path) - 1]++;
      }
    }
  }

  /**
   * Gets all the component entities keyed by source plugin id.
   *
   * @return array
   *   The components keyed by source.
   */
  public function getAllComponentsKeyedBySource(): array {
    $output = [];
    $current_request = $this->requestStack->getCurrentRequest();
    $sub_request = Request::create(
      Url::fromRoute('canvas.api.config.list', ['canvas_config_entity_type_id' => Component::ENTITY_TYPE_ID])->toString(),
      'GET',
      [],
      $current_request?->cookies->all() ?? [],
      [],
      $current_request?->server->all() ?? []
    );
    $sub_request->attributes->set('_format', 'json');
    try {
      $available_components_response = $this->httpKernel->handle($sub_request, HttpKernelInterface::SUB_REQUEST);
      $available_components = (string) $available_components_response->getContent();
      $available_components = Json::decode($available_components);
    } catch (\Exception) {
      return [];
    }
    if (empty($available_components)) {
      return [];
    }

    /** @var \Drupal\canvas\Entity\Component[] $component_entities */
    $component_entities = $this->entityTypeManager->getStorage(Component::ENTITY_TYPE_ID)->loadMultiple(array_keys($available_components));
    $sdc_definitions = $this->componentPluginManager->getDefinitions();

    foreach ($component_entities as $component) {
      $source = $component->getComponentSource()->getPluginId();
      $source_label = (string) $component->getComponentSource()->getPluginDefinition()['label'];
      if (empty($source_label)) {
        $source_label = $source;
      }
      $output[$source]['label'] = $source_label;
      $component_id = $component->id();

      if ($source === SingleDirectoryComponent::SOURCE_PLUGIN_ID) {
        $this->processSdc($component, $sdc_definitions, $output);
      }
      elseif ($source === JsComponent::SOURCE_PLUGIN_ID) {
        $this->processCodeComponents($component, $output, $available_components[$component_id]);
      }
      elseif ($source === BlockComponent::SOURCE_PLUGIN_ID) {
        // Block components: id, name, description (description = name)
        $output[$source]['components'][$component_id] = [
          'id' => $component_id,
          'name' => $component->label(),
          'description' => $component->label(),
          'props' => [
            'label' => [
              'name' => 'label',
              'description' => $this->t('The block title. Required.'),
              'type' => 'string',
              'default' => '',
            ],
            'label_display' => [
              'name' => 'label_display',
              'description' => $this->t('Whether to display the block title. Required.'),
              'type' => 'boolean',
              'default' => TRUE,
            ],
          ],
        ];
        // Load via the block plugin manager to get more details.
        $block_plugin = \Drupal::service('plugin.manager.block')->createInstance($component->get('source_local_id'));
        // Get the default configuration of the block plugin.
        if ($block_plugin instanceof PluginWithFormsInterface) {
          $config = $block_plugin->defaultConfiguration();
          foreach ($config as $key => $value) {
            // Skip the already added props.
            if (in_array($key, ['label', 'label_display'], TRUE)) {
              continue;
            }
            // Try to figure out type from default value.
            $block_input_type = 'string';
            if (is_bool($value)) {
              $block_input_type = 'boolean';
            }
            elseif (is_numeric($value)) {
              $block_input_type = 'number';
            }
            $output[$source]['components'][$component_id]['props'][$key] = [
              'name' => $key,
              'description' => $key . '. Is required, even if just empty.',
              'type' => $block_input_type,
              'default' => $value,
            ];
          }
        }
      }
      else {
        // Other sources: id, name, description (description = name)
        $output[$source]['components'][$component_id] = [
          'id' => $component_id,
          'name' => $component->label(),
          'description' => $component->label(),
        ];
      }
    }
    return $output;
  }

  /**
   * Gets the component context from the config.
   *
   * @return array
   *   The component context array.
   */
  public function getComponentContextFromConfig(): array {
    $config = $this->configFactory->get('canvas_ai.component_description.settings');
    $component_context = $config->get('component_context');

    if (empty($component_context)) {
      return [];
    }

    // Refresh the config to ensure it has the latest components.
    $this->refreshComponentContext($component_context);

    // Provide only the components from enabled sources.
    foreach ($component_context as $source => $components) {
      if ($components['enabled']) {
        $enabled_sources[$source] = Yaml::parse($components['data']);
      }
      foreach (Yaml::parse($components['data']) as $component_id => $component_data) {
        // If a component is marked as hidden, remove it from the context.
        if (isset($component_data['hidden']) && $component_data['hidden']) {
          unset($enabled_sources[$source][$component_id]);
        }
      }
    }

    return $enabled_sources ?? [];
  }

  /**
   * Updates the component context in the config, if there are changes.
   *
   * @param array $component_context
   *   The component context array loaded from the config.
   */
  private function refreshComponentContext(array &$component_context): array {
    // Update the config with the data of newly added/removed components.
    $latest_components = $this->getAllComponentsKeyedBySource();
    $resave_config = FALSE;
    $has_changes = FALSE;

    foreach ($component_context as $source => &$source_info) {
      $source_components_in_config = $source_info['data'] ?? [];
      $source_components_in_config = Yaml::parse($source_components_in_config);
      $latest_components_under_source = $latest_components[$source]['components'] ?? [];
      // Remove components that are not in the latest components.
      $new_config = array_intersect_key($source_components_in_config, $latest_components_under_source);
      // Add new components that are in the latest components but not in the config.
      $new_config += array_diff_key($latest_components_under_source, $new_config);
      // Refresh the props and slots for the components.
      $has_changes = $this->refreshPropsAndSlots($new_config, $latest_components_under_source);
      // Save the changes if there were differences.
      if (array_diff_key($new_config, $source_components_in_config) || array_diff_key($source_components_in_config, $new_config) || $has_changes) {
        $resave_config = TRUE;
        $source_components_in_config = $new_config;
        // Update the source info with the latest components.
        $source_info['data'] = Yaml::dump($source_components_in_config);
      }
    }

    // Save the updated component context to the config only if there were changes.
    if ($resave_config) {
      $this->configFactory->getEditable('canvas_ai.component_description.settings')
        ->set('component_context', $component_context)
        ->save();
    }
    return $component_context;
  }

  /**
   * Refreshes the props and slots for the components.
   *
   * @param array $new_config
   *   The new config with the latest components.
   * @param array $latest_components_under_source
   *   The latest components under the source.
   *
   * @return bool
   *   Returns TRUE if there were changes, FALSE otherwise.
   */
  private function refreshPropsAndSlots(array &$new_config, array $latest_components_under_source): bool {
    $has_changes = FALSE;

    foreach ($new_config as $component_id => &$component_data) {

      // Refresh component props
      if (isset($component_data['props'])) {
        // Check if any new props have been added or existing props have been modified.
        $previous_props = is_array($component_data['props']) ? $component_data['props'] : [];
        $current_props = is_array($latest_components_under_source[$component_id]['props']) ? $latest_components_under_source[$component_id]['props'] : [];

        if (array_keys($previous_props) != array_keys($current_props)) {
          // If the keys of the previous props and current props are different,
          // then there are changes.
          $has_changes = TRUE;
        }

        foreach ($current_props as $prop_name => &$prop_details) {

          // Check if its a new prop.
          if (!isset($previous_props[$prop_name])) {
            continue;
          }

          if (isset($previous_props[$prop_name]) && isset($previous_props[$prop_name]['description'])) {
            // If a description exists in the config for a prop, use that.
            $prop_details['description'] = $previous_props[$prop_name]['description'];
          }

          // Check if any other data of the prop have been modified.
          // Eg: Change in type, default value, enums, etc.
          $previous_prop_data_without_description = array_diff_key($previous_props[$prop_name], ['description' => TRUE]);
          $current_prop_data_without_description = array_diff_key($prop_details, ['description' => TRUE]);
          $differences = DiffArray::diffAssocRecursive($previous_prop_data_without_description, $current_prop_data_without_description);
          $differences += DiffArray::diffAssocRecursive($current_prop_data_without_description, $previous_prop_data_without_description);
          // If there are differences, set has_changes to TRUE.
          if (!empty($differences)) {
            $has_changes = TRUE;
          }
        }
        $component_data['props'] = !empty($current_props) ? $current_props : 'No props';
      }

      // Refresh component slots
      if (isset($component_data['slots'])) {
        // Check if any new slots have been added or existing slots have been modified.
        $previous_slots = is_array($component_data['slots']) ? $component_data['slots'] : [];
        $current_slots = is_array($latest_components_under_source[$component_id]['slots']) ? $latest_components_under_source[$component_id]['slots'] : [];

        if (array_keys($previous_slots) != array_keys($current_slots)) {
          // If the keys of the previous slots and current slots are different,
          // then there are changes.
          $has_changes = TRUE;
        }

        foreach ($current_slots as $slot_name => &$slot_details) {
          // Check if its a new slot.
          if (!isset($previous_slots[$slot_name])) {
            continue;
          }

          if (isset($previous_slots[$slot_name]) && isset($previous_slots[$slot_name]['description'])) {
            // If a description exists in the config for a slot, use that.
            $slot_details['description'] = $previous_slots[$slot_name]['description'];
          }

          // Check if any other slots data have been modified.
          $previous_slot_data_without_description = array_diff_key($previous_slots[$slot_name], ['description' => TRUE]);
          $current_slot_data_without_description = array_diff_key($slot_details, ['description' => TRUE]);
          $differences = DiffArray::diffAssocRecursive($previous_slot_data_without_description, $current_slot_data_without_description);
          $differences += DiffArray::diffAssocRecursive($current_slot_data_without_description, $previous_slot_data_without_description);
          // If there are differences,
          if (!empty($differences)) {
            $has_changes = TRUE;
          }
        }
        $component_data['slots'] = !empty($current_slots) ? $current_slots : 'No slots';
      }
    }
    return $has_changes;
  }

  /**
   * Create the context data for SDCs.
   *
   * @param \Drupal\canvas\Entity\Component $component
   *   The component entity.
   * @param array $sdc_definitions
   *   The SDC definitions.
   * @param array &$output
   *   The output array to store the SDC component data.
   */
  private function processSdc(Component $component, array $sdc_definitions, array &$output): void {
    $sdc_definition = $sdc_definitions[$component->get('source_local_id')];
    $component_id = $component->id();
    $source_id = SingleDirectoryComponent::SOURCE_PLUGIN_ID;
    $output[$source_id]['components'][$component_id] = [
      'id' => $component_id,
      'name' => $sdc_definition['name'],
      'description' => $sdc_definition['description'] ?? $sdc_definition['name'],
      'group' => $sdc_definition['group'] ?? '',
      'props' => 'No props',
      'slots' => 'No slots',
    ];
    // Get slots.
    $slots = $sdc_definition['slots'] ?? [];
    if ($slots) {
      $output[$source_id]['components'][$component_id]['slots'] = [];
      foreach ($slots as $slot => $details) {
        $output[$source_id]['components'][$component_id]['slots'][$slot] = [
          'name' => $details['title'] ?? $slot,
          'description' => $details['description'] ?? 'No description available',
        ];
      }
    }
    // Get props.
    $props = $sdc_definition['props']['properties'] ?? [];
    if ($props) {
      $output[$source_id]['components'][$component_id]['props'] = [];
      foreach ($props as $prop_name => $prop_details) {
        if ($prop_name === 'attributes') {
          continue;
        }
        if ($prop_details['type'] === 'object' && isset($prop_details['examples'][0]['src'])) {
          $prop_details['type'] = 'number';
          $prop_details['default'] = 4;
          $prop_details['description'] .= ' Provide media id or null here.';
        }
        $output[$source_id]['components'][$component_id]['props'][$prop_name] = [
          'name' => $prop_details['title'] ?? $prop_name,
          'description' => $prop_details['description'] ?? 'No description available',
          'type' => $prop_details['type'],
          'default' => $prop_details['default'] ?? $prop_details['examples'][0] ?? NULL,
        ];

        // Mark required props.
        if (isset($sdc_definition['props']['required']) && in_array($prop_name, $sdc_definition['props']['required'], TRUE)) {
          $output[$source_id]['components'][$component_id]['props'][$prop_name]['required'] = TRUE;
        }
        if (isset($prop_details['enum'])) {
          $output[$source_id]['components'][$component_id]['props'][$prop_name]['enum'] = $prop_details['enum'];
        }
      }
    }
  }

  /**
   * Create the context data for JS components.
   *
   * @param \Drupal\canvas\Entity\Component $component
   *   The component entity.
   * @param array &$output
   *   The output array to store the JS component data.
   * @param array $component_data
   *   The component data array containing prop and slots metadata.
   */
  private function processCodeComponents(Component $component, &$output, array $component_data): void {
    $component_id = $component->id();
    $output[JsComponent::SOURCE_PLUGIN_ID]['components'][$component_id] = [
      'id' => $component_id,
      'name' => $component->label(),
      'description' => $component->label(),
    ];

    // Get the descriptions for props of the JS component.
    if (isset($component_data['propSources']) && is_array($component_data['propSources'])) {
      $output[JsComponent::SOURCE_PLUGIN_ID]['components'][$component_id]['props'] = [];
      foreach ($component_data['propSources'] as $prop_name => $prop_details) {
        $output[JsComponent::SOURCE_PLUGIN_ID]['components'][$component_id]['props'][$prop_name] = [
          'name' => $prop_name,
          // Keep the prop description as the prop name for as there is no
          // option to provide a description in the JS component.
          'description' => $prop_name,
          'type' => $prop_details['jsonSchema']['type'],
          'default' => $prop_details['default_values']['resolved'] ?? '',
          'format' => $prop_details['jsonSchema']['format'] ?? '',
          'enum' => $prop_details['jsonSchema']['enum'] ?? '',
        ];
      }
    }

    // Get the descriptions for slots of the JS component.
    if (isset($component_data['slots']) && is_array($component_data['slots'])) {
      $output[JsComponent::SOURCE_PLUGIN_ID]['components'][$component_id]['metadata']['slots'] = [];
      foreach ($component_data['metadata']['slots'] as $slot_name => $slot_details) {
        $output[JsComponent::SOURCE_PLUGIN_ID]['components'][$component_id]['metadata']['slots'][$slot_name] = [
          'name' => $slot_details['title'] ?? $slot_name,
          // Keep the slot description as the slot name for as there is no
          // option to provide a description in the JS component.
          'description' => $slot_name,
        ];
      }
    }
  }

  /**
   * Gets the index of a slot by its name for a given component ID.
   *
   * @param string $slot_name
   *   The name of the slot.
   * @param string $component_id
   *   The ID of component with this slot.
   *
   * @return int
   *   The index of the slot, or 0 if not found.
   */
  public function getSlotIndexFromSlotName(string $slot_name, string $component_id): int {
    $component_context = $this->getAllComponentsKeyedBySource();
    if (empty($component_context)) {
      return 0;
    }

    foreach ($component_context as $source_info) {
      if (isset($source_info['components'][$component_id]['slots'][$slot_name])) {
        $index = array_search($slot_name, array_keys($source_info['components'][$component_id]['slots']));
        return ($index === FALSE) ? 0 : (int) $index;
      }
    }
    return 0;
  }

  /**
   * Gets the region indices from the current layout.
   *
   * @param string $current_layout
   *   The current layout JSON string.
   *
   * @return array
   *   An array with region names as keys and their nodePathPrefix values.
   */
  public function getRegionIndex(string $current_layout): array {
    $layout_array = Json::decode($current_layout);
    $regions = [];

    if (isset($layout_array['layout']) && is_array($layout_array['layout'])) {
      foreach ($layout_array['layout'] as $region_name => $region_data) {
        if (isset($region_data['nodePathPrefix'])) {
          $regions[$region_name] = $region_data['nodePathPrefix'][0];
        }
      }
    }

    return $regions;
  }

  /**
   * Gets the available regions from the current layout along with their descriptions, if configured.
   *
   * @param string $current_layout
   *   The current layout JSON string.
   *
   * @return array
   *   An array with region names as keys and their nodePathPrefix values and descriptions.
   */
  public function getAvailableRegions(string $current_layout): array {
    $region_index_mapping = $this->getRegionIndex($current_layout);
    $region_descriptions = $this->configFactory->get('xb_ai.theme_region.settings')->get('region_descriptions') ?? [];
    $available_regions = [];
    foreach ($region_index_mapping as $region_name => $region_index) {
      $available_regions[$region_name] = [
        'nodePathPrefix' => $region_index,
        'description' => $region_descriptions[$region_name] ?? '',
      ];
    }
    return $available_regions;
  }

  /**
   * Processes the parsed YAML array for UI representation.
   *
   * This function processes the yml generated by the template generation agent
   * and converts it into a JSON structure that can be used in the UI.
   *
   * @param array $parsed_array
   *   The parsed YAML array.
   * @param string $current_layout
   *   The current layout of the page.
   * @param array $reference_nodepath
   *   The nodepath of the reference component, if any.
   */
  public function processTemplateYmlForUi(array $parsed_array, string $current_layout, array $reference_nodepath = []): string {
    $result = [
      'operations' => [
        [
          'operation' => 'ADD',
          'components' => [],
        ],
      ],
    ];
    foreach ($parsed_array as $region => $components) {
      if (!is_array($components)) {
        continue;
      }

      // If reference nodepath is given, calculate the nodepath of other components
      // based on it.
      if ($reference_nodepath) {
        $this->processComponentsBelow($components, $reference_nodepath, $result['operations'][0]['components']);
      } else {
        $region_index_mapping = $this->getRegionIndex($current_layout);

        $component_index = 0;

        $region_index = $region_index_mapping[$region] ?? 0;

        foreach ($components as $index => $component) {
          $this->processComponent($component, $region_index, $index, $result['operations'][0]['components'], $component_index);
          $component_index++;
        }
      }
    }

    return Json::encode($result);
  }

  /**
   * Recursively processes a component and its slots.
   *
   * @param array $component
   *   The component data.
   * @param int $region_index
   *   The region index.
   * @param int $component_index
   *   The component index in the region.
   * @param array $components
   *   The array to store processed components.
   * @param int $global_index
   *   The global component index.
   * @param array $parent_path
   *   The parent node path.
   */
  protected function processComponent(array $component, int $region_index, int $component_index, array &$components, int &$global_index, array $parent_path = []): void {
    foreach ($component as $component_type => $component_data) {
      $node_path = empty($parent_path) ? [$region_index, $component_index] : array_merge($parent_path, [$component_index]);

      $component_structure = [
        'id' => $component_type,
        'nodePath' => $node_path,
        'fieldValues' => [],
      ];

      // Process props if they exist.
      if (isset($component_data['props'])) {
        $component_structure['fieldValues'] = $component_data['props'];
      }

      $components[] = $component_structure;
      $global_index++;

      // Process slots if they exist.
      if (isset($component_data['slots'])) {
        foreach ($component_data['slots'] as $slot_name => $slot_components) {
          if (!is_array($slot_components)) {
            continue;
          }

          foreach ($slot_components as $slot_index => $slot_component) {
            $this->processComponent(
              $slot_component,
              $region_index,
              $slot_index,
              $components,
              $global_index,
              array_merge($node_path, [array_search($slot_name, array_keys($component_data['slots']))])
            );
          }
        }
      }
    }
  }
}
