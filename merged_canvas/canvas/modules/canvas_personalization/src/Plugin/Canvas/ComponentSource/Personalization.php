<?php

declare(strict_types=1);

namespace Drupal\canvas_personalization\Plugin\Canvas\ComponentSource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Plugin\Validation\Constraint\ConfigExistsConstraint;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\BasicRecursiveValidatorFactory;
use Drupal\canvas\Attribute\ComponentSource;
use Drupal\canvas\ComponentSource\ComponentSourceBase;
use Drupal\canvas\ComponentSource\ComponentSourceWithSlotsInterface;
use Drupal\canvas\ComponentSource\ComponentSourceWithSwitchCasesInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\MissingComponentInputsException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Validation\ConstraintPropertyPathTranslatorTrait;
use Drupal\canvas_personalization\Entity\Segment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Personalization component source providing switch/case components.
 *
 * ⚠️ This is highly experimental and *will* be refactored or even removed.
 *
 * @phpstan-type PersonalizationSwitchInputArray array{variants: array<int, string>}
 * @phpstan-type PersonalizationCaseInputArray array{variant_id: string, segments: array<int, string>}
 * @phpstan-type PersonalizationInputArray PersonalizationSwitchInputArray|PersonalizationCaseInputArray
 */
#[ComponentSource(
  id: self::SOURCE_PLUGIN_ID,
  label: new TranslatableMarkup('Personalization'),
  supportsImplicitInputs: FALSE,
)]
final class Personalization extends ComponentSourceBase implements
  ComponentSourceWithSlotsInterface,
  ComponentSourceWithSwitchCasesInterface,
  ContainerFactoryPluginInterface {

  use ContextAwarePluginTrait;
  use ConstraintPropertyPathTranslatorTrait;

  public const string SOURCE_PLUGIN_ID = 'p13n';

  public const string POC_ONLY_HARDCODED_VARIANTS_DEFAULT = 'default';
  public const string POC_ONLY_HARDCODED_VARIANTS_HALLOWEEN = 'halloween';

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly BasicRecursiveValidatorFactory $validatorFactory,
  ) {
    assert(array_key_exists('local_source_id', $configuration));
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(BasicRecursiveValidatorFactory::class),
    );
  }

  public function getReferencedPluginClass(): ?string {
    return NULL;
  }

  public function getComponentDescription(): TranslatableMarkup {
    return match ($this->getType()) {
      self::SWITCH => new TranslatableMarkup('Personalization'),
      self::CASE => new TranslatableMarkup('Variant'),
    };
  }

  /**
   * @return 'switch'|'case'
   */
  protected function getType(): string {
    return $this->configuration['local_source_id'];
  }

  public function isSwitch(): bool {
    return $this->getType() === self::SWITCH;
  }

  public function isCase():bool {
    return $this->getType() === self::CASE;
  }

  public function renderComponent(array $inputs, string $componentUuid, bool $isPreview): array {
    $build = [];

    // When live rendering:
    if (!$isPreview) {
      // 1. a switch: never visible to the end user, zero markup
      // Note this has no cacheability (beyond the render system's default),
      // because this renders to nothing (the empty string above).
      // Take into account that e.g. if a tree changed because of new added
      // variants, the tree host itself would be invalidated (e.g. node:23
      // would be invalidated).
      if ($this->isSwitch()) {
        return $build;
      }
      // 2. a case: only rendered if it is the negotiated one
      else {
        // @todo We need to render the negotiated `case` cache metadata in both the wrapper and the case. Remove this hardcoded logic in https://www.drupal.org/project/canvas/issues/3525797
        $cache = CacheableMetadata::createFromRenderArray($build);
        $cache->addCacheContexts(['url.query_args:utm_campaign']);
        $cache->applyTo($build);
        if (!$this->isNegotiatedCase($inputs)) {
          return $build;
        }
      }
    }

    // We do render container markup for:
    // - the one negotiated `case` when live rendering
    // - the `switch` and ALL `case`s when previewing
    $build += [
      '#type' => 'container',
      '#attributes' => [
        'canvas_uuid' => $componentUuid,
        'canvas_type' => $this->getType(),
        'canvas_slot_ids' => \array_keys($this->getSlotDefinitions()),
      ],
    ];
    return $build;
  }

  public function isNegotiatedCase(array $inputs): bool {
    if ($this->isSwitch()) {
      throw new \LogicException();
    }
    // @todo Evaluate this `case` component instance's `segments` explicit input against the given contexts (aka from the Drupal context system), and remove this hardcoded logic in https://www.drupal.org/project/canvas/issues/3525797
    // @phpstan-ignore-next-line globalDrupalDependencyInjection.useDependencyInjection
    if (str_contains(\Drupal::request()->getRequestUri(), 'HALLOWEEN')) {
      return in_array('halloween', $inputs['segments']);
    }
    return in_array(Segment::DEFAULT_ID, $inputs['segments']);
  }

  public function requiresExplicitInput(): bool {
    // - `switch` requires variant IDs
    // - `case` requires variant ID + segment IDs
    return TRUE;
  }

  public function getExplicitInput(string $uuid, ComponentTreeItem $item, ?FieldableEntityInterface $host_entity = NULL): array {
    try {
      // Inputs might be NULL, so ensure we return a valid array.
      return $item->getInputs() ?? $this->getDefaultExplicitInput();
    }
    catch (MissingComponentInputsException) {
      return $this->getDefaultExplicitInput();
    }
  }

  public function hydrateComponent(array $explicit_input): array {
    $hydrated = $explicit_input;
    // Set the slots.
    if ($slots = $this->getSlotDefinitions()) {
      // Use the first example defined in the components metadata, which we
      // guarantee it exists.
      $hydrated['slots'] = array_map(fn($slot) => $slot['examples'][0], $slots);
    }
    return $hydrated;
  }

  public function inputToClientModel(array $explicit_input): array {
    // @see DynamicComponent type-script definition.
    // @see ComponentModel type-script definition.
    return ['resolved' => $explicit_input];
  }

  public function getClientSideInfo(Component $component): array {
    // @todo Uncomment the next line and delete everything else once a React UI exists for this: you would never drag these components onto the editor frame. Remove in https://www.drupal.org/project/canvas/issues/3525797
    // throw new \RuntimeException('This should not be called because this source implements ComponentSourceWithSwitchCasesInterface.');

    $client_side_info = [
      'build' => match($this->getType()) {
        self::SWITCH => ['#markup' => '<h1>Switch!</h1'],
        self::CASE => ['#markup' => '<h1>Case!</h1'],
      },
      // @todo UI does not use any other metadata - should `slots` move to top level?
      'metadata' => [
        'slots' => $this->getSlotDefinitions(),
      ],
    ];
    return $client_side_info;
  }

  public function clientModelToInput(string $component_instance_uuid, Component $component, array $client_model, ?FieldableEntityInterface $host_entity, ?ConstraintViolationListInterface $violations = NULL): array {
    return $client_model['resolved'] ?? [];
  }

  public function validateComponentInput(array $inputValues, string $component_instance_uuid, ?FieldableEntityInterface $entity): ConstraintViolationListInterface {
    $variant_id_constraints = new Sequentially([
      new Type('string'),
      new NotBlank(),
      // @see `type: machine_name`
      new Regex(pattern: '/^[a-z0-9_]+$/'),
      // @todo Remove.
      new Choice([static::POC_ONLY_HARDCODED_VARIANTS_HALLOWEEN, static::POC_ONLY_HARDCODED_VARIANTS_DEFAULT]),
    ]);
    $segment_id_constraints = new Sequentially([
      new Type('string'),
      new NotBlank(),
      new ConfigExistsConstraint(['prefix' => sprintf('canvas_personalization.%s.', Segment::ENTITY_TYPE_ID)]),
    ]);

    $component_constraints = match ($this->getType()) {
      self::SWITCH => new Collection(
        fields: [
          'variants' => new Required([
            new Type('array'),
            new NotBlank(),
            new All([$variant_id_constraints]),
          ]),
        ],
        allowExtraFields: FALSE,
      ),
      self::CASE => new Collection(
        fields: [
          'variant_id' => new Required([$variant_id_constraints]),
          'segments' => new Required([
            new Type('array'),
            new NotBlank(),
            new All([$segment_id_constraints]),
          ]),
        ],
        allowExtraFields: FALSE,
      ),
    };

    $non_typed_data_validator = $this->validatorFactory->createValidator();
    $violations = $non_typed_data_validator->validate($inputValues, $component_constraints);
    return $this->translateConstraintPropertyPathsAndRoot(['' => \sprintf('inputs.%s.', $component_instance_uuid)], $violations);
  }

  public function checkRequirements(): void {
    // Do nothing, our components are not dynamic and provided as module config.
  }

  public function calculateDependencies(): array {
    // Because our components have no settings, there also cannot be any
    // additional dependencies for their corresponding Component config
    // entities.
    return [
      'module' => [
        'canvas_personalization',
      ],
    ];
  }

  /**
   * @return PersonalizationInputArray
   * @phpstan-ignore-next-line method.childReturnType
   */
  public function getDefaultExplicitInput(): array {
    return match($this->getType()) {
      self::SWITCH => [
        'variants' => [Segment::DEFAULT_ID],
      ],
      self::CASE => [
        'variant_id' => Segment::DEFAULT_ID,
        'segments' => [Segment::DEFAULT_ID],
      ],
    };
  }

  /**
   * {@inheritdoc}
   *
   * @todo Before offering this functionality to end users, this should switch to returning a declarative representation of the schema based on the validation constraints defined in ::validateComponentInput(). This only used JSON Schema as an MVP (inspired by JsComponent::getExplicitInputDefinitions()).
   */
  protected function getExplicitInputDefinitions(): array {
    return match($this->getType()) {
      self::SWITCH => [
        'required' => ['variants'],
        'shapes' => [
          'variants' => [
            'type' => 'array',
            'minItems' => 1,
            'items' => ['type' => 'string'],
          ],
        ],
      ],
      self::CASE => [
        'required' => TRUE,
        'variant_id' => ['type' => 'string'],
        'segments' => [
          'type' => 'array',
          'minItems' => 1,
          'items' => ['type' => 'string'],
        ],
      ],
    };
  }

  public function buildComponentInstanceForm(
    array $form,
    FormStateInterface $form_state,
    ?Component $component = NULL,
    string $component_instance_uuid = '',
    array $client_model = [],
    ?EntityInterface $entity = NULL,
    array $settings = [],
  ): array {
    // @todo Uncomment one of the next 2 lines and delete everything else once a React UI exists for this.
    // throw new \RuntimeException('This should not be called because this source implements ComponentSourceWithSwitchCasesInterface.');
    // return [];

    // We won't use a Drupal generated form, but something specific in the
    // client for these components.
    // Temporarily render something just to see what's in the inputs.
    return match ($this->getType()) {
      self::CASE => [
        'type' => [
          '#type' => 'textfield',
          '#title' => $this->t('Personalization Component Type'),
          '#value' => $this->getType(),
          '#disabled' => TRUE,
        ],
        'variant_id' => [
          '#type' => 'textfield',
          '#title' => $this->t('Variant ID'),
          '#value' => \json_encode($client_model['variant_id'], \JSON_PRETTY_PRINT & \JSON_THROW_ON_ERROR),
          '#disabled' => TRUE,
        ],
        'segments' => [
          '#type' => 'textfield',
          '#title' => $this->t('Segments'),
          '#value' => \json_encode($client_model['segments'], \JSON_PRETTY_PRINT & \JSON_THROW_ON_ERROR),
          '#disabled' => TRUE,
        ],
      ],
      self::SWITCH => [
        'type' => [
          '#type' => 'textfield',
          '#title' => $this->t('Personalization Component Type'),
          '#value' => $this->getType(),
          '#disabled' => TRUE,
        ],
        'variants' => [
          '#type' => 'textarea',
          '#title' => $this->t('Variants'),
          '#value' => \json_encode($client_model['variants'], \JSON_PRETTY_PRINT & \JSON_THROW_ON_ERROR),
        ],
      ],
    };
  }

  public function getSlotDefinitions(): array {
    return [
      'content' => [
        'title' => 'Content',
        'description' => match ($this->getType()) {
          'switch' => 'The variants',
          'case' => 'The component tree for this variant',
        },
        'examples' => [
          '',
        ],
      ],
    ];
  }

  public function setSlots(array &$build, array $slots): void {
    // @see ::getSlotDefinitions()
    assert(array_keys($slots) === ['content']);
    $build += $slots;
  }

}
