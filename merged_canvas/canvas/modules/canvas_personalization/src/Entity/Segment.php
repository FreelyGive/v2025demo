<?php

declare(strict_types=1);

namespace Drupal\canvas_personalization\Entity;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Plugin\FilteredPluginManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\ClientSideRepresentation;
use Drupal\canvas_personalization\Form\SegmentForm;
use Drupal\canvas_personalization\Form\SegmentRuleForm;
use Drupal\canvas_personalization\Access\SegmentAccessControlHandler;
use Drupal\canvas_personalization\SegmentListBuilder;

#[ConfigEntityType(
  id: self::ENTITY_TYPE_ID,
  label: new TranslatableMarkup("Personalization Segment"),
  label_collection: new TranslatableMarkup("Personalization Segments"),
  label_singular: new TranslatableMarkup("personalization segment"),
  label_plural: new TranslatableMarkup("personalization segments"),
  entity_keys: [
    "id" => "id",
    "label" => "label",
    "status" => "status",
    "weight" => "weight",
  ],
  handlers: [
    "access" => SegmentAccessControlHandler::class,
    "list_builder" => SegmentListBuilder::class,
    "form" => [
      "add" => SegmentForm::class,
      "edit" => SegmentForm::class,
      "delete" => EntityDeleteForm::class,
      "add_rule_form" => SegmentRuleForm::class,
    ],
  ],
  links: [
    "collection" => "/admin/structure/segment",
    "add-form" => "/admin/structure/segment/add",
    "edit-form" => "/admin/structure/segment/{segment}",
    "delete-form" => "/admin/structure/segment/{segment}/delete",
  ],
  admin_permission: self::ADMIN_PERMISSION,
  label_count: [
    "singular" => "@count personalization segment",
    "plural" => "@count personalization segments",
  ],
  config_export: [
    "id",
    "label",
    "description",
    "rules",
    "weight",
  ],
)]
final class Segment extends ConfigEntityBase implements SegmentInterface {

  use StringTranslationTrait;

  public const string ENTITY_TYPE_ID = 'segment';
  public const string ADMIN_PERMISSION = 'administer personalization segments';
  public const string DEFAULT_ID = 'default';

  /**
   * The segment ID.
   */
  protected string $id;

  /**
   * The human-readable label of the segment.
   */
  protected ?string $label;

  /**
   * The human-readable description of the segment.
   */
  protected ?string $description;

  /**
   * The segmentation rules.
   */
  protected ?array $rules;

  /**
   * The segment weight.
   */
  protected int $weight = 0;

  /**
   * The segmentation rules lazy collection of plugin instances.
   */
  protected ?ConditionPluginCollection $segmentRulesPluginCollection;

  /**
   * The condition plugin manager for instantiating the segmentation rules.
   */
  protected (ExecutableManagerInterface&FilteredPluginManagerInterface)|NULL $conditionPluginManager;

  /**
   * {@inheritdoc}
   */
  public function addSegmentRule(string $plugin_id, array $settings): self {
    $condition_definitions = $this->conditionPluginManager()->getFilteredDefinitions('canvas_personalization');
    if (!isset($condition_definitions[$plugin_id])) {
      $valid_ids = implode(', ', array_keys($condition_definitions));
      throw new PluginNotFoundException($plugin_id, sprintf('The "%s" plugin does not exist. Valid plugin IDs for adding as segment rules are: %s', $plugin_id, $valid_ids));
    }
    $this->getSegmentRulesPluginCollection()->addInstanceId($plugin_id, $settings);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeSegmentRule(string $plugin_id): self {
    $this->getSegmentRulesPluginCollection()->removeInstanceId($plugin_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSegmentRules(): array {
    return $this->rules ?? [];
  }

  public function getPluginCollections() {
    return [
      'rules' => $this->getSegmentRulesPluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSegmentRulesPluginCollection(): ConditionPluginCollection {
    if (!isset($this->segmentRulesPluginCollection)) {
      $this->segmentRulesPluginCollection = new ConditionPluginCollection($this->conditionPluginManager(), $this->getSegmentRules());
    }
    return $this->segmentRulesPluginCollection;
  }

  /**
   * Gets the condition plugin manager.
   */
  protected function conditionPluginManager(): ExecutableManagerInterface&FilteredPluginManagerInterface {
    if (!isset($this->conditionPluginManager)) {
      $this->conditionPluginManager = \Drupal::service('plugin.manager.condition');
    }
    return $this->conditionPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function summary(): array {
    $summary = [];
    foreach ($this->getSegmentRulesPluginCollection() as $segment_rule) {
      $summary[] = $segment_rule->summary();
    }
    if (empty($summary)) {
      $summary[] = $this->t('No personalization segment rules added yet');
    }
    return $summary;
  }

  public function normalizeForClientSide(): ClientSideRepresentation {
    return ClientSideRepresentation::create(
      values: [
        'id' => $this->id,
        'label' => $this->label,
        'description' => $this->description,
        'rules' => $this->rules,
        'weight' => $this->weight,
        'status' => $this->status(),
      ],
      preview: NULL,
    );
  }

  public static function createFromClientSide(array $data): static {
    $entity = static::create(['id' => $data['id']]);
    if (!isset($data['rules'])) {
      $data['rules'] = [];
    }
    $entity->updateFromClientSide($data);
    $entity->disable();
    return $entity;
  }

  public function updateFromClientSide(array $data): void {
    foreach ($data as $key => $value) {
      $this->set($key, $value);
    }
  }

  public static function refineListQuery(QueryInterface &$query, RefinableCacheableDependencyInterface $cacheability): void {
    // Nothing to do.
  }

}
