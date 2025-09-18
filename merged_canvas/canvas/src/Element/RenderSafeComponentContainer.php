<?php

declare(strict_types=1);

namespace Drupal\canvas\Element;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Attribute\RenderElement;
use Drupal\Core\Render\Element\RenderElementBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a render element that provides safety against an exception.
 */
#[RenderElement(self::PLUGIN_ID)]
final class RenderSafeComponentContainer extends RenderElementBase implements ContainerFactoryPluginInterface {

  public const PLUGIN_ID = 'component_container';

  /**
   * Constructs a new RenderSafeComponentContainer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#pre_render' => [
        [$this, 'renderComponent'],
      ],
      '#component_context' => '',
      '#component' => [],
      '#is_preview' => FALSE,
      '#component_uuid' => '',
    ];
  }

  public function renderComponent(array $element): array {
    $context = new RenderContext();
    $element['#children'] = $this->renderer->executeInRenderContext($context, function () use (&$element, $context) {
      try {
        return $this->renderer->render($element['#component']);
      }
      catch (\Throwable $e) {
        // In this scenario because rendering fails the context isn't updated or
        // bubbled.
        $context->update($element);
        $context->bubble();
        $fallback = self::handleComponentException(
          $e,
          $element['#component_context'] ?? '',
          $element['#is_preview'] ?? FALSE,
          $element['#component_uuid'] ?? '',
        );
        return $this->renderer->render($fallback);
      }
    });
    unset($element['#component']);
    unset($element['#pre_render']);
    if (!$context->isEmpty()) {
      $context->pop()->applyTo($element);
    }
    return $element;
  }

  public static function handleComponentException(\Throwable $e, string $componentContext, bool $isPreview, string $componentUuid): array {
    \Drupal::logger('canvas')->error(\sprintf('%s occurred during rendering of component %s in %s: %s', $e::class, $componentUuid, $componentContext, $e->getMessage()));
    if ($isPreview) {
      return [
        '#type' => 'container',
        '#attributes' => [
          'data-component-uuid' => $componentUuid,
        ],
        '#markup' => new TranslatableMarkup('Component failed to render, check logs for more detail.'),
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-component-uuid' => $componentUuid],
      '#markup' => new TranslatableMarkup('Oops, something went wrong! Site admins have been notified.'),
    ];
  }

}
