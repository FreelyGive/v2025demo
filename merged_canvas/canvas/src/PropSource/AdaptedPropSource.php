<?php

declare(strict_types=1);

namespace Drupal\canvas\PropSource;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\canvas\Plugin\AdapterManager;
use Drupal\canvas\Plugin\Adapter\AdapterInterface;

/**
 * @phpstan-import-type AdaptedPropSourceArray from PropSource
 * @internal
 */
final class AdaptedPropSource extends PropSourceBase {

  /**
   * @param \Drupal\canvas\Plugin\Adapter\AdapterInterface $adapter_instance
   * @param array<string, mixed> $adapter_inputs
   */
  public function __construct(
    private readonly AdapterInterface $adapter_instance,
    private readonly array $adapter_inputs,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSourceTypePrefix(): string {
    return 'adapter';
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceType(): string {
    return $this->getSourceTypePrefix() . self::SOURCE_TYPE_PREFIX_SEPARATOR . $this->adapter_instance->getPluginId();
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return AdaptedPropSourceArray
   */
  public function toArray(): array {
    return [
      'sourceType' => $this->getSourceType(),
      'adapterInputs' => array_combine(
        array_keys($this->adapter_inputs),
        array_map(
          fn (PropSourceBase $source): array => $source->toArray(),
          array_map(
            fn (string $input_name): PropSourceBase => $this->getInputPropSource($input_name),
            array_keys($this->adapter_inputs)
          )
        ),
      ),
    ];
  }

  /**
   * @param AdaptedPropSourceArray $sdc_prop_source
   */
  public static function parse(array $sdc_prop_source): static {
    $adapter_manager = \Drupal::service(AdapterManager::class);
    assert($adapter_manager instanceof AdapterManager);
    $adapter_instance = $adapter_manager->createInstance(explode(self::SOURCE_TYPE_PREFIX_SEPARATOR, $sdc_prop_source['sourceType'])[1]);
    assert($adapter_instance instanceof AdapterInterface);

    // `sourceType = adapter:*` requires adapterInputs to be specified.
    $missing = array_diff(['adapterInputs'], array_keys($sdc_prop_source));
    if (!empty($missing)) {
      throw new \LogicException(sprintf('Missing the keys %s.', implode(',', $missing)));
    }
    assert(array_key_exists('adapterInputs', $sdc_prop_source));

    return new AdaptedPropSource($adapter_instance, $sdc_prop_source['adapterInputs']);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(?FieldableEntityInterface $host_entity, bool $is_required): mixed {
    foreach ($this->adapter_inputs as $input_name => $input) {
      $value_object = $this->getInputPropSource($input_name);
      $value = $value_object->evaluate($host_entity, $is_required);
      $this->adapter_instance->addInput($input_name, $value);
    }

    return $this->adapter_instance->adapt();
  }

  public function asChoice(): string {
    return $this->adapter_instance->getPluginId();
  }

  public function getInputPropSource(string $input_name) : PropSourceBase {
    return PropSource::parse($this->adapter_inputs[$input_name]);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(FieldableEntityInterface|FieldItemListInterface|null $host_entity = NULL): array {
    $dependencies = [];
    $plugin_definition = $this->adapter_instance->getPluginDefinition();
    $dependencies['module'][] = match (TRUE) {
      $plugin_definition instanceof PluginDefinitionInterface => $plugin_definition->getProvider(),
      is_array($plugin_definition) => $plugin_definition['provider'],
      default => NULL,
    };
    foreach ($this->adapter_inputs as $input_name => $input) {
      $dependencies = NestedArray::mergeDeep($dependencies, $this->getInputPropSource($input_name)->calculateDependencies($host_entity));
    }
    return $dependencies;
  }

}
