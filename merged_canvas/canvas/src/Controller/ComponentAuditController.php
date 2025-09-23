<?php

declare(strict_types=1);

namespace Drupal\canvas\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Entity\ContentTemplate;
use Drupal\canvas\Entity\PageRegion;
use Drupal\canvas\Entity\Pattern;

/**
 * Controller for components audit page.
 */
final class ComponentAuditController {

  use StringTranslationTrait;

  public function __construct(
    private readonly ComponentAudit $componentAudit,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  public function auditTitle(Component $component): \Stringable {
    return $this->t('Audit of %component usages', ['%component' => $component->label()]);
  }

  public function audit(Component $component): array {
    $versions = $component->getVersions();

    $build = [
      'overview' => [
        '#markup' => '<p>' . $this->t('There are %count versions of this component. The usages for each version are listed below:', ['%count' => count($versions)]) . '</p>',
      ],
    ];
    // @todo Field config default values
    // @todo Base field definition default values
    // @todo What if there are asymmetric content translations, or the translated
    //   config provide different defaults? Verify and test in
    //   https://www.drupal.org/i/3522198
    foreach ($versions as $version) {
      $build[$version] = [
        'separator' => ['#markup' => '<hr>'],
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => [
            'id' => $version,
          ],
          '#value' => $version === ComponentInterface::FALLBACK_VERSION
            // phpcs:ignore Drupal.Semantics.FunctionT.ConcatString
            ? '🚨' . $this->t('Fallback version active') . '🚨'
            : "<code>$version</code>",
        ],
        'results' => $version === ComponentInterface::FALLBACK_VERSION
          ? [
            '#markup' => '<em>' . $this->t('All of the versions below are using the fallback rendering now — restore this component to make the instances listed below work again.') . '</em>',
          ]
          : $this->auditVersion($component->loadVersion($version)),
      ];
    }
    return $build;
  }

  public function auditVersion(Component $component): array {
    return [
      'content' => $this->getContentAudit($component),
      'content templates' => $this->getContentTemplatesAudit($component),
      'regions' => $this->getRegionsAudit($component),
      'patterns' => $this->getPatternsAudit($component),
    ];
  }

  public function getContentAudit(Component $component): array {
    $rows = [];
    $header = [
      'title' => $this->t('Title'),
      'entity_type_id' => [
        'data' => $this->t('Entity Type'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'bundle' => [
        'data' => $this->t('Bundle'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'id' => [
        'data' => $this->t('ID'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'revision_id' => [
        'data' => $this->t('Revision ID'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'in_latest' => [
        'data' => $this->t('Appears in latest revision?'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'in_default' => [
        'data' => $this->t('Appears in default revision?'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
    ];
    $dependents = $this->componentAudit->getContentRevisionsUsingComponent($component, [$component->getLoadedVersion()]);
    foreach ($dependents as $entity) {
      $row = [];
      $entity_type_id = $entity->getEntityTypeId();
      $key = "$entity_type_id:{$entity->id()}";
      if (isset($rows["$entity_type_id.{$entity->id()}"])) {
        if ($entity->isLatestRevision()) {
          $rows[$key]['in_latest']['data'] = '✔';
        }
        if ($entity->isDefaultRevision()) {
          $rows[$key]['in_default']['data'] = '✔';
          $rows[$key]['title']['data'] = $entity->toLink();
        }
      }
      else {
        $bundle_label = $this->bundleInfo->getBundleInfo($entity_type_id)[$entity->bundle()]['label'];
        $row['title']['data'] = $entity->toLink();
        $row['entity_type_id']['data'] = $entity->getEntityType()->getLabel();
        $row['bundle']['data'] = $bundle_label;
        $row['id']['data'] = $entity->id();
        $row['revision_id']['data'] = $entity->getRevisionId();
        $row['in_latest']['data'] = $entity->isLatestRevision() ? '✔' : '❌';
        $row['in_default']['data'] = $entity->isDefaultRevision() ? '✔' : '❌';
        $rows[$key] = $row;
      }
    }

    return [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => ['name' => 'content'],
        '#value' => $this->t('Content usages'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No component usage detected.'),
        '#attributes' => ['name' => 'table-content'],
      ],
    ];
  }

  protected function getContentTemplatesAudit(Component $component): array {
    $headers = [
      'title' => $this->t('Title'),
      'entity_type_id' => [
        'data' => $this->t('Entity Type'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'bundle' => [
        'data' => $this->t('Bundle'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'id' => [
        'data' => $this->t('ID'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],

    ];
    return $this->createConfigTable(
      $component,
      ContentTemplate::ENTITY_TYPE_ID,
      $this->t('Content templates usages'),
      $this->t('No content templates usage detected.'),
      $headers,
      function (ContentTemplate $content_template): array {
        $entity_type_id = $content_template->getTargetEntityTypeId();
        $bundle = $content_template->getTargetBundle();
        $bundle_label = $this->bundleInfo->getBundleInfo($entity_type_id)[$bundle]['label'];
        $view_mode = EntityViewMode::load("$entity_type_id.{$content_template->getMode()}");
        assert($view_mode instanceof EntityViewMode);
        $row = [];
        $row['title']['data'] = $content_template->label();
        $row['entity_type_id']['data'] = $this->entityTypeManager->getDefinition($entity_type_id)->getLabel();
        $row['bundle']['data'] = $bundle_label;
        $row['mode']['data'] = $view_mode->label();
        return $row;
      });
  }

  protected function getRegionsAudit(Component $component): array {
    $headers = [
      'title' => $this->t('Title'),
    ];
    return $this->createConfigTable(
      $component,
      PageRegion::ENTITY_TYPE_ID,
      $this->t('Region usages'),
      $this->t('No regions usage detected.'),
      $headers,
      function (PageRegion $region): array {
        $row = [];
        $row['title']['data'] = $region->label();
        return $row;
      });
  }

  protected function getPatternsAudit(Component $component): array {
    $headers = [
      'title' => $this->t('Title'),
    ];
    return $this->createConfigTable(
      $component,
      Pattern::ENTITY_TYPE_ID,
      $this->t('Pattern usages'),
      $this->t('No patterns usage detected.'),
      $headers,
      function (Pattern $pattern): array {
        $row = [];
        $row['title']['data'] = $pattern->label();
        return $row;
      });
  }

  public function createConfigTable(Component $component, string $config_entity_type_id, \Stringable $sectionTitle, \Stringable $emptyMessage, array $headers, callable $rowCallback): array {
    $rows = [];
    $dependents = $this->componentAudit->getConfigEntityDependenciesUsingComponent($component, $config_entity_type_id);
    /** @var \Drupal\canvas\Entity\PageRegion $region */
    foreach ($dependents as $region) {
      $row = $rowCallback($region);
      $rows[] = $row;
    }
    $class = Html::getClass($config_entity_type_id);
    return [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => ['name' => $class],
        '#value' => $sectionTitle,
      ],
      'table' => [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#empty' => $emptyMessage,
        '#attributes' => ['name' => 'table-' . $class],
      ],
    ];
  }

}
