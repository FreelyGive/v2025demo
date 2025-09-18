<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Controller;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Url;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\canvas\Kernel\Traits\PageTrait;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the Component Audit Controller UI.
 *
 * @group canvas
 */
final class ComponentAuditControllerTest extends KernelTestBase {

  use ContribStrictConfigSchemaTestTrait;
  use PageTrait;
  use RequestTrait;
  use UserCreationTrait;
  use GenerateComponentConfigTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'system',
    'node',
    'canvas_test_sdc',
    ...self::PAGE_TEST_MODULES,
    // Canvas's dependencies (modules providing field types + widgets).
    'ckeditor5',
    'editor',
    'field',
    'file',
    'image',
    'link',
    'media',
    'node',
    'options',
    'text',
  ];

  protected function setUp(): void {
    parent::setUp();
    // Drupal Canvas configuration (creates the global AssetLibrary).
    $this->installConfig('canvas');
    $this->generateComponentConfig();

    $this->container->get('theme_installer')->install(['stark']);

    // Needed for date formats.
    $this->installConfig(['system']);
    $this->installConfig('node');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('field_storage_config');
    $this->installEntitySchema('field_config');
    $this->installEntitySchema(Page::ENTITY_TYPE_ID);

    $this->createContentType(['name' => 'Article', 'type' => 'article']);

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_canvas_test',
      'type' => 'component_tree',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'field_canvas_test',
      'label' => 'Canvas Test Field',
      'required' => TRUE,
    ])->setDefaultValue([
      [
        'uuid' => 'bd4ae317-3f4d-4b82-a3ca-452d916ae715',
        'component_id' => 'sdc.canvas_test_sdc.druplicon',
        'component_version' => '8fe3be948e0194e1',
        'inputs' => [],
      ],
    ])->save();
  }

  /**
   * Tests controller output when adding or editing an entity.
   */
  public function testController(): void {
    $this->setUpCurrentUser(permissions: [
      'administer themes',
      Component::ADMIN_PERMISSION,
      Page::CREATE_PERMISSION,
      Page::EDIT_PERMISSION,
    ]);

    $entity_data = $this->entityData();

    $entity_type_manager = $this->container->get('entity_type.manager');
    $storages = [];

    foreach ($entity_data as $entity_type_id => $bundle_data) {
      $storages[$entity_type_id] = $entity_type_manager->getStorage($entity_type_id);
      foreach ($bundle_data as $entities_data) {
        foreach ($entities_data as $values) {
          $entity = $storages[$entity_type_id]->create($values);
          $entity->save();
        }
      }
    }
    $page1 = $storages[Page::ENTITY_TYPE_ID]->load(1);
    assert($page1 instanceof Page);
    $page1->get('components')->setValue([
      [
        'uuid' => 'component-sdc',
        'component_id' => 'sdc.canvas_test_sdc.druplicon',
        'inputs' => [],
      ],
    ]);
    $page1->setUnpublished()
      ->setNewRevision(TRUE);
    $page1->save();

    $node1 = $storages['node']->load(1);
    assert($node1 instanceof NodeInterface);
    $node1->get('field_canvas_test')->setValue([
      [
        'uuid' => 'component-sdc',
        'component_id' => 'sdc.canvas_test_sdc.druplicon',
        'inputs' => [],
      ],
    ]);
    $node1->setNewRevision(TRUE);
    $node1->save();

    $audit_url = Url::fromRoute('entity.component.audit', ['component' => 'sdc.canvas_test_sdc.props-slots'])->toString();
    $response = $this->request(Request::create($audit_url));
    assert($response instanceof HtmlResponse);
    $this->assertSame([
      'theme',
      'user.roles:authenticated',
      'languages:language_interface',
      'user.permissions',
      'url.query_args:_wrapper_format',
    ], $response->getCacheableMetadata()->getCacheContexts());
    $this->assertSame([
      'rendered',
      'http_response',
    ], $response->getCacheableMetadata()->getCacheTags());

    $this->assertTitle('Audit of Canvas test SDC with props and slots usages | ');

    $this->assertTableCellContains('table-content', 1, 1, 'Test page');
    $this->assertTableCellContains('table-content', 1, 2, 'Page');
    $this->assertTableCellContains('table-content', 1, 3, 'Page');
    $this->assertTableCellContains('table-content', 1, 4, '1');
    $this->assertTableCellContains('table-content', 1, 5, '1');
    $this->assertTableCellContains('table-content', 1, 6, '❌');
    $this->assertTableCellContains('table-content', 1, 7, '❌');

    $this->assertTableCellContains('table-content', 2, 1, 'Another test page');
    $this->assertTableCellContains('table-content', 2, 2, 'Page');
    $this->assertTableCellContains('table-content', 2, 3, 'Page');
    $this->assertTableCellContains('table-content', 2, 4, '2');
    $this->assertTableCellContains('table-content', 2, 5, '2');
    $this->assertTableCellContains('table-content', 2, 6, '✔');
    $this->assertTableCellContains('table-content', 2, 7, '✔');

    $this->assertTableCellContains('table-content', 3, 1, 'Test entity');
    $this->assertTableCellContains('table-content', 3, 2, 'Content');
    $this->assertTableCellContains('table-content', 3, 3, 'Article');
    $this->assertTableCellContains('table-content', 3, 4, '1');
    $this->assertTableCellContains('table-content', 3, 5, '1');
    $this->assertTableCellContains('table-content', 3, 6, '❌');
    $this->assertTableCellContains('table-content', 3, 7, '❌');

    $audit_url = Url::fromRoute('entity.component.audit', ['component' => 'sdc.canvas_test_sdc.druplicon'])->toString();
    $response = $this->request(Request::create($audit_url));
    assert($response instanceof HtmlResponse);
    $this->assertSame([
      'theme',
      'user.roles:authenticated',
      'languages:language_interface',
      'user.permissions',
      'url.query_args:_wrapper_format',
    ], $response->getCacheableMetadata()->getCacheContexts());
    $this->assertSame([
      'rendered',
      'http_response',
    ], $response->getCacheableMetadata()->getCacheTags());

    $this->assertTitle('Audit of Druplicon usages | ');

    $this->assertTableCellContains('table-content', 1, 1, 'Test page');
    $this->assertTableCellContains('table-content', 1, 2, 'Page');
    $this->assertTableCellContains('table-content', 1, 3, 'Page');
    $this->assertTableCellContains('table-content', 1, 4, '1');
    $this->assertTableCellContains('table-content', 1, 5, '3');
    $this->assertTableCellContains('table-content', 1, 6, '✔');
    $this->assertTableCellContains('table-content', 1, 7, '✔');

    $this->assertTableCellContains('table-content', 2, 1, 'Test entity');
    $this->assertTableCellContains('table-content', 2, 2, 'Content');
    $this->assertTableCellContains('table-content', 2, 3, 'Article');
    $this->assertTableCellContains('table-content', 2, 4, '1');
    $this->assertTableCellContains('table-content', 2, 5, '2');
    $this->assertTableCellContains('table-content', 2, 6, '✔');
    $this->assertTableCellContains('table-content', 2, 7, '✔');
  }

  private function assertTableCellContains(string $table_name, int $row_index, int $column_index, string $needle): void {
    $xpath_element = $this->xpath("//table[@name=\"$table_name\"]//tr[$row_index]//td[$column_index]");
    assert(\is_array($xpath_element) && \array_key_exists(0, $xpath_element));
    $this->assertStringContainsString($needle, trim((string) $xpath_element[0]->asXML()));
  }

  private function entityData(): array {
    return [
      Page::ENTITY_TYPE_ID => [
        Page::ENTITY_TYPE_ID => [
          [
            'title' => 'Test page',
            'description' => 'This is a test page.',
            'status' => TRUE,
            'components' => [
              [
                'uuid' => 'component-sdc',
                'component_id' => 'sdc.canvas_test_sdc.props-slots',
                'inputs' => [
                  'heading' => [
                    'sourceType' => 'static:field_item:string',
                    'value' => 'This is my header',
                    'expression' => 'ℹ︎string␟value',
                  ],
                ],
              ],
            ],
          ],
          [
            'title' => 'Another test page',
            'description' => 'This is another test page.',
            'status' => TRUE,
            'components' => [
              [
                'uuid' => 'component-sdc',
                'component_id' => 'sdc.canvas_test_sdc.props-slots',
                'inputs' => [
                  'heading' => [
                    'sourceType' => 'static:field_item:string',
                    'value' => 'This is my header',
                    'expression' => 'ℹ︎string␟value',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'node' => [
        'article' => [
          [
            'title' => 'Test entity',
            'status' => TRUE,
            'type' => 'article',
            'field_canvas_test' => [
              [
                'uuid' => 'component-sdc',
                'component_id' => 'sdc.canvas_test_sdc.props-slots',
                'inputs' => [
                  'heading' => [
                    'sourceType' => 'static:field_item:string',
                    'value' => 'This is my header',
                    'expression' => 'ℹ︎string␟value',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
