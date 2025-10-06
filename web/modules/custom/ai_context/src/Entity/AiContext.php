<?php

declare(strict_types=1);

namespace Drupal\ai_context\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the AI Context config entity.
 *
 * @ConfigEntityType(
 *   id = "ai_context",
 *   label = @Translation("AI Context"),
 *   label_collection = @Translation("AI Contexts"),
 *   label_singular = @Translation("AI Context"),
 *   label_plural = @Translation("AI Contexts"),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_context\AiContextListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_context\Form\AiContextForm",
 *       "edit" = "Drupal\ai_context\Form\AiContextForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "ai_context",
 *   admin_permission = "administer ai context",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "collection" = "/admin/config/ai/contexts",
 *     "add-form" = "/admin/config/ai/contexts/add",
 *     "edit-form" = "/admin/config/ai/contexts/{ai_context}",
 *     "delete-form" = "/admin/config/ai/contexts/{ai_context}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "content",
 *     "tags"
 *   }
 * )
 */
final class AiContext extends ConfigEntityBase {

  /**
   * The machine ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The description.
   *
   * @var string
   */
  protected string $description = '';

  /**
   * Markdown content.
   *
   * @var string
   */
  protected string $content = '';

  /**
   * Tag term IDs (taxonomy ai_context_tags).
   *
   * @var string[]
   */
  protected array $tags = [];
}

