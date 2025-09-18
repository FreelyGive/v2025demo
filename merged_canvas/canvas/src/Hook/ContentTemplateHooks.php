<?php

declare(strict_types=1);

namespace Drupal\canvas\Hook;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\EntityHandlers\ContentTemplateAwareViewBuilder;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;

/**
 * @see \Drupal\canvas\Entity\ContentTemplate
 * @see \Drupal\canvas\EntityHandlers\ContentTemplateAwareViewBuilder
 */
final class ContentTemplateHooks {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * Implements hook_entity_form_display_alter().
   */
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context): void {
    // @todo Remove this route match check, and instead use
    //   `$context['form_mode']`. This will require refactoring
    //   `\Drupal\canvas\Controller\EntityFormController` to pass in a
    //   dynamically generated `canvas` form mode.
    if (!\str_starts_with((string) $this->routeMatch->getRouteName(), 'canvas.api.')) {
      return;
    }
    $target_entity_type_id = $form_display->getTargetEntityTypeId();
    $entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
    \assert($entity_type instanceof EntityTypeInterface);
    if (\is_subclass_of($entity_type->getClass(), EntityPublishedInterface::class) && ($published_key = $entity_type->getKey('published'))) {
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($target_entity_type_id, $form_display->getTargetBundle());
      // @see \Drupal\canvas\InternalCanvasFieldNameResolver::getCanvasFieldName()
      $canvas_fields = \array_filter($field_definitions, fn(FieldDefinitionInterface $field_definition) => \is_a($field_definition->getItemDefinition()
        ->getClass(), ComponentTreeItem::class, \TRUE));
      if (empty($canvas_fields)) {
        return;
      }
      // Publishable entities are automatically published when publishing auto-saved changes.
      // @see \Drupal\canvas\Controller\ApiAutoSaveController::post()
      $form_display->removeComponent($published_key);
    }
  }

  /**
   * Implements hook_entity_type_alter.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array $definitions): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    foreach ($definitions as $entity_type) {
      // Canvas pages don't have any structured data, and therefore don't support
      // content templates (which require structured data anyway -- that is, they
      // need to be using at least one dynamic prop source).
      // @see docs/adr/0004-page-entity-type.md
      if ($entity_type->id() === Page::ENTITY_TYPE_ID) {
        continue;
      }
      // Canvas can only render fieldable content entities. Any content entity
      // types with structured data (all of them except Canvas' own `Page`)
      // must be assumed to use ContentTemplates, and hence should use that view
      // builder.
      // Note: as soon as a ContentTemplate exists for a certain content entity
      // type + view mode, the original template will NOT be used anymore:
      // - not the view mode-specific one, such as `node--teaser.html.twig`
      // - not the generic one, such as `node.html.twig`
      // @todo Remove the restriction that this only works with nodes, after
      //   https://www.drupal.org/project/canvas/issues/3498525.
      if ($entity_type->entityClassImplements(FieldableEntityInterface::class) && $entity_type->id() === 'node') {
        // @see \Drupal\canvas\EntityHandlers\ContentTemplateAwareViewBuilder::createInstance()
        $entity_type->setHandlerClass(ContentTemplateAwareViewBuilder::DECORATED_HANDLER_KEY, $entity_type->getViewBuilderClass())
          ->setViewBuilderClass(ContentTemplateAwareViewBuilder::class);
      }
    }
  }

}
