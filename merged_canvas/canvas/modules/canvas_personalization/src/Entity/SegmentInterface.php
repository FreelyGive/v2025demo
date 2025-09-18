<?php

declare(strict_types=1);

namespace Drupal\canvas_personalization\Entity;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\canvas\Entity\CanvasHttpApiEligibleConfigEntityInterface;

/**
 * Provides an interface defining a personalization segment entity type.
 *
 * We can't really shape arrays, as we don't know the plugins themselves.
 *
 * @phpstan-type ConditionPluginSettings array{id: string, negate: bool, context_mapping?: array<string, string> }
 */
interface SegmentInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface, CanvasHttpApiEligibleConfigEntityInterface {

  public function addSegmentRule(string $plugin_id, array $settings): self;

  public function removeSegmentRule(string $plugin_id): self;

  public function getSegmentRules(): array;

  public function getSegmentRulesPluginCollection(): ConditionPluginCollection;

  /**
   * @return array<string|\Stringable>
   */
  public function summary(): array;

}
