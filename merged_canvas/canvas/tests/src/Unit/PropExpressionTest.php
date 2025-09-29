<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression;
use Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression;
use Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface;
use Drupal\canvas\TypedData\BetterEntityDataDefinition;
use Drupal\Tests\UnitTestCase;
use Prophecy\Prophet;

/**
 * @coversDefaultClass \Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpression
 * @coversClass \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression
 * @coversClass \Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldPropExpression
 * @coversClass \Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression
 * @coversClass \Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression
 * @coversClass \Drupal\canvas\PropExpressions\StructuredData\ReferenceFieldTypePropExpression
 * @coversClass \Drupal\canvas\PropExpressions\StructuredData\FieldTypeObjectPropsExpression
 * @see \Drupal\Tests\canvas\Kernel\PropExpressionDependenciesTest
 * @group canvas
 *
 * @phpstan-import-type ConfigDependenciesArray from \Drupal\canvas\Entity\VersionedConfigEntityInterface
 */
class PropExpressionTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('typed_data_manager', $this->prophesize(TypedDataManagerInterface::class)->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * @dataProvider provider
   */
  public function testToString(string $string_representation, StructuredDataPropExpressionInterface $expression): void {
    $this->assertSame($string_representation, (string) $expression);
  }

  /**
   * @dataProvider provider
   */
  public function testFromString(string $string_representation, StructuredDataPropExpressionInterface $expression): void {
    $reconstructed = call_user_func([get_class($expression), 'fromString'], $string_representation);
    $this->assertEquals($expression, $reconstructed);
    $this->assertEquals($expression, StructuredDataPropExpression::fromString($string_representation));
  }

  /**
   * Combines the cases of all individual data providers, assigns clear labels.
   *
   * @return array<array{0: string, 1: FieldPropExpression|ReferenceFieldPropExpression|FieldObjectPropsExpression|FieldTypePropExpression|ReferenceFieldTypePropExpression|FieldTypeObjectPropsExpression, 2: ConfigDependenciesArray|\Exception}>
   */
  public static function provider(): array {
    // Allow this provider to be called by a kernel test, too.
    $original_container = \Drupal::hasContainer() ? \Drupal::getContainer() : FALSE;

    $container = new ContainerBuilder();
    $prophet = new Prophet();
    $container->set('typed_data_manager', $prophet->prophesize(TypedDataManagerInterface::class)->reveal());
    \Drupal::setContainer($container);
    $generate_meaningful_case_label = function (string $prefix, array $cases) : array {
      return array_combine(
        array_map(fn (int|string $key) => sprintf("$prefix - %s", is_string($key) ? $key : "#$key"), array_keys($cases)),
        $cases,
      );
    };

    if ($original_container) {
      \Drupal::setContainer($original_container);
    }

    return $generate_meaningful_case_label('FieldPropExpression', self::providerFieldPropExpression())
      + $generate_meaningful_case_label('FieldReferencePropExpression', self::providerReferenceFieldPropExpression())
      + $generate_meaningful_case_label('FieldObjectPropsExpression', self::providerFieldObjectPropsExpression())
      + $generate_meaningful_case_label('FieldTypePropExpression', self::providerFieldTypePropExpression())
      + $generate_meaningful_case_label('ReferenceFieldTypePropExpression', self::providerReferenceFieldTypePropExpression())
      + $generate_meaningful_case_label('FieldTypeObjectPropsExpression', self::providerFieldTypeObjectPropsExpression());
  }

  /**
   * @return array<array{0: string, 1: FieldPropExpression, 2: ConfigDependenciesArray|\Exception}>
   */
  public static function providerFieldPropExpression(): array {
    return [
      // Context: entity type, base field.
      ['ℹ︎␜entity:node␝title␞␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'title', NULL, 'value'),
        [
          'module' => ['node'],
        ],
      ],
      ['ℹ︎␜entity:node␝title␞0␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'title', 0, 'value'),
        [
          'module' => ['node'],
        ],
      ],
      ['ℹ︎␜entity:node␝title␞99␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'title', 99, 'value'),
        [
          'module' => ['node'],
        ],
      ],

      // Context: bundle of entity type, base field.
      ['ℹ︎␜entity:node:article␝title␞␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'title', NULL, 'value'),
        [
          'module' => ['node'],
          'config' => ['node.type.article'],
        ],
      ],
      ['ℹ︎␜entity:node:article␝title␞0␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'title', 0, 'value'),
        [
          'module' => ['node'],
          'config' => ['node.type.article'],
        ],
      ],
      ['ℹ︎␜entity:node:article␝title␞99␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'title', 99, 'value'),
        [
          'module' => ['node'],
          'config' => ['node.type.article'],
        ],
      ],

      // Context: bundle of entity type, configurable field.
      ['ℹ︎␜entity:node:article␝field_image␞␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', NULL, 'value'),
        [
          'module' => ['node', 'file'],
          'config' => ['node.type.article', 'field.field.node.article.field_image', 'image.style.canvas_parametrized_width'],
        ],
      ],
      ['ℹ︎␜entity:node:article␝field_image␞0␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', 0, 'value'),
        [
          'module' => ['node', 'file'],
          'config' => ['node.type.article', 'field.field.node.article.field_image', 'image.style.canvas_parametrized_width'],
        ],
      ],
      ['ℹ︎␜entity:node:article␝field_image␞99␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', 99, 'value'),
        [
          'module' => ['node', 'file'],
          'config' => ['node.type.article', 'field.field.node.article.field_image', 'image.style.canvas_parametrized_width'],
        ],
      ],

      // Context: >1 bundle of entity type, base field.
      ['ℹ︎␜entity:node:article|news␝title␞␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', ['news', 'article']), 'title', NULL, 'value'),
        [
          'module' => ['node'],
          'config' => ['node.type.article', 'node.type.news'],
        ],
      ],

      // Context: >1 bundle of entity type, bundle/configurable field.
      // ⚠️ Note the inconsistent ordering in the object representation, and the
      // consistent ordering based on alphabetical bundle ordering in the string
      // representation.
      ['ℹ︎␜entity:node:article|news|product␝field_image|field_photo|field_product_packaging_photo␞␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', ['news', 'article', 'product']), ['article' => 'field_image', 'news' => 'field_photo', 'product' => 'field_product_packaging_photo'], NULL, 'value'),
        [
          'module' => ['node', 'file', 'file', 'file'],
          'config' => [
            'node.type.article',
            'node.type.news',
            'node.type.product',
            'field.field.node.article.field_image',
            'image.style.canvas_parametrized_width',
            'field.field.node.news.field_photo',
            'image.style.canvas_parametrized_width',
            'field.field.node.product.field_product_packaging_photo',
            'image.style.canvas_parametrized_width',
          ],
        ],
      ],

      // Context: >1 bundle of entity type, bundle/configurable field, with
      // fields of different types and hence different field properties.
      // ⚠️ Note the inconsistent ordering in the object representation, and the
      // consistent ordering based on alphabetical bundle ordering in the string
      // representation.
      ['ℹ︎␜entity:node:article|foo|xyz␝field_image|bar|abc␞␟value|url|␀', new FieldPropExpression(BetterEntityDataDefinition::create('node', ['article', 'foo', 'xyz']), ['article' => 'field_image', 'foo' => 'bar', 'xyz' => 'abc'], NULL, ['field_image' => 'value', 'bar' => 'url', 'abc' => StructuredDataPropExpressionInterface::SYMBOL_OBJECT_MAPPED_OPTIONAL_PROP]),
        [
          'module' => ['node', 'file'],
          'config' => [
            'node.type.article',
            'node.type.foo',
            'node.type.xyz',
            'field.field.node.article.field_image',
            'image.style.canvas_parametrized_width',
            'field.field.node.foo.bar',
            'field.field.node.xyz.abc',
          ],
        ],
      ],

      // Structured data expressions do NOT introspect the data model, they are
      // just stand-alone expressions with a string representation and a PHP
      // object representation. Hence nonsensical values are accepted for all
      // aspects:
      'invalid entity type' => ['ℹ︎␜entity:non_existent␝title␞␟value', new FieldPropExpression(BetterEntityDataDefinition::create('non_existent'), 'title', NULL, 'value'), new PluginNotFoundException('non_existent', 'The "non_existent" entity type does not exist.')],
      'invalid delta' => ['ℹ︎␜entity:node:article␝title␞-1␟value', new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'title', -1, 'value'),
        [
          'module' => ['node'],
          'config' => ['node.type.article'],
        ],
      ],
      'invalid prop name' => ['ℹ︎␜entity:node:article␝title␞␟non_existent', new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'title', NULL, 'non_existent'),
        [
          'module' => ['node'],
          'config' => ['node.type.article'],
        ],
      ],
    ];
  }

  /**
   * @return array<array{0: string, 1: ReferenceFieldPropExpression, 2: ConfigDependenciesArray|\Exception}>
   */
  public static function providerReferenceFieldPropExpression(): array {
    $referencer_delta_null = new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'uid', NULL, 'entity');
    $referencer_delta_zero = new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'uid', 0, 'entity');
    $referencer_delta_high = new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'uid', 123, 'entity');

    return [
      ['ℹ︎␜entity:node␝uid␞␟entity␜␜entity:user␝name␞␟value', new ReferenceFieldPropExpression($referencer_delta_null, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', NULL, 'value')),
        [
          'module' => ['node', 'user'],
          'content' => ['user:user:some-user-uuid'],
        ],
      ],
      ['ℹ︎␜entity:node␝uid␞␟entity␜␜entity:user␝name␞0␟value', new ReferenceFieldPropExpression($referencer_delta_null, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', 0, 'value')),
        [
          'module' => ['node', 'user'],
          'content' => ['user:user:some-user-uuid'],
        ],
      ],
      ['ℹ︎␜entity:node␝uid␞␟entity␜␜entity:user␝name␞99␟value', new ReferenceFieldPropExpression($referencer_delta_null, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', 99, 'value')),
        [
          'module' => ['node', 'user'],
          'content' => ['user:user:some-user-uuid'],
        ],
      ],

      ['ℹ︎␜entity:node␝uid␞0␟entity␜␜entity:user␝name␞␟value', new ReferenceFieldPropExpression($referencer_delta_zero, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', NULL, 'value')),
        [
          'module' => ['node', 'user'],
          'content' => ['user:user:some-user-uuid'],
        ],
      ],
      ['ℹ︎␜entity:node␝uid␞0␟entity␜␜entity:user␝name␞0␟value', new ReferenceFieldPropExpression($referencer_delta_zero, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', 0, 'value')),
        [
          'module' => ['node', 'user'],
          'content' => ['user:user:some-user-uuid'],
        ],
      ],
      ['ℹ︎␜entity:node␝uid␞0␟entity␜␜entity:user␝name␞99␟value', new ReferenceFieldPropExpression($referencer_delta_zero, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', 99, 'value')),
        [
          'module' => ['node', 'user'],
          'content' => ['user:user:some-user-uuid'],
        ],
      ],

      ['ℹ︎␜entity:node␝uid␞123␟entity␜␜entity:user␝name␞␟value', new ReferenceFieldPropExpression($referencer_delta_high, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', NULL, 'value')), new \LogicException('Requested delta 123 for single-cardinality field, must be either zero or omitted.')],
      ['ℹ︎␜entity:node␝uid␞123␟entity␜␜entity:user␝name␞0␟value', new ReferenceFieldPropExpression($referencer_delta_high, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', 0, 'value')), new \LogicException('Requested delta 123 for single-cardinality field, must be either zero or omitted.')],
      ['ℹ︎␜entity:node␝uid␞123␟entity␜␜entity:user␝name␞99␟value', new ReferenceFieldPropExpression($referencer_delta_high, new FieldPropExpression(BetterEntityDataDefinition::create('user'), 'name', 99, 'value')), new \LogicException('Requested delta 123 for single-cardinality field, must be either zero or omitted.')],
    ];
  }

  /**
   * @return array<array{0: string, 1: FieldObjectPropsExpression, 2: ConfigDependenciesArray|\Exception}>
   */
  public static function providerFieldObjectPropsExpression(): array {
    return [
      // Context: entity type, base field.
      [
        'ℹ︎␜entity:node␝title␞0␟{label↠value}',
        new FieldObjectPropsExpression(BetterEntityDataDefinition::create('node'), 'title', 0, [
          // SDC prop accepting an object, with a single mapped key-value pair.
          'label' => new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'title', 0, 'value'),
        ]),
        [
          'module' => ['node'],
        ],
      ],
      [
        'ℹ︎␜entity:node␝title␞␟{label↠value}',
        new FieldObjectPropsExpression(BetterEntityDataDefinition::create('node'), 'title', NULL, [
          // SDC prop accepting an object, with a single mapped key-value pair.
          'label' => new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'title', NULL, 'value'),
        ]),
        [
          'module' => ['node'],
        ],
      ],

      // Context: bundle of entity type, configurable field.
      [
        'ℹ︎␜entity:node:article␝field_image␞␟{src↝entity␜␜entity:file␝uri␞␟url,width↠width}',
        new FieldObjectPropsExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', NULL, [
          // SDC prop accepting an object, with >=1 mapped key-value pairs:
          // 1. one (non-leaf) field property that follows an entity reference
          'src' => new ReferenceFieldPropExpression(
            new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', NULL, 'entity'),
            new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'url'),
          ),
          // 2. one (leaf) field property
          'width' => new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', NULL, 'width'),
        ]),
        [
          'module' => ['node', 'file', 'file', 'node', 'file'],
          'config' => [
            'node.type.article',
            'field.field.node.article.field_image',
            'image.style.canvas_parametrized_width',
            'node.type.article',
            'field.field.node.article.field_image',
            'image.style.canvas_parametrized_width',
          ],
          'content' => ['file:file:some-image-uuid'],
        ],
      ],
      [
        'ℹ︎␜entity:node:article␝field_image␞␟{src↠src_with_alternate_widths,width↠width}',
        new FieldObjectPropsExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', NULL, [
          // SDC prop accepting an object, with >=1 mapped key-value pairs:
          // 1. one (leaf) field property that is computed and has its own
          // dependencies
          'src' => new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', NULL, 'src_with_alternate_widths'),
          // 2. one (leaf) field property
          'width' => new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'field_image', NULL, 'width'),
        ]),
        // Expected content-aware dependencies.
        [
          'module' => ['node', 'file', 'file', 'file', 'node', 'file'],
          'config' => [
            'node.type.article',
            'field.field.node.article.field_image',
            'image.style.canvas_parametrized_width',
            'node.type.article',
            'field.field.node.article.field_image',
            'image.style.canvas_parametrized_width',
          ],
          'content' => ['file:file:some-image-uuid'],
        ],
        // Expected content-unaware dependencies.
        [
          'module' => ['node', 'file', 'node', 'file'],
          'config' => [
            'node.type.article',
            'field.field.node.article.field_image',
            'image.style.canvas_parametrized_width',
            'node.type.article',
            'field.field.node.article.field_image',
            'image.style.canvas_parametrized_width',
          ],
        ],
      ],

      // Digs into multiple levels of an entity reference field to return values
      // from different levels of that reference.
      [
        'ℹ︎␜entity:node:article␝yo_ho␞␟{src↝entity␜␜entity:media:image␝field_media_image␞␟entity␜␜entity:file␝uri␞␟url,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt}',
        new FieldObjectPropsExpression(BetterEntityDataDefinition::create('node', 'article'), 'yo_ho', NULL, [
          'src' => new ReferenceFieldPropExpression(
            new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'yo_ho', NULL, 'entity'),
            new ReferenceFieldPropExpression(
              new FieldPropExpression(BetterEntityDataDefinition::create('media', 'image'), 'field_media_image', NULL, 'entity'),
              new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'url'),
            ),
          ),
          'alt' => new ReferenceFieldPropExpression(
            new FieldPropExpression(BetterEntityDataDefinition::create('node', 'article'), 'yo_ho', NULL, 'entity'),
            new FieldPropExpression(BetterEntityDataDefinition::create('media', 'image'), 'field_media_image', NULL, 'alt'),
          ),
        ]),
        [
          'module' => [
            'node',
            'media',
            'media',
            'file',
            'file',
            'node',
            'media',
            'media',
            'file',
          ],
          'config' => [
            'node.type.article',
            'field.field.node.article.yo_ho',
            'media.type.image',
            'media.type.image',
            'field.field.media.image.field_media_image',
            'image.style.canvas_parametrized_width',
            'node.type.article',
            'field.field.node.article.yo_ho',
            'media.type.image',
            'media.type.image',
            'field.field.media.image.field_media_image',
            'image.style.canvas_parametrized_width',
          ],
          'content' => [
            'media:image:some-media-uuid',
            'file:file:some-image-uuid',
            'media:image:some-media-uuid',
          ],
        ],
      ],
    ];
  }

  /**
   * @return array<array{0: string, 1: FieldTypePropExpression, 2: ConfigDependenciesArray|\Exception}>
   */
  public static function providerFieldTypePropExpression(): array {
    return [
      // Field type with single property.
      // @see \Drupal\Core\Field\Plugin\Field\FieldType\StringItem
      ['ℹ︎string␟value', new FieldTypePropExpression('string', 'value'),
        [],
      ],

      // Field type with >1 properties.
      // @see \Drupal\image\Plugin\Field\FieldType\ImageItem
      ['ℹ︎image␟width', new FieldTypePropExpression('image', 'width'),
        [
          'module' => ['image'],
        ],
      ],
      ['ℹ︎image␟src', new FieldTypePropExpression('image', 'src'),
        [
          'module' => ['image'],
        ],
      ],
      ['ℹ︎image␟src_with_alternate_widths', new FieldTypePropExpression('image', 'src_with_alternate_widths'),
        [
          'module' => [
            'image',
            'image',
            'file',
            'file',
            'image',
          ],
          'content' => [
            'file:file:some-image-uuid',
          ],
        ],
        [
          'module' => [
            'image',
          ],
        ],
      ],

      // Structured data expressions do NOT introspect the data model, they are
      // just stand-alone expressions with a string representation and a PHP
      // object representation. Hence nonsensical values are accepted:
      'invalid prop name' => ['ℹ︎string␟non_existent', new FieldTypePropExpression('string', 'non_existent'),
        [],
      ],
    ];
  }

  /**
   * @return array<array{0: string, 1: ReferenceFieldTypePropExpression, 2: ConfigDependenciesArray|\Exception}>
   */
  public static function providerReferenceFieldTypePropExpression(): array {
    return [
      // Reference field type for a single property.
      // @see \Drupal\Core\Field\Plugin\Field\FieldType\StringItem
      [
        'ℹ︎image␟entity␜␜entity:file␝uri␞0␟value',
        new ReferenceFieldTypePropExpression(
          new FieldTypePropExpression('image', 'entity'),
          new FieldPropExpression(
            BetterEntityDataDefinition::create('file'),
          'uri',
          0,
          'value'
          )
        ),
        [
          'module' => ['image', 'file', 'file'],
          'content' => ['file:file:some-image-uuid'],
        ],
      ],

      // Field type with >1 properties.
      // @see \Drupal\image\Plugin\Field\FieldType\ImageItem
      [
        'ℹ︎image␟entity␜␜entity:file␝uri␞0␟{stream_wrapper_uri↠value,public_url↠url}',
        new ReferenceFieldTypePropExpression(
          new FieldTypePropExpression('image', 'entity'),
          new FieldObjectPropsExpression(
            BetterEntityDataDefinition::create('file'),
            'uri',
            0,
            [
              'stream_wrapper_uri' => new FieldPropExpression(
                BetterEntityDataDefinition::create('file'),
                'uri',
                0,
                'value'
              ),
              'public_url' => new FieldPropExpression(
                BetterEntityDataDefinition::create('file'),
                'uri',
                0,
                'url'
              ),
            ]
          ),
        ),
        [
          'module' => ['image', 'file', 'file', 'file', 'file'],
          'content' => ['file:file:some-image-uuid'],
        ],
      ],

      // Reference field type that fetches a reference of a reference.
      // ℹ️ This test case requires quite some simulating in the sibling kernel
      // test that tests the expected dependencies. To ensure it is accurate,
      // this particular test case also has a functional test.
      // @see \Drupal\Tests\canvas\Kernel\PropExpressionDependenciesTest
      // @see \Drupal\Tests\canvas\Functional\PropExpressionDependenciesTest::testIntermediateDependencies()
      [
        'ℹ︎entity_reference␟entity␜␜entity:media:baby_photos|vacation_photos␝field_media_image_1|field_media_image_2␞␟entity␜␜entity:file␝uri␞␟value',
        new ReferenceFieldTypePropExpression(
          new FieldTypePropExpression('entity_reference', 'entity'),
          new ReferenceFieldPropExpression(
            new FieldPropExpression(BetterEntityDataDefinition::create('media', ['baby_photos', 'vacation_photos']), ['baby_photos' => 'field_media_image_1', 'vacation_photos' => 'field_media_image_2'], \NULL, 'entity'),
            new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'value'),
          ),
        ),
        [
          'content' => [
            'media:baby_photos:baby-photos-media-uuid',
            'file:file:photo-baby-jack-uuid',
          ],
          'module' => [
            'media',
            'file',
            'file',
            'file',
            'media',
            'file',
            'file',
            'file',
          ],
          'config' => [
            'media.type.baby_photos',
            'media.type.vacation_photos',
            'field.field.media.baby_photos.field_media_image_1',
            'image.style.canvas_parametrized_width',
            'field.field.media.vacation_photos.field_media_image_2',
            'image.style.canvas_parametrized_width',
            'media.type.baby_photos',
            'media.type.vacation_photos',
            'field.field.media.baby_photos.field_media_image_1',
            'image.style.canvas_parametrized_width',
            'field.field.media.vacation_photos.field_media_image_2',
            'image.style.canvas_parametrized_width',
          ],
        ],
      ],
    ];
  }

  /**
   * @return array<array{0: string, 1: FieldTypeObjectPropsExpression, 2: ConfigDependenciesArray|\Exception}>
   */
  public static function providerFieldTypeObjectPropsExpression(): array {
    return [
      // Context: entity type, base field.
      [
        'ℹ︎string␟{label↠value}',
        new FieldTypeObjectPropsExpression('string', [
          // SDC prop accepting an object, with a single mapped key-value pair.
          'label' => new FieldTypePropExpression('string', 'value'),
        ]),
        [],
      ],

      // Context: bundle of entity type, configurable field.
      [
        'ℹ︎image␟{src↝entity␜␜entity:file␝uri␞␟url,width↠width}',
        new FieldTypeObjectPropsExpression('image', [
          // SDC prop accepting an object, with >=1 mapped key-value pairs:
          // 1. one (non-leaf) field property that follows an entity reference
          'src' => new ReferenceFieldTypePropExpression(
            new FieldTypePropExpression('image', 'entity'),
            new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', NULL, 'url'),
          ),
          // 2. one (leaf) field property
          'width' => new FieldTypePropExpression('image', 'width'),
        ]),
        [
          'module' => ['image', 'file', 'file', 'image'],
          'content' => ['file:file:some-image-uuid'],
        ],
      ],
    ];
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::__construct()
   * @testWith [null]
   *           ["article"]
   */
  public function testInvalidFieldPropExpressionDueToMultipleFieldNamesWithoutMultipleBundles(?string $bundle): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('When targeting a (single bundle of) an entity type, only a single field name can be specified.');
    new FieldPropExpression(
      BetterEntityDataDefinition::create('node', $bundle),
      [
        'bundle_a' => 'field_image',
        'bundle_b' => 'field_image_1',
      ],
      0,
      'alt',
    );
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::__construct()
   * @testWith [null]
   *           ["article"]
   */
  public function testInvalidFieldPropExpressionDueToMultipleFieldPropNamesWithoutMultipleBundles(?string $bundle): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('When targeting a (single bundle of) an entity type, only a single field property name can be specified.');
    new FieldPropExpression(
      BetterEntityDataDefinition::create('node', $bundle),
      'field_image',
      0,
      [
        'field_image' => 'alt',
        'field_media' => 'description',
      ],
    );
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::__construct()
   */
  public function testInvalidFieldPropExpressionDueToMultipleFieldPropNamesWithoutMultipleFieldNames(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A field property name must be specified for every field name, and in the same order.');
    new FieldPropExpression(
      BetterEntityDataDefinition::create('node', ['bundle_a', 'bundle_b', 'bundle_c']),
      [
        'bundle_a' => 'field_image',
        'bundle_b' => 'field_media_1',
        'bundle_c' => 'field_media',
      ],
      0,
      [
        'field_image' => 'alt',
        'field_media' => 'description',
      ],
    );
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::__construct()
   */
  public function testInvalidFieldPropExpressionDueToOnlyNullFieldPropNames(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('At least one of the field names must have a field property specified; otherwise it should be omitted (␀ can only be used when a subset of the bundles does not provide a certain value).');
    new FieldPropExpression(
      BetterEntityDataDefinition::create('node', ['bundle_a', 'bundle_b']),
      [
        'bundle_a' => 'field_image',
        'bundle_b' => 'field_media_1',
      ],
      0,
      [
        'field_image' => '␀',
        'field_media_1' => '␀',
      ],
    );
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::__construct()
   */
  public function testInvalidFieldPropExpressionDueToDuplicateBundles(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Duplicate bundles are nonsensical.');
    new FieldPropExpression(
      BetterEntityDataDefinition::create('node', ['foo', 'bar', 'foo']),
      [],
      0,
      'alt',
    );
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression::__construct()
   * @testWith [{"foo": "field_media_image", "bar": "field_media_image_1", "baz": "field_media_image_2"}]
   *           [{"foo": "field_media_image", "baz": "field_media_image_2"}]
   *           [{}]
   *           [{"foo": "field_media_image", "bar": "field_media_image_1"}]
   */
  public function testInvalidFieldPropExpressionDueToFieldNameMismatch(array $field_name): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('A field name must be specified for every bundle, and in the same order.');
    new FieldPropExpression(
      BetterEntityDataDefinition::create('node', ['foo', 'bar']),
      $field_name,
      0,
      'alt',
    );
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression::__construct()
   */
  public function testInvalidFieldObjectPropsExpressionDueToPropName(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('`ℹ︎␜entity:node␝title␞0␟value` is not a valid expression, because it does not map the same field item (entity type `entity:node`, field name `field_image`, delta `0`).');
    new FieldObjectPropsExpression(BetterEntityDataDefinition::create('node'), 'field_image', 0, [
      'label' => new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'title', 0, 'value'),
    ]);
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression::__construct()
   */
  public function testInvalidFieldObjectPropsExpressionDueToDelta(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('`ℹ︎␜entity:node␝title␞␟value` is not a valid expression, because it does not map the same field item (entity type `entity:node`, field name `title`, delta `0`).');
    new FieldObjectPropsExpression(BetterEntityDataDefinition::create('node'), 'title', 0, [
      'label' => new FieldPropExpression(BetterEntityDataDefinition::create('node'), 'title', NULL, 'value'),
    ]);
  }

  /**
   * @covers \Drupal\canvas\PropExpressions\StructuredData\FieldObjectPropsExpression::__construct()
   */
  public function testInvalidFieldObjectPropsExpressionInsideReferenceFieldTypeExpression(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('`ℹ︎␜entity:file␝bytes␞0␟value` is not a valid expression, because it does not map the same field item (entity type `entity:file`, field name `uri`, delta `0`).');

    // @phpstan-ignore-next-line new.resultUnused
    new ReferenceFieldTypePropExpression(
      new FieldTypePropExpression('image', 'entity'),
      new FieldObjectPropsExpression(
        BetterEntityDataDefinition::create('file'),
        'uri',
        0,
        [
          'src' => new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'uri', 0, 'value'),
          'bytes' => new FieldPropExpression(BetterEntityDataDefinition::create('file'), 'bytes', 0, 'value'),
        ]
      )
    );
  }

}
