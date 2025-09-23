<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Render\Element;

final class PageViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode): void {
    if (!$display->isNew()) {
      throw new \InvalidArgumentException('Pages do not have configurable view displays. The view display is computed from base field definitions, to ensure there is never a need for an update path.');
    }

    foreach (Element::children($build) as $key) {
      if ($key !== 'components') {
        unset($build[$key]);
      }
    }
  }

}
