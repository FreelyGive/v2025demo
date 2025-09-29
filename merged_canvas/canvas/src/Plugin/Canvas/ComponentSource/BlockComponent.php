<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Canvas\ComponentSource;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Block\MainContentBlockPluginInterface;
use Drupal\Core\Block\MessagesBlockPluginInterface;
use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinitionInterface;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Plugin\Validation\Constraint\FullyValidatableConstraint;
use Drupal\canvas\Attribute\ComponentSource;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\ComponentSource\ComponentSourceBase;
use Drupal\canvas\Entity\Component as ComponentEntity;
use Drupal\canvas\Form\ClientFormSubmissionHelper;
use Drupal\canvas\MissingComponentInputsException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Validation\ConstraintPropertyPathTranslatorTrait;
use Drupal\system\Plugin\Block\SystemBreadcrumbBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Defines a component source based on block plugins.
 *
 * @todo Context mappings.
 */
#[ComponentSource(
  id: self::SOURCE_PLUGIN_ID,
  label: new TranslatableMarkup('Blocks'),
  // While Canvas does not support context mappings yet, Block plugins also can
  // contain logic and perform e.g. database queries that fetch data to present.
  supportsImplicitInputs: TRUE,
)]
final class BlockComponent extends ComponentSourceBase implements ContainerFactoryPluginInterface {

  use PluginDependencyTrait;
  use ConstraintPropertyPathTranslatorTrait;

  public const SOURCE_PLUGIN_ID = 'block';
  public const EXPLICIT_INPUT_NAME = 'settings';

  /**
   * Constructs a new BlockComponent.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   Block plugin manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly BlockManagerInterface $blockManager,
    private readonly AccountInterface $currentUser,
    private readonly TypedConfigManagerInterface $typedConfigManager,
    private readonly FormBuilderInterface $formBuilder,
    private readonly PluginFormFactoryInterface $pluginFormFactory,
    private readonly AutoSaveManager $autoSaveManager,
  ) {
    assert(array_key_exists('local_source_id', $configuration));
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(BlockManagerInterface::class),
      $container->get(AccountInterface::class),
      $container->get(TypedConfigManagerInterface::class),
      $container->get(FormBuilderInterface::class),
      $container->get(PluginFormFactoryInterface::class),
      $container->get(AutoSaveManager::class),
    );
  }

  /**
   * Generate a component ID given a block plugin ID.
   *
   * @param string $pluginId
   *   Block plugin ID.
   *
   * @return string
   *   Generated component ID.
   */
  public static function componentIdFromBlockPluginId(string $pluginId): string {
    return 'block.' . \str_replace(':', '.', $pluginId);
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedPluginClass(): ?string {
    return $this->blockManager->getDefinition($this->configuration['local_source_id'])['class'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getBlockPlugin(): BlockPluginInterface {
    // @todo this should probably use DefaultSingleLazyPluginCollection
    $block = $this->blockManager->createInstance($this->configuration['local_source_id'], $this->configuration);
    assert($block instanceof BlockPluginInterface);
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return $this->getPluginDependencies($this->getBlockPlugin());
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentDescription(): TranslatableMarkup {
    $pluginDefinition = $this->getBlockPlugin()->getPluginDefinition() ?? [];
    assert(is_array($pluginDefinition));
    return new TranslatableMarkup('Block: %name', [
      '%name' => $pluginDefinition['admin_label'] ?? new TranslatableMarkup('Invalid/broken'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function renderComponent(array $inputs, string $componentUuid, bool $isPreview = FALSE): array {
    $block = $this->getBlockPlugin();

    // @todo Refine to reflect the edited entity route in https://www.drupal.org/i/3509500
    if ($isPreview && $block instanceof SystemBreadcrumbBlock) {
      $block = new SystemBreadcrumbBlock(
        $block->getConfiguration(),
        $block->getPluginId(),
        $block->getPluginDefinition(),
        new class() implements BreadcrumbBuilderInterface {
          use StringTranslationTrait;

          public function applies(RouteMatchInterface $route_match) {
             return TRUE;
          }

          /**
           * In the preview, the breadcrumbs always points to the frontpage.
           */
          public function build(RouteMatchInterface $route_match) {
            $breadcrumb = new Breadcrumb();
            $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
            return $breadcrumb;
          }

        },
        // @phpstan-ignore-next-line
        new RouteMatch('<front>', \Drupal::service(RouteProviderInterface::class)->getRouteByName('<front>')),
      );
    }

    foreach ($inputs[self::EXPLICIT_INPUT_NAME] ?? [] as $key => $value) {
      $block->setConfigurationValue($key, $value);
    }

    // Allow global context to be injected by suspending the fiber.
    // @see \Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant::build()
    if ($block instanceof TitleBlockPluginInterface || $block instanceof MessagesBlockPluginInterface) {
      if (\Fiber::getCurrent() === NULL) {
        throw new \LogicException(sprintf('The %s block plugin does not support previews.', $block->getPluginId()));
      }
      \Fiber::suspend($block);
    }

    // @todo preview fallback handling (in case of no access or emptiness) in https://drupal.org/i/3497990
    // @see \Drupal\layout_builder\EventSubscriber\BlockComponentRenderArray::onBuildRender()
    $build = [
      '#access' => $block->access($this->currentUser, TRUE),
    ];
    $cacheable_metadata = CacheableMetadata::createFromObject($block);
    $cacheable_metadata->applyTo($build);

    assert($build['#access'] instanceof AccessResultInterface);
    if (!$build['#access']->isAllowed()) {
      return $build;
    }

    $build['content'] = $block->build();
    if (Element::isEmpty($build['content'])) {
      return $build;
    }

    // @todo This render array might be refactored in https://www.drupal.org/node/2931040
    // @see \Drupal\block\BlockViewBuilder::buildPreRenderableBlock
    $build += [
      '#theme' => 'block',
      '#configuration' => $block->getConfiguration(),
      '#plugin_id' => $block->getPluginId(),
      '#base_plugin_id' => $block->getBaseId(),
      '#derivative_plugin_id' => $block->getDerivativeId(),
      '#id' => $componentUuid,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExplicitInputDefinitions(): array {
    $block_plugin = $this->getBlockPlugin();
    $plugin_id = $block_plugin->getPluginId();
    $config_schema_type_definition = $this->typedConfigManager->getDefinition('block.settings.' . $plugin_id);
    return self::removeConfigSchemaLabels($config_schema_type_definition);
  }

  private static function removeConfigSchemaLabels(array $config_schema): array {
    $normalized = [];
    foreach ($config_schema as $key => $value) {
      if ($key === 'label') {
        continue;
      }
      if (is_array($value)) {
        $value = self::removeConfigSchemaLabels($value);
      }
      $normalized[$key] = $value;
    }
    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresExplicitInput(): bool {
    return !empty($this->getDefaultExplicitInput());
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultExplicitInput(): array {
    return $this->getBlockPlugin()->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getExplicitInput(string $uuid, ComponentTreeItem $item, ?FieldableEntityInterface $host_entity = NULL): array {

    try {
      return $item->getInputs() ?? [];
    }
    catch (MissingComponentInputsException) {
      // There is no input for this component. That should only be the case for
      // block plugins without any settings.
      assert(!$this->requiresExplicitInput());
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hydrateComponent(array $explicit_input): array {
    return [self::EXPLICIT_INPUT_NAME => $explicit_input];
  }

  /**
   * {@inheritdoc}
   */
  public function inputToClientModel(array $explicit_input): array {
    // @see DynamicComponent type-script definition.
    // @see ComponentModel type-script definition.
    $form_state = new FormState();
    // Some plugins make use of nested form elements such as details elements
    // wrapping the configuration form. Our client values need to reflect those
    // of the form structure.
    // @see \Drupal\system\Plugin\Block\SystemBrandingBlock::blockForm
    $block_plugin = $this->getBlockPlugin();
    $block_plugin->setConfiguration($explicit_input);
    $form_object = $this->buildAnonymousFormForBlockPlugin(\hash('sha256', \json_encode($explicit_input, \JSON_THROW_ON_ERROR)), $block_plugin);
    $form_state->setFormObject($form_object);
    $form = $this->formBuilder->buildForm($form_object, $form_state);
    $values = self::filterClientInput($form_state->getValues(), $form);
    return [
      'resolved' => ($values['settings'] ?? []) +
        // We always pass along the explicit input for label and label_display
        // as we've removed these from the form in
        // ::buildAnonymousFormForBlockPlugin.
      \array_intersect_key($explicit_input, \array_flip([
        'label',
        'label_display',
      ])),
    ];
  }

  protected static function filterClientInput(array $input, array $element): array {
    foreach (Element::children($element) as $child) {
      $child_element = $element[$child];
      $input = self::filterClientInput($input, $child_element);

      if (isset($child_element['#access']) && $child_element['#access'] === FALSE) {
        NestedArray::unsetValue($input, $child_element['#parents']);
      }
    }
    return $input;
  }

  /**
   * {@inheritdoc}
   */
  public function buildComponentInstanceForm(
    array $form,
    FormStateInterface $form_state,
    ?ComponentEntity $component = NULL,
    string $component_instance_uuid = '',
    array $inputValues = [],
    ?EntityInterface $entity = NULL,
    array $settings = [],
  ): array {
    $blockPlugin = $this->getBlockPlugin();
    if ($inputValues) {
      $blockPlugin->setConfiguration($inputValues);
    }
    // Mirror the sub-form logic from the Block config entity form.
    // @see \Drupal\block\BlockForm::form
    $form['#tree'] = TRUE;
    $form['settings'] = [
      '#parents' => $form['#parents'],
    ];
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $form['settings'] = $this->getPluginForm($blockPlugin)->buildConfigurationForm($form['settings'], $subform_state);
    // These fields are added by \Drupal\Core\Block\BlockBase - but we don't
    // want them to factor into the calculation of the input values or client
    // model. The value of these keys are added back to the input
    // in ::clientModelToInput so we can safely remove them.
    foreach (['label', 'admin_label', 'label_display'] as $field) {
      unset($form['settings'][$field]);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientSideInfo(ComponentEntity $component): array {
    // These 2 block plugin interfaces cannot be previewed (regardless of which
    // implementation) because they depend on the global context.
    // @see `type: canvas.page_region.*`'s `component_trees.tree.presence`
    $block_plugin = $this->getBlockPlugin();
    if ($block_plugin instanceof TitleBlockPluginInterface || $block_plugin instanceof MessagesBlockPluginInterface) {
      return ['build' => []];
    }

    return ['build' => $this->renderComponent([], $component->uuid(), TRUE)];
  }

  /**
   * {@inheritdoc}
   */
  public function clientModelToInput(string $component_instance_uuid, ComponentEntity $component, array $client_model, ?FieldableEntityInterface $host_entity, ?ConstraintViolationListInterface $violations = NULL): array {
    // @todo Remove this in https://www.drupal.org/project/canvas/issues/3500994#comment-15951774 — the client should send the right data.
    $defaults = $component->get('settings')['default_settings'];
    // 💡 The client side's simplest model (`ComponentModel`) is used for
    // components of this ("block") ComponentSource: because block plugins
    // provide their own input UX, no evaluation of PropSources is needed.
    // @see docs/components.md#3.2.1
    // @see DynamicComponent type-script definition.
    // @see ComponentModel type-script definition.
    // @todo Make this less confusing in https://www.drupal.org/project/canvas/issues/3521041
    if ($client_model === ['resolved' => []]) {
      // This is the default case for a new component, initialize with defaults.
      // @see addNewComponentToLayout AppThunk in layoutModelSlice.ts
      $client_model['resolved'] = $defaults;
    }
    $input = $client_model['resolved'] ?? [];
    $label_input = \array_intersect_key($input, \array_flip([
      // We remove these from the form in ::submitBlockConfigurationForm and
      // ::buildConfigurationForm, so let's make note of their values before
      // submitting the form.
      'label',
      'label_display',
    ]));

    $block_plugin = $this->getBlockPlugin();
    $this->submitBlockConfigurationForm($block_plugin, $component_instance_uuid, $input);
    $input = $label_input + \array_intersect_key(
      // Ignore any keys from configuration that don't exist in configuration.
      $block_plugin->getConfiguration(),
      // But also ignore any submitted values for label/label_display as we
      // don't show those fields to the user and will fill them from
      // $label_input.
      \array_diff_key($defaults, \array_flip(['label', 'label_display']))
    );

    // We don't need to store these as they can be recalculated based on the
    // plugin ID.
    $input += $defaults;
    unset($input['provider'], $input['id']);
    return $input;
  }

  /**
   * {@inheritdoc}
   */
  public function validateComponentInput(array $inputValues, string $component_instance_uuid, ?FieldableEntityInterface $entity): ConstraintViolationListInterface {
    if (!$this->requiresExplicitInput()) {
      return new ConstraintViolationList();
    }
    $block_plugin = $this->getBlockPlugin();
    $plugin_id = $block_plugin->getPluginId();
    $definition = $block_plugin->getPluginDefinition();
    $form_violations = $this->autoSaveManager->getComponentInstanceFormViolations($component_instance_uuid);
    \assert(\is_array($definition));
    // We don't store these, but they're needed for validation.
    $inputValues += [
      'id' => $plugin_id,
      'provider' => $definition['provider'] ?? 'system',
    ];
    $typed_data = $this->typedConfigManager->createFromNameAndData('block.settings.' . $plugin_id, $inputValues);
    $violations = $typed_data->validate();
    $violations->addAll($form_violations);
    return $this->translateConstraintPropertyPathsAndRoot(['' => \sprintf('inputs.%s.', $component_instance_uuid)], $violations);
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): void {
    $block = $this->getBlockPlugin();
    // The main content is rendered in a fixed position.
    // @see \Drupal\canvas\Plugin\DisplayVariant\CanvasPageVariant::build()
    if ($block instanceof MainContentBlockPluginInterface) {
      return;
    }
    $settings = $block->defaultConfiguration();
    $data_definition = $this->typedConfigManager->createFromNameAndData('block.settings.' . $block->getPluginId(), $settings);
    // We currently support only block plugins with no settings, or if they do
    // have settings, they must be fully validatable.
    $fullyValidatable = FALSE;
    foreach ($data_definition->getConstraints() as $constraint) {
      if ($constraint instanceof FullyValidatableConstraint) {
        $fullyValidatable = TRUE;
        break;
      }
    }

    $reasons = [];
    if (!empty($settings) && !$fullyValidatable) {
      $reasons[] = 'Block plugin settings must opt into strict validation. Use the FullyValidatable constraint. See https://www.drupal.org/node/3404425';
    }

    $plugin_definition = $block->getPluginDefinition();
    assert(is_array($plugin_definition));
    $required_contexts = array_filter(
      $plugin_definition['context_definitions'],
      fn (ContextDefinitionInterface $definition): bool => $definition->isRequired(),
    );
    if ($required_contexts) {
      $reasons[] = 'Block plugins that require context values are not supported.';
    }

    if ($reasons) {
      throw new ComponentDoesNotMeetRequirementsException($reasons);
    }
  }

  protected function submitBlockConfigurationForm(
    BlockPluginInterface $block_plugin,
    string $component_instance_uuid,
    array $input,
  ): FormStateInterface {
    $form_state = new FormState();
    // Some plugins make use of form elements that modify the submitted values
    // during validation. Additionally, the block plugin might make changes to
    // the values from the form in its ::blockSubmit or ::blockValidate method.
    // To call these methods we make an anonymous form class with the block
    // plugin form in a sub-form and submit it with the values from the client
    // model.
    $form_object = $this->buildAnonymousFormForBlockPlugin($component_instance_uuid, $block_plugin);
    $form_state = ClientFormSubmissionHelper::prepareProgrammedFormStateForFormObject($form_state, $form_object)
      // With the values provided from the front-end.
      ->setValues(['settings' => $input]);
    $this->formBuilder->submitForm($form_object, $form_state);
    return $form_state;
  }

  protected function getPluginForm(BlockPluginInterface $block): PluginFormInterface {
    // Mirror the logic from BlockForm::getPluginForm.
    if ($block instanceof PluginWithFormsInterface) {
      return $this->pluginFormFactory->createInstance($block, 'configure');
    }
    return $block;
  }

  protected function buildAnonymousFormForBlockPlugin(string $form_id, BlockPluginInterface $block_plugin): FormInterface {
    // Build and return an ephemeral form object that can be used with the form
    // builder to validate and submit block plugin forms. This gives any block
    // plugins that have logic in these methods a chance to modify the client
    // model when transforming it to input and vice versa.
    return new class(
      $form_id,
      $block_plugin,
      $this->pluginFormFactory,
      $this->autoSaveManager,
    ) implements FormInterface {

      public function __construct(
        protected readonly string $formId,
        protected readonly BlockPluginInterface $blockPlugin,
        protected readonly PluginFormFactoryInterface $formFactory,
        protected readonly AutoSaveManager $autoSaveManager,
      ) {
      }

      public function getFormId(): string {
        return $this->formId;
      }

      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['#tree'] = TRUE;
        $form['settings'] = [];
        $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
        $form['settings'] = $this->getPluginForm($this->blockPlugin)
          ->buildConfigurationForm($form['settings'], $subform_state);
        foreach ([
          'label',
          'label_display',
          'admin_label',
          'provider',
        ] as $field) {
          // Remove these fields and let the defaults be added from
          // the component's default_settings in ::clientModelToInput.
          unset($form['settings'][$field]);
        }
        return $form;
      }

      public function validateForm(array &$form, FormStateInterface $form_state): void {
        // Give the block plugin a chance to perform any logic in its
        // ::blockValidate method.
        $checkboxes = ClientFormSubmissionHelper::spotCheckboxesParents($form['settings']);
        $input = $form_state->getUserInput();
        foreach ($checkboxes as $parents) {
          $value = NestedArray::getValue($input, $parents);
          // Unchecked checkboxes are expected to be set with value NULL. For
          // a normal form submission, this is done for us by the Form
          // Builder. But for a programmatic form submission, this needs to be
          // done manually. However, in this case we're not dealing with a
          // programmatic form submission so it is appropriate to set the value
          // directly to a boolean both in the form state values and user input.
          // We use empty as this covers NULL, FALSE, 0 and '0' by design.
          // @see \Drupal\Core\Form\FormBuilder::handleInputElement
          NestedArray::setValue($input, $parents, !empty($value) && $value !== 'false');
          $form_state->setValue($parents, !empty($value) && $value !== 'false');
        }
        $form_state->setUserInput($input);
        $this->getPluginForm($this->blockPlugin)->validateConfigurationForm(
          $form['settings'],
          SubformState::createForSubform($form['settings'], $form, $form_state),
        );
        $errors = $form_state->getErrors();
        if (\count($errors) > 0) {
          $violations_list = new ConstraintViolationList();
          foreach ($errors as $element_path => $error) {
            $parents = \explode('][', $element_path);
            $element = NestedArray::getValue($form, $parents);
            // Check for #required errors.
            if (($error instanceof TranslatableMarkup && $error->getUntranslatedString() === '@name field is required.') ||
              ((string) $error === ($element['#required_error'] ?? NULL))) {
              // Fall-back to the default value and ignore the error.
              $form_state->setValue($parents, NestedArray::getValue($input, $parents));
              continue;
            }
            // Remove the 'settings' key added in the ::buildForm method.
            \array_shift($parents);
            $violations_list->add(new ConstraintViolation(
            // Some errors may contain markup from the user of % placeholders in
            // TranslatableMarkup. We just want the plain text version.
              PlainTextOutput::renderFromHtml((string) $error),
              NULL,
              [],
              NULL,
              \implode('.', $parents),
              $form_state->getValue($parents),
            ));
          }
          // Store block form validation errors so they can be used later during
          // component validation.
          $this->autoSaveManager->saveComponentInstanceFormViolations($this->formId, $violations_list);
          // Clear errors so that ::submitForm is still called - we're not using
          // this to store any data etc, we're just making sure that any block
          // plugins that have logic in the ::blockValidate and ::blockSubmit
          // methods get a chance to perform that logic during conversion from
          // the client model (form values) to input (block settings that comply
          // to the config schema).
          $form_state->clearErrors();
          return;
        }
        // No violations, so clean the auto-save manager in case previous form
        // violations existed and were stored.
        $this->autoSaveManager->saveComponentInstanceFormViolations($this->formId);
      }

      public function submitForm(array &$form, FormStateInterface $form_state): void {
        // Give the block plugin a chance to perform any logic in its
        // ::blockSubmit method.
        $this->getPluginForm($this->blockPlugin)->submitConfigurationForm(
          $form['settings'],
          SubformState::createForSubform($form['settings'], $form, $form_state),
        );
      }

      protected function getPluginForm(BlockPluginInterface $block): PluginFormInterface {
        // Mirror the logic from BlockForm::getPluginForm.
        if ($block instanceof PluginWithFormsInterface) {
          return $this->formFactory->createInstance($block, 'configure');
        }
        return $block;
      }

    };
  }

}
