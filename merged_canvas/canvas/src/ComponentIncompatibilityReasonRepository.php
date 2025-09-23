<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Defines a class for storage of component incompatibility reasons.
 */
final class ComponentIncompatibilityReasonRepository {

  private readonly KeyValueStoreInterface $keyValue;

  public function __construct(
    #[Autowire('@keyvalue')]
    KeyValueFactoryInterface $keyValueFactory,
  ) {
    $this->keyValue = $keyValueFactory->get('canvas:component:reasons');
  }

  /**
   * @param string $source_plugin_id
   * @param string $identifier
   * @param array<int, string> $reasons
   *
   * @return void
   */
  public function storeReasons(string $source_plugin_id, string $identifier, array $reasons): void {
    $key = $this->generateKey($source_plugin_id, $identifier);
    $this->keyValue->set($key, $reasons);
  }

  public function removeReason(string $source_plugin_id, string $identifier): void {
    $key = $this->generateKey($source_plugin_id, $identifier);
    $this->keyValue->delete($key);
  }

  private function generateKey(string $source_plugin_id, string $identifier): string {
    return \sprintf('%s:%s', $source_plugin_id, $identifier);
  }

  public function getReasons(): array {
    $reasons = $this->keyValue->getAll();
    return \array_reduce(\array_keys($reasons), function (array $carry, string $key) use ($reasons) {
      NestedArray::setValue($carry, \explode(':', $key, 2), $reasons[$key]);
      return $carry;
    }, []);
  }

  public function updateReasons(string $source_plugin_id, array $reasons): void {
    $old_keys = \array_filter(\array_keys($this->keyValue->getAll()), static fn(string $key) => \str_starts_with($key, $source_plugin_id));
    $this->keyValue->deleteMultiple($old_keys);
    $new_entries = \array_reduce(\array_keys($reasons), fn (array $carry, string $key) => [
      ...$carry,
      $this->generateKey($source_plugin_id, $key) => $reasons[$key],
    ], []);
    $this->keyValue->setMultiple($new_entries);
  }

}
