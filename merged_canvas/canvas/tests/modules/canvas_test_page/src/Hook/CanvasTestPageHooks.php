<?php

declare(strict_types=1);

namespace Drupal\canvas_test_page\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\canvas\Entity\Page;

class CanvasTestPageHooks {

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() === Page::ENTITY_TYPE_ID) {
      $fields = [];
      $fields['canvas_test_field'] = BaseFieldDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Test field'))
        ->setDescription(new TranslatableMarkup('A test field'))
        ->setDisplayOptions('view', [
          'label' => 'above',
          'type' => 'string',
          'weight' => 0,
        ]);
      return $fields;
    }
    return [];
  }

  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$base_field_definitions, EntityTypeInterface $entity_type): void {
    // @phpstan-ignore-next-line
    $default_value = \Drupal::state()->get('canvas_test_page.components_default_value', []);
    if ($entity_type->id() === Page::ENTITY_TYPE_ID && !empty($default_value)) {
      /** @var \Drupal\Core\Field\BaseFieldDefinition[] $base_field_definitions */
      $base_field_definitions['components']->setDefaultValue($default_value);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('canvas_page_view')]
  public function canvasPageView(array &$build): void {
    $build['#attached']['drupalSettings']['canvas_test_page'] = ['foo' => 'Bar'];
    $build['#attached']['library'][] = 'core/drupalSettings';
    $build['canvas_test_page_markup'] = ['#markup' => '<div id="canvas-test-page-markup">canvas_test_page_canvas_page_view markup</div>'];
  }

  #[Hook('field_widget_info_alter')]
  public function widgetInfoAlter(array &$info): void {
    // @see \Drupal\Tests\canvas\Kernel\LibraryInfoAlterTest::testTransformMounting()
    $info['non_existent_widget']['canvas'] = [
      'transforms' => [
        'diaclone' => [],
      ],
    ];
  }

}
