<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Decorator to avoid displaying messages on Canvas API routes.
 *
 * No messages should ever be visible in the previews rendered by Canvas API routes.
 *
 * (The only messages relevant in the Canvas UI are validation errors, and those are
 * displayed when reviewing/publishing all auto-saved changes.)
 */
readonly class Messenger implements MessengerInterface {

  public function __construct(
    private MessengerInterface $messenger,
    private RouteMatchInterface $currentRouteMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addMessage($message, $type = MessengerInterface::TYPE_STATUS, $repeat = FALSE): MessengerInterface {
    $routeName = $this->currentRouteMatch->getRouteName();
    if (!is_string($routeName) || !str_starts_with($routeName, 'canvas.api.')) {
      $this->messenger->addMessage($message, $type, $repeat);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addStatus($message, $repeat = FALSE): MessengerInterface {
    return $this->addMessage($message, static::TYPE_STATUS, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addError($message, $repeat = FALSE): MessengerInterface {
    return $this->addMessage($message, static::TYPE_ERROR, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function addWarning($message, $repeat = FALSE): MessengerInterface {
    return $this->addMessage($message, static::TYPE_WARNING, $repeat);
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return $this->messenger->all();
  }

  /**
   * {@inheritdoc}
   */
  public function messagesByType($type): array {
    return $this->messenger->messagesByType($type);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): array {
    return $this->messenger->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByType($type): array {
    return $this->messenger->deleteByType($type);
  }

}
