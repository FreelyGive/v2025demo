<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper\EventSubscriber;

use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DefaultContent\ExportMetadata;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 *
 * @todo Remove after https://www.drupal.org/project/canvas/issues/3534561.
 */
final class DefaultContentSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreExportEvent::class => 'preExport',
    ];
  }

  public function preExport(PreExportEvent $event): void {
    $event->setCallback(
      'field_item:' . ComponentTreeItem::PLUGIN_ID,
      function (ComponentTreeItem $item, ExportMetadata $metadata): array {
        // Find all entities referenced in the component tree and add them as
        // dependencies of the entity being exported.
        $dependencies = $item->calculateFieldItemValueDependencies($item->getEntity());
        foreach ($dependencies['content'] ?? [] as $dependency) {
          // @see \Drupal\Core\Entity\EntityBase::getConfigDependencyName()
          [$entity_type_id,, $uuid] = explode(':', $dependency);
          $dependency = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);
          if ($dependency instanceof ContentEntityInterface) {
            $metadata->addDependency($dependency);
          }
        }
        // Don't export any empty properties; they're not valid for import.
        $values = array_filter($item->getValue());

        // The component version is not necessary in exported content.
        unset($values['component_version']);

        // Export the inputs as an array, since that's much easier to read and
        // edit manually if needed.
        if (isset($values['inputs']) && is_string($values['inputs'])) {
          $values['inputs'] = Json::decode($values['inputs']);
        }
        return $values;
      },
    );
  }

}
