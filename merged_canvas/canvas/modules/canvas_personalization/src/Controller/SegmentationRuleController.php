<?php

declare(strict_types=1);


namespace Drupal\canvas_personalization\Controller;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\canvas_personalization\Entity\Segment;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⚠️ This is highly experimental and *will* be refactored or even removed.
 *
 * @todo Revisit in https://www.drupal.org/i/3527086
 */
final class SegmentationRuleController {

  public function delete(Segment $segment, string $rule): Response {
    $segment->removeSegmentRule($rule);
    $segment->save();
    return new TrustedRedirectResponse($segment->toUrl('edit-form')->toString());
  }

}
