<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Traits;

use Drupal\canvas\Entity\Page;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;

trait PageTrait {

  use ConstraintViolationsTestTrait;

  protected const array PAGE_TEST_MODULES = [
    'user',
    'path',
    'path_alias',
    'field',
    'image',
    'media',
    'views',
    'filter',
    'media_library',
    'canvas_test_page',
  ];

  protected function installPageEntitySchema(): void {
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);
  }

  /**
   * Asserts that the page entity can be saved without violations.
   *
   * @param \Drupal\canvas\Entity\Page $page
   *   The page entity.
   */
  protected static function assertSaveWithoutViolations(Page $page): void {
    // Path field is always invalid for new entities.
    // @see \Drupal\path\Plugin\Field\FieldWidget\PathWidget::validateFormElement().
    $violations = $page->validate()->filterByFields(['path']);
    self::assertCount(
      0,
      $violations,
      var_export(self::violationsToArray($violations), TRUE)
    );
    $page->save();
  }

}
