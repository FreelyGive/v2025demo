<?php

declare(strict_types=1);

namespace Drupal\ai_context\EventSubscriber;

use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

final class SystemPromptSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly LoggerChannelInterface $logger) {}

  public static function getSubscribedEvents(): array {
    return [
      BuildSystemPromptEvent::EVENT_NAME => 'onPreSystemPrompt',
    ];
  }

  public function onPreSystemPrompt(BuildSystemPromptEvent $event): void {
    $tokens = $event->getTokens();
    if (!isset($tokens['ai_context']['render']) || empty($tokens['ai_context']['render'])) {
      return;
    }
    $append = "\n\nThe following site-specific context applies to this task. Use it strictly when relevant; do not override user intent.\n";
    $append .= "-----------------------------------------------\n";
    $append .= (string) $tokens['ai_context']['render'];
    $append .= "-----------------------------------------------\n";
    $event->setSystemPrompt($event->getSystemPrompt() . $append);
  }
}
