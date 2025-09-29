<?php

declare(strict_types=1);

namespace Drupal\canvas\ComponentSource;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Defines an interface for component source plugins.
 *
 * A Component is a config entity created by a site builder that allows
 * placement of that component in Drupal Canvas.
 *
 * Each Component config entity is handled by a component source. For example
 * there might be:
 * - an SDC component source — which renders a single-directory component and
 *   needs values for each required SDC prop
 * - a block plugin component source — which renders the a block and needs
 *   settings for the block plugin
 *
 * Not all component sources support slots. A source that supports slots should
 * implement \Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface.
 *
 * @phpstan-import-type PropSourceArray from \Drupal\canvas\PropSource\PropSourceBase
 * @phpstan-import-type SingleComponentInputArray from \Drupal\canvas\Plugin\DataType\ComponentInputs
 * @phpstan-import-type OptimizedExplicitInput from \Drupal\canvas\Plugin\DataType\ComponentInputs
 * @phpstan-import-type OptimizedSingleComponentInputArray from \Drupal\canvas\Plugin\DataType\ComponentInputs
 *
 * @see \Drupal\canvas\Attribute\ComponentSource
 * @see \Drupal\canvas\ComponentSource\ComponentSourceBase
 * @see \Drupal\canvas\ComponentSource\ComponentSourceManager
 * @see \Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface
 */
interface ComponentSourceInterface extends PluginInspectionInterface, DerivativeInspectionInterface, ConfigurableInterface, DependentPluginInterface, ContextAwarePluginInterface {

  /**
   * Gets referenced plugin classes for this instance.
   *
   * This is used in validation to allow component tree items to limit the type
   * of plugins that can be referenced. For example, the main content block
   * can't be referenced by a content entity's component tree.
   *
   * @return class-string|null
   *   An FQCN of any plugin classes that this source plugin is referencing. For
   *   example a block source plugin might return the block plugin class it is
   *   referencing here.
   */
  public function getReferencedPluginClass(): ?string;

  /**
   * Gets the ID that this source knows to interpret.
   *
   * ⚠️ This is NOT to be confused with the Component config entity's ID!
   *
   * For example:
   * - `sdc.olivero.teaser` is the ID of a Component config entity
   * - that Component config entity uses the `sdc` ComponentSource plugin
   * - that Component config entity has its `source_local_id` property set to
   *   `olivero:teaser`
   * - `olivero:teaser` is the source-specific ID: only that ComponentSource
   *   plugin knows how to load it.
   *
   * @return string
   */
  public function getSourceSpecificComponentId(): string;

  /**
   * Gets a description of the component.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Description.
   */
  public function getComponentDescription(): TranslatableMarkup;

  /**
   * Renders a component for the given instance.
   *
   * @param array $inputs
   *   Component inputs — both implicit and explicit.
   * @param string $componentUuid
   *   Component UUID.
   * @param bool $isPreview
   *   TRUE if is preview.
   *
   * @return array
   *   Render array.
   */
  public function renderComponent(array $inputs, string $componentUuid, bool $isPreview): array;

  public function generateVersionHash(): string;

  /**
   * Whether this component requires explicit input or not.
   */
  public function requiresExplicitInput(): bool;

  /**
   * Returns the default explicit input (prop sources) for this component.
   *
   * @phpcs:ignore
   * @return SingleComponentInputArray
   *   An array of prop sources to use for the inputs of this component, keyed
   *   by input name.
   */
  public function getDefaultExplicitInput(): array;

  /**
   * Retrieves the component instance's explicit (possibly empty) input.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $host_entity
   *   Host entity. Required when a component instance has inputs populated by
   *   DynamicPropSources AND the parent entity of $item is not the host entity
   *   to use during evaluation of the DynamicPropSources. (Typically: when
   *   this is a component instance in a ContentTemplate.)
   *
   * @todo Add ::getImplicitInput() in https://www.drupal.org/project/canvas/issues/3485502 — SDCs don't have implicit inputs, but Block plugins do: contexts
   */
  public function getExplicitInput(string $uuid, ComponentTreeItem $item, ?FieldableEntityInterface $host_entity = NULL): array;

  /**
   * Hydrates a component with its explicit input plus slots (if any).
   *
   * Note that the result contains the default slot value, because this method
   * only handles a single component instance, not a component tree. Populating
   * slots with component instance happens later.
   *
   * @return array{'slots'?: array<string, string>}
   *
   * @see \Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface::setSlots()
   */
  public function hydrateComponent(array $explicit_input): array;

  /**
   * Converts (stored) explicit inputs to the data model expected by the client.
   *
   * Note that the result MUST NOT contain slot information.
   *
   * @param array $explicit_input
   *
   * @return array
   *   An array with at minimum the 'resolved' key, possibly more. Each
   *   ComponentSource plugin is free to choose its own client-side data model.
   *
   * @see ComponentModel
   * @see openapi.yml
   * @see ::clientModelToInput()
   * @see \Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface::normalizeForClientSide
   */
  public function inputToClientModel(array $explicit_input): array;

  /**
   * Gets the plugin definition.
   *
   * @return array
   *   Plugin definition.
   */
  public function getPluginDefinition(): array;

  /**
   * Returns information the client side needs for the Canvas UI.
   *
   * @param \Drupal\canvas\Entity\Component $component
   *   A component config entity that uses this source.
   *
   * @return array{'source'?: string, 'build': array<string, mixed>, propSources?: array<string, array>}
   *   Client side metadata including a build array for the default markup.
   *
   * @see \Drupal\canvas\Controller\ApiComponentsController
   */
  public function getClientSideInfo(Component $component): array;

  /**
   * Component instance form constructor.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\canvas\Entity\Component|null $component
   *   The component configuration entity.
   * @param string $component_instance_uuid
   *   The component instance UUID.
   * @param array $inputValues
   *   Current client model values for the component from the incoming request,
   *   as returned by ::clientModelToInput().
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The host entity (for evaluated input).
   * @param array $settings
   *   The component configuration entity settings.
   *
   * @return array
   *   The form structure.
   *
   * @see ::inputToClientModel()
   * @see ::clientModelToInput()
   * @see \Drupal\Core\Plugin\PluginFormInterface::buildConfigurationForm()
   */
  public function buildComponentInstanceForm(
    array $form,
    FormStateInterface $form_state,
    ?Component $component = NULL,
    string $component_instance_uuid = '',
    array $inputValues = [],
    ?EntityInterface $entity = NULL,
    array $settings = [],
  ): array;

  /**
   * Converts client data model (typically form value) to input (stored value).
   *
   * Note that each ComponentSource plugin is free to choose its own client-side
   * data model.
   *
   * If your component source needs to perform form submissions to retrieve
   * validation errors, you can make use of the AutoSaveManager to store these
   * for future retrieval.
   *
   * @param string $component_instance_uuid
   *   Component instance UUID.
   * @param \Drupal\canvas\Entity\Component $component
   *   Component for this instance.
   * @param array{source: SingleComponentInputArray, resolved: array<string, mixed>} $client_model
   *   Client model for this component.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $host_entity
   *   Host entity. Required when a component instance has inputs populated by
   *   DynamicPropSources.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface|null $violations
   *   If validation should be performed, a violation constraint list, or NULL
   *   otherwise. Use ::addViolation to add violations detected during conversion.
   *
   * @phpcs:ignore
   * @return OptimizedSingleComponentInputArray
   *
   * @see ::inputToClientModel()
   * @see \Drupal\canvas\AutoSave\AutoSaveManager::saveComponentInstanceFormViolations
   * @see \Drupal\canvas\PropSource\DynamicPropSource
   * @todo Refactor to use the Symfony denormalizer infrastructure?
   */
  public function clientModelToInput(string $component_instance_uuid, Component $component, array $client_model, ?FieldableEntityInterface $host_entity, ?ConstraintViolationListInterface $violations = NULL): array;

  /**
   * Validates component input.
   *
   * @param array $inputValues
   *   Input values stored for this component.
   * @param string $component_instance_uuid
   *   Component instance UUID.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   Host entity.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   Any violations.
   */
  public function validateComponentInput(array $inputValues, string $component_instance_uuid, ?FieldableEntityInterface $entity): ConstraintViolationListInterface;

  /**
   * Checks if component meets requirements.
   *
   * @throws \Drupal\canvas\ComponentDoesNotMeetRequirementsException
   *   When the component does not meet requirements.
   */
  public function checkRequirements(): void;

  /**
   * Optimize component inputs prior to saving.
   *
   * For example a component source plugin may with to store a normalized
   * representation of its data.
   *
   * @param SingleComponentInputArray|OptimizedSingleComponentInputArray $values
   *   Input values to optimize.
   *
   * @return OptimizedSingleComponentInputArray
   *   Optimized values.
   */
  public function optimizeExplicitInput(array $values): array;

}
