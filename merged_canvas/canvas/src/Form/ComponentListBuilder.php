<?php

declare(strict_types=1);

namespace Drupal\canvas\Form;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\Entity\Component;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @todo Figure out if UX of "create page builder component to see what components are available" is good enough. We might want to add "tree view" of all the components, with ability to quickly "add page builder component" definition for any of them.
 */
final class ComponentListBuilder extends ConfigEntityListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly ComponentAudit $audit,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get(EntityTypeManagerInterface::class)->getStorage($entity_type->id()),
      $container->get(ComponentAudit::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['label'] = $this->t('Label');
    $header['component_source'] = $this->t('Component type');
    $header['component_label'] = $this->t('Component name');
    $header['component_version'] = $this->t('Versions');
    $header['component_usage'] = $this->t('Usage<br>(all&nbsp;versions)');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof Component);

    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $source = $entity->getComponentSource();
    $row['component_source'] = $source->getPluginDefinition()['label'];
    $row['component_label'] = $source->getComponentDescription();
    $row['component_version']['data'] = [
      '#prefix' => sprintf('<code title="%s">', $entity->getActiveVersion()),
      '#plain_text' => count($entity->getVersions()),
      '#suffix' => '</code>',
    ];
    $usage = $this->audit->getConfigEntityUsageCount($entity) +
      // @todo Stop triggering a DB query per row, instead perform a single DB query in ::load() in https://www.drupal.org/i/3522953
      count($this->audit->getContentRevisionsUsingComponent($entity));
    $row['component_usage'] = $usage === 0 ? '' : $usage;

    return $row + parent::buildRow($entity);
  }

  public function getOperations(EntityInterface $entity): array {
    return [
      'audit' => [
        'title' => $this->t('Audit'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.component.audit', ['component' => $entity->id()]),
      ],
    ] + parent::getOperations($entity);
  }

}
