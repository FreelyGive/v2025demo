<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\media\Entity\MediaType;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\views\EntityViewsData;
use Drupal\Core\Entity\Form\RevisionDeleteForm;
use Drupal\Core\Entity\Form\RevisionRevertForm;
use Drupal\canvas\Entity\Routing\CanvasHtmlRouteProvider;
use Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider;

/**
 * Defines the page entity class.
 *
 * @todo change add-form and edit-form links to use `page` instead of `canvas_page`.
 *    This requires updating the UI to use the values from `drupalSettings.canvas`
 *    without them matching the URL path. If they don't routing in the UI is
 *    broken and the UI never renders. See `empty-canvas.cy.js`.
 *    Fix after https://www.drupal.org/project/canvas/issues/3489775
 */
#[ContentEntityType(
    id: self::ENTITY_TYPE_ID,
    label: new TranslatableMarkup("Page"),
    label_collection: new TranslatableMarkup("Pages"),
    label_singular: new TranslatableMarkup("page"),
    label_plural: new TranslatableMarkup("pages"),
    label_count: ["@count page", "@count pages"],
    handlers: [
      "storage" => SqlContentEntityStorage::class,
      "access" => PageAccessControlHandler::class,
      "view_builder" => PageViewBuilder::class,
      "views_data" => EntityViewsData::class,
      "form" => [
        "default" => CanvasPageForm::class,
        "delete" => ContentEntityDeleteForm::class,
        "revision-delete" => RevisionDeleteForm::class,
        "revision-revert" => RevisionRevertForm::class,
      ],
      "route_provider" => [
        "html" => CanvasHtmlRouteProvider::class,
        "revision" => RevisionHtmlRouteProvider::class,
      ],
    ],
    base_table: "canvas_page",
    revision_table: "canvas_page_revision",
    data_table: "canvas_page_field_data",
    revision_data_table: "canvas_page_field_revision",
    show_revision_ui: TRUE,
    links: [
      "canonical" => "/page/{canvas_page}",
      "delete-form" => "/page/{canvas_page}/delete",
      "edit-form" => "/canvas/editor/canvas_page/{canvas_page}",
      "revision-delete-form" => "/page/{canvas_page}/revisions/{canvas_page_revision}/delete",
      "revision-revert-form" => "/page/{canvas_page}/revisions/{canvas_page_revision}/revert",
      "version-history" => "/page/{canvas_page}/revisions",
    ],
    translatable: TRUE,
    entity_keys: [
      "id" => "id",
      "uuid" => "uuid",
      "revision" => "revision_id",
      "label" => "title",
      "langcode" => "langcode",
      "published" => "status",
      "owner" => "owner",
    ],
    revision_metadata_keys: [
      "revision_user" => "revision_user",
      "revision_created" => "revision_created",
      "revision_log_message" => "revision_log",
    ],
  )
]
final class Page extends EditorialContentEntityBase implements EntityOwnerInterface, ComponentTreeEntityInterface {

  use EntityOwnerTrait;
  public const string ENTITY_TYPE_ID = 'canvas_page';
  public const string CREATE_PERMISSION = 'create canvas_page';
  public const string EDIT_PERMISSION = 'edit canvas_page';
  public const string DELETE_PERMISSION = 'delete canvas_page';

  /**
   * {@inheritdoc}
   */
  public function getComponentTree(): ComponentTreeItemList {
    $item = $this->get('components');
    assert($item instanceof ComponentTreeItemList);
    return $item;
  }

  public function setComponentTree(array $values): self {
    $this->getComponentTree()->setValue($values);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += self::ownerBaseFieldDefinitions($entity_type);
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE);
    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Meta description'))
      ->setDescription(t('The meta description of the page.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE);
    $fields['components'] = BaseFieldDefinition::create('component_tree')
      ->setLabel(t('Components'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'component_tree',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'canvas_naive_render_sdc_tree',
      ]);
    // @see path_entity_base_field_info().
    $fields['path'] = BaseFieldDefinition::create('path')
      ->setLabel(t('URL alias'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'path',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setComputed(TRUE);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time the page was created.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValueCallback(self::class . '::getRequestTime');
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the page was last edited.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);
    $fields['image'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Image'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => self::getImageMediaTypes(),
      ])
      ->setDisplayOptions('form', [
        'type' => 'media_library_widget',
        'settings' => [
          // Leave empty so that the allowed media types are delegated to the
          // `handler_settings.target_bundles` setting.
          'media_types' => [],
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);
    return $fields;
  }

  /**
   * Gets the request time.
   */
  public static function getRequestTime(): int {
    return \Drupal::time()->getRequestTime();
  }

  /**
   * Gets the media type IDs that use the `image` field type.
   *
   * @return array
   *   The media type IDs that use the `image` field type.
   */
  private static function getImageMediaTypes(): array {
    $media_types = MediaType::loadMultiple();
    $target_bundles = [];
    foreach ($media_types as $media_type) {
      /** @var array{allowed_field_types: list<string>} $media_source_plugin_definition */
      $media_source_plugin_definition = $media_type->getSource()->getPluginDefinition();
      if (in_array('image', $media_source_plugin_definition['allowed_field_types'], TRUE)) {
        $target_bundles[] = $media_type->id();
      }
    }
    return $target_bundles;
  }

}
