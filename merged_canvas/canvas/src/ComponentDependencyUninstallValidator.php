<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityDependency;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\Url;
use Drupal\canvas\Audit\ComponentAudit;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;

/**
 * Defines an uninstall validator for content entity uses of Components.
 */
final class ComponentDependencyUninstallValidator implements ModuleUninstallValidatorInterface {

  public function __construct(
    private readonly ComponentAudit $componentAudit,
    private readonly ConfigManagerInterface $configManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  public function validate($module): array {
    $component_definition = $this->entityTypeManager->getDefinition(Component::ENTITY_TYPE_ID);
    \assert($component_definition instanceof ConfigEntityTypeInterface);
    $dependencies = $this->configManager->findConfigEntityDependencies('module', [$module]);
    $components = \array_filter($dependencies, static fn (ConfigEntityDependency $dependency): bool => \str_starts_with($dependency->getConfigDependencyName(), $component_definition->getConfigPrefix()));
    if (\count($components) === 0) {
      return [];
    }
    $components = \array_map(fn (ConfigEntityDependency $dependency) => $this->configManager->loadConfigEntityByName($dependency->getConfigDependencyName()), $components);
    $reasons = [];
    foreach ($components as $component) {
      \assert($component instanceof ComponentInterface);
      $usage = $this->componentAudit->getContentRevisionsUsingComponent($component);
      $count = \count($usage);
      if ($count === 0) {
        continue;
      }
      $reasons[] = new PluralTranslatableMarkup(
        $count,
        'Is required by the %component component, that is in use in the 1 content entity - <a href=":url">View usage</a>',
        'Is required by the %component component, that is in use in the @count content entities - <a href=":url">View usage</a>',
        [
          '%component' => $component->label(),
          ':url' => Url::fromRoute('entity.component.audit', ['component' => $component->id()])->toString(),
        ],
      );
    }
    // @phpstan-ignore-next-line
    return $reasons;
  }

}
