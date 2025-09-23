<?php

namespace Drupal\canvas\Controller;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\ComponentIncompatibilityReasonRepository;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Plugin\ComponentPluginManager;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Not every unavailable/disabled SDC will have Component entity, so we're using a controller instead of EntityListBuilder for this.
 *
 * @see \Drupal\canvas\Plugin\ComponentPluginManager::setCachedDefinitions()
 *
 * @todo Ensure reasons are translated.
 */
final class ComponentStatusController {

  use StringTranslationTrait;

  /**
   * @param \Drupal\canvas\Plugin\ComponentPluginManager $componentPluginManager
   */
  public function __construct(
    private readonly ComponentPluginManager $componentPluginManager,
    private readonly ComponentIncompatibilityReasonRepository $reasonRepository,
    private readonly MessengerInterface $messenger,
  ) {}

  public function __invoke(): array {
    // @todo State API is not guaranteed to stay in sync with SDC discovery cache and we should revisit this and choose more reliable, but still performant storage.
    // @see https://www.drupal.org/node/3177901
    $this->componentPluginManager->clearCachedDefinitions();
    $this->componentPluginManager->getDefinitions();

    $reasons = $this->reasonRepository->getReasons();
    $rows = [];
    $header = [
      'id' => $this->t('Component'),
      'status' => $this->t('Status'),
      'reason' => $this->t('Reason'),
    ];
    foreach ($reasons as $source_reasons) {
      foreach ($source_reasons as $component_id => $component_reasons) {
        $component_entity = Component::load($component_id);
        $status = $component_entity instanceof Component && !$component_entity->status() ? $this->t('Disabled') : $this->t('Incompatible');
        $items = [];
        $component_reasons = is_string($component_reasons) ? [$component_reasons] : $component_reasons;
        foreach ($component_reasons as $item) {
          $items[] = Markup::create($item);
        }
        $row = [];
        $row['id']['data'] = $component_id;
        $row['status']['data'] = $status;
        $row['reason']['data'] = [
          '#theme' => 'item_list',
          '#items' => $items,
        ];
        $rows[] = $row;
      }
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No incompatible components detected.'),
    ];
  }

  /**
   * Calls a method on a component and reloads the listing page.
   *
   * @param \Drupal\canvas\Entity\Component $component
   *   The component being acted upon.
   * @param string $op
   *   The operation to perform, e.g., 'enable' or 'disable'.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect back to the listing page.
   */
  public function performOperation(Component $component, string $op) {
    assert(in_array($op, ['enable', 'disable']));

    $component_id = $component->id();
    $source = $component->getComponentSource();
    $source_plugin_id = $source->getPluginId();
    if ($op === 'disable') {
      $component->disable()->save();
      $this->reasonRepository->storeReasons($source_plugin_id, $component_id, ['Manually disabled']);
    }
    elseif ($op === 'enable') {
      try {
        $source->checkRequirements();
        $component->enable()->save();
        $this->reasonRepository->removeReason($source_plugin_id, $component_id);
      }
      catch (ComponentDoesNotMeetRequirementsException $e) {
        $this->messenger->addError($this->t('The component %component does not meet requirements: %reason', [
          "%component" => $component_id,
          "%reason" => $e->getMessage(),
        ]));
        $this->reasonRepository->storeReasons($source_plugin_id, $component_id, $e->getMessages());
        return new RedirectResponse(Url::fromRoute('entity.component.collection')->toString());
      }
    }

    $this->messenger->addStatus($this->t('The component %component has been updated', [
      "%component" => $component_id,
    ]));
    return new RedirectResponse(Url::fromRoute('entity.component.collection')->toString());
  }

}
