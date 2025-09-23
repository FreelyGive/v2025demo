<?php

namespace Drupal\canvas\Routing;

use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;

/**
 * Provides upcasting for a preview entity in ContentTemplate layout API routes.
 */
final class ContentTemplatePreviewEntityConverter implements ParamConverterInterface {

  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    // The ContentTemplate {entity} parameter must have already been upcast.
    assert(array_key_exists('entity', $defaults));
    $content_template = $defaults['entity'];
    assert($content_template instanceof ContentTemplate);

    // Use the ContentTemplate's target entity type to load the requested
    // preview entity; load the entity revision that would typically be
    // displayed on its canonical route.
    $preview_entity = $this->entityRepository->getCanonical($content_template->getTargetEntityTypeId(), $value);

    if ($preview_entity === NULL) {
      throw new ParamNotConvertedException(sprintf('The "%s" parameter was not converted because a `%s` content entity with ID %d does not exist.', $name, $content_template->getTargetEntityTypeId(), $value));
    }
    if ($preview_entity->bundle() !== $content_template->getTargetBundle()) {
      throw new ParamNotConvertedException(sprintf('The "%s" parameter was not converted because the `%s` content entity with ID %d is of the bundle `%s`, should be `%s`.', $name, $content_template->getTargetEntityTypeId(), $value, $preview_entity->bundle(), $content_template->getTargetBundle()));
    }
    return $preview_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && $definition['type'] == 'canvas_content_template_preview_entity';
  }

}
