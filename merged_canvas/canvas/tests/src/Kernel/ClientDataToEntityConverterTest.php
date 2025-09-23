<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Component\Datetime\Time;
use Drupal\content_moderation\Permissions;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Controller\ApiLayoutController;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\EntityConstraintViolationList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\canvas\ClientDataToEntityConverter;
use Drupal\canvas\Controller\EntityFormTrait;
use Drupal\canvas\Entity\VersionedConfigEntityInterface;
use Drupal\canvas\Exception\ConstraintViolationException;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem;
use Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList;
use Drupal\canvas\Render\PreviewEnvelope;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\Kernel\Traits\VfsPublicStreamUrlTrait;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;
use Drupal\Tests\canvas\Traits\ConstraintViolationsTestTrait;
use Drupal\Tests\canvas\Traits\CanvasFieldTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Drupal\canvas_test_article_fields\Hook\CanvasTestArticleFieldsHooks;
use GuzzleHttp\Psr7\Query;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group canvas
 * @group #slow
 */
class ClientDataToEntityConverterTest extends KernelTestBase {

  use CanvasFieldTrait {
    getValidClientJson as traitGetValidClientJson;
  }
  use ConstraintViolationsTestTrait;
  use UserCreationTrait;
  use EntityFormTrait;
  use ContentModerationTestTrait;
  use RequestTrait;
  use VfsPublicStreamUrlTrait;

  private User $otherUser;

  public function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['system']);
    (new CanvasTestSetup())->setup();
    $this->setUpImages();
    $other_user = $this->createUser();
    assert($other_user instanceof User);
    $this->otherUser = $other_user;
  }

  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $definition = $container->getDefinition('plugin.manager.field.widget');
    $definition->setClass(TestWidgetManager::class);
    $container->setDefinition('plugin.manager.field.widget', $definition);
    $container->getDefinition('datetime.time')
      ->setClass(TestTime::class);
  }

  /**
   * {@inheritdoc}
   */
  private function getValidClientJson(bool $dynamic_image = TRUE): array {
    $json = $this->traitGetValidClientJson(NULL, $dynamic_image);
    $content_region = \array_values(\array_filter($json['layout'], static fn(array $region) => $region['id'] === 'content'));
    return [
      'layout' => reset($content_region),
      'model' => $json['model'],
      'entity_form_fields' => $json['entity_form_fields'],
    ];
  }

  /**
   * @testWith [false]
   *           [true]
   */
  public function testConvert(bool $with_content_moderation = FALSE): void {
    if ($with_content_moderation) {
      $this->container->get(ModuleInstallerInterface::class)->install(['content_moderation']);
      $workflow = $this->createEditorialWorkflow();
      $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'article');
      $permissions = \array_keys(\Drupal::classResolver(Permissions::class)->transitionPermissions());
      $canvas_role = Role::load('canvas');
      \assert($canvas_role instanceof RoleInterface);
      foreach ($permissions as $permission) {
        $canvas_role->grantPermission($permission)->save();
      }
    }
    // Add a multi-value date and time field.
    $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', '2025-04-01T04:15:00');
    \assert($date instanceof \DateTimeImmutable);
    $date_field = 'field_cvt_datetime_timestamp';
    self::assertNull(FieldStorageConfig::loadByName('node', $date_field));
    FieldStorageConfig::create([
      'field_name' => $date_field,
      'entity_type' => 'node',
      'type' => 'datetime',
      'settings' => [
        'datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME,
      ],
      'cardinality' => 3,
    ])->save();
    self::assertNull(FieldConfig::loadByName('node', 'article', $date_field));
    FieldConfig::create([
      'field_name' => $date_field,
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Date-time',
      'settings' => [
        'datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME,
      ],
      'default_value' => [
        [
          'default_date_type' => 'relative',
          'default_date' => $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        ],
      ],
    ])->save();
    \Drupal::service(EntityDisplayRepositoryInterface::class)->getFormDisplay('node', 'article')->setComponent($date_field, [
      'type' => 'datetime_timestamp',
      'settings' => [],
    ])->save();

    $account = $this->createUser(values: [
      'roles' => [
        'canvas',
      ],
    ]);
    \assert($account instanceof AccountInterface);
    $this->setCurrentUser($account);
    $valid_client_json = $this->getValidClientJson(FALSE);
    // The client may not filter out form inputs that are not entity fields.
    $valid_client_json['entity_form_fields']['field_image_0_upload_button'] = 'Not an entity field input';
    $this->assertConvert(
      $valid_client_json,
      [],
      'The updated title.'
    );

    $single_propless_component_client_json = $valid_client_json;
    $component = Component::load('sdc.canvas_test_sdc.druplicon');
    $propless_uuid = '4ad36179-a9bd-4bc8-8a4a-241e73dbed25';
    $single_propless_component_client_json['layout']['components'] = [
      [
        'nodeType' => 'component',
        'uuid' => $propless_uuid,
        'type' => 'sdc.canvas_test_sdc.druplicon@8fe3be948e0194e1',
        'slots' => [],
      ],
    ];
    $single_propless_component_client_json['model'] = [
      $propless_uuid => [],
    ];
    $node = $this->assertConvert(
      $single_propless_component_client_json,
      [],
      'The updated title.'
    );
    $item_list = $node->get('field_canvas_demo');
    \assert($item_list instanceof ComponentTreeItemList);
    $item = $item_list->getComponentTreeItemByUuid($propless_uuid);
    \assert($item instanceof ComponentTreeItem);
    // The converted item should store the active version ID at the time it was
    // converted rather than 'active'.
    self::assertNotEquals(VersionedConfigEntityInterface::ACTIVE_VERSION, $item->getComponentVersion());
    self::assertEquals($component?->getActiveVersion(), $item->getComponentVersion());

    $unreferenced_file_client_json = $valid_client_json;
    $unreferenced_src = $this->getSrcPropertyFromFile($this->unreferencedImage);
    $unreferenced_file_client_json['model'][self::TEST_IMAGE_UUID]['resolved']['image']['src'] = $unreferenced_src;
    // Remove the valid image reference from source values.
    unset($unreferenced_file_client_json['model'][self::TEST_IMAGE_UUID]['source']['image']['value']);

    $this->assertConvert(
      $unreferenced_file_client_json,
      [
        // The failed transformation above results in an empty value for the
        // entire SDC prop. Which then fails SDC validation.
        // @see \Drupal\Core\Theme\Component\ComponentValidator::validateProps()
        'model.' . self::TEST_IMAGE_UUID . '.image' => 'The property image is required.',
      ],
      // The error above happens in `\Drupal\canvas\Controller\ClientServerConversionTrait::convertClientToServer()`
      // therefore the title, as well as other entity fields will not be updated.
      'The original title.'
    );

    $invalid_heading_client_json = $valid_client_json;
    $invalid_heading_client_json['model'][self::TEST_HEADING_UUID]['resolved']['style'] = 'not-a-style';
    $this->assertConvert(
      $invalid_heading_client_json,
      ['model.' . self::TEST_HEADING_UUID . '.style' => 'Does not have a value in the enumeration ["primary","secondary"]. The provided value is: "not-a-style".'],
      // The error above happens in `\Drupal\canvas\Controller\ClientServerConversionTrait::convertClientToServer()`
      // therefore the title, as well as other entity fields will not be updated.
      'The original title.',
    );

    $invalid_missing_heading_props_client_json = $valid_client_json;
    unset($invalid_missing_heading_props_client_json['model'][self::TEST_HEADING_UUID]);
    $this->assertConvert(
      $invalid_missing_heading_props_client_json,
      [
        'model.' . self::TEST_HEADING_UUID . '.text' => 'The property text is required.',
        'model.' . self::TEST_HEADING_UUID . '.element' => 'The property element is required.',
      ],
      // The error above happens in `\Drupal\canvas\Controller\ClientServerConversionTrait::convertClientToServer()`
      // therefore the title, as well as other entity fields will not be updated.
      'The original title.',
    );

    // If the client tries to update a field the user does not have access to edit, the field should remain unchanged.
    $permissions = [];
    if ($with_content_moderation) {
      $permissions[] = 'use editorial transition create_new_draft';
    }
    $this->setupCurrentUser([], $permissions);
    $limited_user = \Drupal::currentUser();
    $limited_user_id = $limited_user->id();
    $new_author = \sprintf('%s (%d)', $limited_user->getDisplayName(), $limited_user_id);
    $test_node = $this->createTestNode();
    $this->assertFalse($test_node->get('sticky')->access('edit'));
    $this->assertTrue($test_node->get('sticky')->access('view'));
    $this->assertFalse($test_node->isSticky());
    $invalid_field_access_client_json = $valid_client_json;
    $invalid_field_access_client_json['entity_form_fields']['sticky[value]'] = TRUE;
    $this->assertConvert(
      $invalid_field_access_client_json,
      [],
      'The updated title.',
      $test_node
    );
    // The field value should remain unchanged.
    $this->assertFalse($test_node->isSticky());

    // If the client sends a field the user does not have access to edit, but the field value is the same as the current value no violation should be returned.
    $no_field_access_field_unchanged_client_json = $valid_client_json;
    $no_field_access_field_unchanged_client_json['entity_form_fields']['sticky[value]'] = FALSE;
    $test_node = $this->createTestNode();
    $this->assertFalse($test_node->get('sticky')->access('edit'));
    $this->assertTrue($test_node->get('sticky')->access('view'));
    $this->assertFalse($test_node->isSticky());
    $this->assertConvert(
      $no_field_access_field_unchanged_client_json,
      [],
      'The updated title.',
      $test_node
    );
    // The field value should remain unchanged.
    $this->assertFalse($test_node->isSticky());

    // If the client has elevated permissions, they can update protected fields.
    $permissions = ['administer nodes'];
    if ($with_content_moderation) {
      $permissions[] = 'use editorial transition create_new_draft';
    }
    $this->setupCurrentUser([
      'timezone' => \date_default_timezone_get(),
    ], $permissions);
    $test_node = $this->createTestNode();
    self::assertTrue($test_node->get('sticky')->access('edit'));
    self::assertTrue($test_node->get('sticky')->access('view'));
    self::assertFalse($test_node->isSticky());
    self::assertEquals(3, (int) $test_node->getOwnerId());
    self::assertNotEquals(3, $limited_user->id());
    $protected_field_updated_json = $valid_client_json;
    $protected_field_updated_json['entity_form_fields']['sticky[value]'] = TRUE;
    // Test a form element that is more complex and features a validate callback
    // that changes the form value - e.g. EntityAutocomplete element.
    // @see \Drupal\Core\Entity\Element\EntityAutocomplete::validateEntityAutocomplete
    $protected_field_updated_json['entity_form_fields']['uid[0][target_id]'] = $new_author;
    $this->assertConvert(
      $protected_field_updated_json,
      [],
      'The updated title.',
      $test_node
    );
    self::assertTrue($test_node->isSticky());
    self::assertGreaterThan(0, $limited_user->id());
    self::assertEquals($limited_user_id, (int) $test_node->getOwnerId());

    $test_node = $this->createTestNode();
    self::assertEquals(3, (int) $test_node->getOwnerId());
    self::assertNotEquals(3, $limited_user->id());
    $invalid_form_callback_client_json = $valid_client_json;
    // Test a form element that has a validate callback, the validation should
    // bubble up from the form. Submit a value that doesn't pass validation.
    // @see \Drupal\Core\Entity\Element\EntityAutocomplete::validateEntityAutocomplete
    $invalid_form_callback_client_json['entity_form_fields']['uid[0][target_id]'] = 'Strikes and gutters, ups and downs';
    $this->assertConvert(
      $invalid_form_callback_client_json,
      ['uid.0.target_id' => 'There are no users matching "Strikes and gutters, ups and downs".'],
      // Other valid entity values should be updated for storage in the
      // auto-save store, otherwise there is no change to detect when generating
      // the hash and therefore no auto-save entry created.
      'The updated title.',
      $test_node,
    );
    // Owner field will not be updated and will retain the original value.
    self::assertEquals(3, $test_node->getOwnerId());

    $utc = new \DateTimeZone('UTC');
    $test_node = $this->createTestNode([
      $date_field => [
        [
          'value' => $date->setTimezone($utc)->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        ],
        [
          'value' => $date->setTimezone($utc)->modify('+2 days')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        ],
      ],
    ]);
    self::assertSame([], self::violationsToArray($test_node->validate()));
    $result = \Drupal::classResolver(ApiLayoutController::class)->get($test_node);
    \assert($result instanceof PreviewEnvelope);
    $invalid_form_callback_client_json = \array_intersect_key($result->additionalData, \array_flip(['layout', 'model', 'entity_form_fields']));
    // Assert the default date and time values are returned.
    self::assertEquals('2025-04-01', $date->format('Y-m-d'));
    self::assertEquals($date->format('Y-m-d'), $invalid_form_callback_client_json['entity_form_fields'][\sprintf('%s[0][value][date]', $date_field)]);
    self::assertEquals('04:15:00', $date->format('H:i:s'));
    self::assertEquals($date->format('H:i:s'), $invalid_form_callback_client_json['entity_form_fields'][\sprintf('%s[0][value][time]', $date_field)]);
    self::assertEquals($date->modify('+2 days')->format('Y-m-d'), $invalid_form_callback_client_json['entity_form_fields'][\sprintf('%s[1][value][date]', $date_field)]);
    self::assertEquals($date->modify('+2 days')->format('H:i:s'), $invalid_form_callback_client_json['entity_form_fields'][\sprintf('%s[1][value][time]', $date_field)]);
    // Submit with an invalid value for time in the second item/delta.
    $invalid_form_callback_client_json['entity_form_fields'][\sprintf('%s[1][value][time]', $date_field)] = '';
    // But a valid value in the first item/delta
    $invalid_form_callback_client_json['entity_form_fields'][\sprintf('%s[0][value][time]', $date_field)] = $date->modify('+2 hours')->format('H:i:s');
    // And a third (new) item/delta.
    $invalid_form_callback_client_json['entity_form_fields'][\sprintf('%s[2][value][date]', $date_field)] = $date->modify('+5 hours')->format('Y-m-d');
    $invalid_form_callback_client_json['entity_form_fields'][\sprintf('%s[2][value][time]', $date_field)] = $date->modify('+5 hours')->format('H:i:s');
    $invalid_form_callback_client_json['entity_form_fields']['title[0][value]'] = 'The updated title.';
    $invalid_form_callback_client_json['layout'] = $valid_client_json['layout'];
    $invalid_form_callback_client_json['model'] = $valid_client_json['model'];
    $this->assertConvert(
      $invalid_form_callback_client_json,
      [
        'field_cvt_datetime_timestamp.1.value' => 'The Date-time (value 2) date is invalid. Enter a date in the correct format.',
      ],
      // Other valid entity values should be updated for storage in the
      // auto-save store, otherwise there is no change to detect when generating
      // the hash and therefore no auto-save entry created.
      'The updated title.',
      $test_node,
    );
    // First delta will return the updated value.
    self::assertEquals($date->setTimezone($utc)->modify('+2 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), $test_node->get($date_field)->get(0)?->get('date')->getValue()->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
    // Second delta will return the original value because the submitted values
    // were invalid.
    self::assertEquals($date->setTimezone($utc)->modify('+2 days')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), $test_node->get($date_field)->get(1)?->get('date')->getValue()->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));
    // Third (new) delta is also retained.
    self::assertEquals($date->setTimezone($utc)->modify('+5 hours')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), $test_node->get($date_field)->get(2)?->get('date')->getValue()->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT));

    // Ensure that the entity values are passed through the widget.
    $modify_title_client_json = $valid_client_json;
    $modify_title_client_json['entity_form_fields']['title[0][value]'] = 'Hey widget, modify me!';
    $this->assertConvert(
      $modify_title_client_json,
      [],
      'Modified!',
    );

    $this->container->get('module_installer')->install(['canvas_test_article_fields']);
    // Remove the field_cvt_textarea_summary field installed by
    // canvas_test_article_fields because it is not used in the test and causes
    // unrelated validation errors.
    $field = FieldConfig::load('node.article.field_cvt_textarea_summary');
    if ($field) {
      $field->delete();
    }

    $this->setUpCurrentUser([], [
      'administer url aliases',
      'edit any article content',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
    ]);
    $no_view_field_access_client_json = $valid_client_json;

    // Assert we get access error when the user cannot view the field but the
    // set value is the same as the current value.
    $no_view_field_access_client_json['entity_form_fields']['field_cvt_comment[0][status]'] = 2;
    $test_node = $this->createTestNode();
    /** @var \Drupal\comment\CommentFieldItemList $comment_field */
    $comment_field = $test_node->get('field_cvt_comment');
    /** @var \Drupal\comment\Plugin\Field\FieldType\CommentItem $first_comment */
    $first_comment = $comment_field->first();
    $this->assertSame(2, $first_comment->get('status')->getValue());
    $this->assertFalse($comment_field->access('view'));
    $this->assertConvert(
      $no_view_field_access_client_json,
      ['entity_form_fields.field_cvt_comment' => "The current user is not allowed to update the field 'field_cvt_comment'."],
      'The updated title.',
      $test_node
    );

    // Assert we get access error when the user cannot view the field but the
    // set value is different from the current value.
    $no_view_field_access_client_json['entity_form_fields']['field_cvt_comment[0][status]'] = 1;
    $test_node = $this->createTestNode();
    /** @var \Drupal\comment\CommentFieldItemList $comment_field */
    $comment_field = $test_node->get('field_cvt_comment');
    /** @var \Drupal\comment\Plugin\Field\FieldType\CommentItem $first_comment */
    $first_comment = $comment_field->first();
    $this->assertFalse($comment_field->access('view'));
    $this->assertSame(2, $first_comment->get('status')->getValue());
    $this->assertConvert(
      $no_view_field_access_client_json,
      ['entity_form_fields.field_cvt_comment' => "The current user is not allowed to update the field 'field_cvt_comment'."],
      'The updated title.',
      $test_node
    );

    // @todo Test case where the user does not have access to view the field.
    //   Right now this is tricky because field access does not take into account
    //   entity access.
    $test_node = $this->createTestNode();
    // 🔥 Field access does not take into account parent entity access, i.e. you
    // edit the field but not the entity🤔.
    // Fix in https://drupal.org/i/3494915
    $this->assertTrue((!$test_node->access('edit')) && $test_node->get('title')->access('edit'));
  }

  /**
   * Tests that the 'changed' field from the client ignored.
   *
   * \Drupal\canvas\ClientDataToEntityConverter::convert() will
   * automatically update the `changed` field because it creates a form object
   * submits the form.
   *
   * @see \Drupal\Core\Entity\ContentEntityForm::updateChangedTime
   * @see ClientDataToEntityConverter::checkPatchFieldAccess
   */
  public function testClientChangedTimeIgnored(): void {
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    \Drupal::keyValue('canvas_test_time')->set('request_time', TestTime::$requestTime);
    $client_json = $this->getValidClientJson(FALSE);
    // Ensure the server will ignore the value in the 'changed' field by sending
    // a non-int value.
    $client_json['entity_form_fields']['changed'] = 'Hammer Time!';
    $node = $this->assertConvert($client_json, [], 'The updated title.');
    self::assertCount(0, $autoSave->getEntityFormViolations($node));
    self::assertEquals(TestTime::$requestTime, $node->getChangedTime());

    // Ensure we can send the exact value that 'changed' field will be updated to.
    $client_json['entity_form_fields']['changed'] = TestTime::$requestTime;
    $node = $this->assertConvert($client_json, [], 'The updated title.');
    self::assertCount(0, $autoSave->getEntityFormViolations($node));
    self::assertEquals(TestTime::$requestTime, $node->getChangedTime());

    // Ensure we can send any int.
    $client_json['entity_form_fields']['changed'] = 42;
    $node = $this->assertConvert($client_json, [], 'The updated title.');
    self::assertCount(0, $autoSave->getEntityFormViolations($node));
    self::assertEquals(TestTime::$requestTime, $node->getChangedTime());
  }

  protected function assertConvert(array $client_json, array $expected_errors, string $expected_title, ?Node $node = NULL): NodeInterface {
    $node = $node ?? $this->createTestNode();
    $form = \Drupal::entityTypeManager()->getFormObject($node->getEntityTypeId(), 'default');
    $form_state = $this->buildFormState($form, $node, 'default');
    \Drupal::formBuilder()->buildForm($form, $form_state);
    $node_values = \array_reduce(\array_filter($node->getFields(), static fn(FieldItemListInterface $field) => !$field->isEmpty()), static fn(array $carry, FieldItemListInterface $field) => [
      ...$carry,
      $field->getName() => $field->getValue(),
    ], []);
    unset($node_values['field_canvas_demo']);
    if (!\Drupal::currentUser()->hasPermission('create url aliases')) {
      unset($node_values['path']);
    }
    try {
      if (!\Drupal::currentUser()->hasPermission('administer nodes')) {
        unset($node_values['created']);
      }
      $values = Query::parse(\http_build_query(\array_intersect_key($form_state->getValues(), $node_values)));
      $client_json['entity_form_fields'] += $values;
      \parse_str(\http_build_query(\array_diff_key($values, $client_json['entity_form_fields'])), $unchanged_fields);
      $this->container->get(ClientDataToEntityConverter::class)->convert($client_json, $node);
      self::assertCount(0, $expected_errors);
      // If no violations occurred, the node should be valid.
      $this->assertCount(0, $node->validate());
      $this->assertSame(SAVED_UPDATED, $node->save());
    }
    catch (ConstraintViolationException $e) {
      $violations = $e->getConstraintViolationList();
      $this->assertInstanceOf(EntityConstraintViolationList::class, $violations);
      $this->assertSame($node->id(), $violations->entity->id());
      $this->assertSame($expected_errors, self::violationsToArray($violations));
    }
    $this->assertSame($expected_title, (string) $node->getTitle());

    // Ensure the unchanged fields are not updated.
    // TRICKY: We can't directly compare `$client_json['entity_form_fields'][$field_name]`
    // to `$node->get($field_name)->getValue()` because after fields have been
    // set the type of values seem to change. For example, 'status' changes
    // from 0 to false and timestamps change from int to string. Therefore, we
    // need to duplicate the node which allows us to compare the values using
    // \Drupal\Core\Field\FieldItemListInterface::equals() which will handle
    // these differences.
    $cloned = $node->createDuplicate();
    foreach ($unchanged_fields as $field_name) {
      \assert(\is_string($field_name));
      $cloned->get($field_name)->setValue($client_json['entity_form_fields'][$field_name]);
      if ($field_name === 'vid' && \Drupal::moduleHandler()->moduleExists('content_moderation')) {
        // Content moderation forces a new revision and hence the revision ID
        // will be incremented.
        self::assertGreaterThan((int) $client_json['entity_form_fields'][$field_name], (int) $node->getRevisionId());
        continue;
      }
      $this->assertTrue($cloned->get($field_name)->equals($node->get($field_name)), "The field '$field_name' was not updated.");
    }
    return $node;
  }

  protected function createTestNode(array $values = []): Node {
    $node = Node::create([
      'status' => FALSE,
      'uid' => $this->otherUser->id(),
      'type' => 'article',
      'title' => 'The original title.',
      'field_canvas_demo' => [
        'tree' => [
          ComponentTreeItemList::ROOT_UUID => [],
        ],
        'inputs' => [],
      ],
      'revision_log' => [
        [
          'value' => 'Initial revision.',
        ],
      ],
    ] + $values);
    assert($node instanceof Node);
    $this->assertSame(SAVED_NEW, $node->save());
    return $node;
  }

  public function testBooleanCheckboxesNotForBooleanField(): void {
    \Drupal::service(ModuleInstallerInterface::class)->install(['canvas_test_article_fields']);
    \Drupal::keyValue(CanvasTestArticleFieldsHooks::CANVAS_STATE)->set(CanvasTestArticleFieldsHooks::GRAVY_STATE, TRUE);
    $autoSave = $this->container->get(AutoSaveManager::class);
    \assert($autoSave instanceof AutoSaveManager);
    $this->setupCurrentUser(permissions: [
      'access administration pages',
      'edit any article content',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
    ]);
    $node = Node::load(1);
    \assert($node instanceof NodeInterface);
    $url = Url::fromRoute('canvas.api.layout.get', [
      'entity' => $node->id(),
      'entity_type' => 'node',
    ]);

    // Originally the `No more gravy please` checkbox is checked.
    $response = $this->request(Request::create($url->toString()));
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $json = \json_decode($response->getContent() ?: '', TRUE, \JSON_THROW_ON_ERROR);
    self::assertEquals(TRUE, $json['entity_form_fields'][CanvasTestArticleFieldsHooks::NO_MORE_GRAVY]);
    self::assertNull($autoSave->getAutoSaveEntity($node)->entity);
    self::assertEquals('Canvas Needs This For The Time Being', $json['entity_form_fields']['title[0][value]']);

    // Uncheck this checkbox. This should change the (auto-saved) entity title.
    // @see \Drupal\canvas_test_article_fields\Hook\CanvasTestArticleFieldsHooks::canvasPageEntityGravyBuilder()
    $json['entity_form_fields'][CanvasTestArticleFieldsHooks::NO_MORE_GRAVY] = FALSE;
    unset($json['isNew'], $json['isPublished'], $json['html']);
    $json += $this->getPostContentsDefaults($node);
    $response = $this->request(Request::create('/canvas/api/v0/layout/node/1', method: 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: \json_encode($json, \JSON_THROW_ON_ERROR)));
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $autoSaveEntity = $autoSave->getAutoSaveEntity($node)->entity;
    // @phpstan-ignore-next-line
    self::assertInstanceOf(NodeInterface::class, $autoSaveEntity);
    self::assertSame('Gravy!', $autoSaveEntity->label());

    // Re-check it. This should change the (auto-saved) entity title again.
    // @see \Drupal\canvas_test_article_fields\Hook\CanvasTestArticleFieldsHooks::canvasPageEntityGravyBuilder()
    $json['entity_form_fields'][CanvasTestArticleFieldsHooks::NO_MORE_GRAVY] = TRUE;
    unset($json['isNew'], $json['isPublished'], $json['html']);
    $json += $this->getPostContentsDefaults($node);
    $response = $this->request(Request::create('/canvas/api/v0/layout/node/1', method: 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: \json_encode($json, \JSON_THROW_ON_ERROR)));
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    $autoSaveEntity = $autoSave->getAutoSaveEntity($node)->entity;
    // @phpstan-ignore-next-line
    self::assertInstanceOf(NodeInterface::class, $autoSaveEntity);
    self::assertSame('No more gravy', $autoSaveEntity->label());
  }

}

class TestWidgetManager extends WidgetPluginManager {

  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    if (isset($definitions['string_textfield'])) {
      $definitions['string_textfield']['class'] = TestStringTextfieldWidget::class;
    }
    return $definitions;
  }

}

class TestStringTextfieldWidget extends StringTextfieldWidget {

  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);
    if ($values[0]['value'] === 'Hey widget, modify me!') {
      $values[0]['value'] = 'Modified!';
    }
    return $values;
  }

}

/**
 * A test-only implementation of the time service.
 */
class TestTime extends Time {

  /**
   * An offset to add to the request time.
   *
   * @var int
   */
  public static int $requestTime = 123456789;

  /**
   * {@inheritdoc}
   */
  public function getRequestTime() {
    return static::$requestTime;
  }

}

