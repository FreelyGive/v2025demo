<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Drupal\Tests\canvas\TestSite\CanvasTestSetup;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversClass \Drupal\canvas\Form\ComponentInstanceForm
 * @covers \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::buildConfigurationForm()
 * @group canvas
 */
final class ComponentInstanceFormTest extends ApiLayoutControllerTestBase {

  use CiModulePathTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('module_installer')->install(['system', 'canvas_test_sdc']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'stark')->save();

    (new CanvasTestSetup())->setup();
    $this->setUpCurrentUser(permissions: ['edit any article content', 'administer themes']);
  }

  #[DataProvider('providerOptionalImages')]
  public function testOptionalImageAndHeading(string $component, array $values_to_set, array $expected_form_canvas_props): void {
    $response = $this->parentRequest(Request::create('/canvas/api/v0/config/component'))->getContent();
    self::assertIsString($response);
    // @see RenderSafeComponentContainer::handleComponentException()
    self::assertStringNotContainsString('Component failed to render', $response, 'Component failed to render');
    self::assertStringNotContainsString('something went wrong', $response);

    // Fetch the client-side info.
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::getClientSideInfo()
    $client_side_info_prop_sources = json_decode($response, TRUE)[$component]['propSources'];

    // Perform the same transformation the Canvas UI does in JavaScript to construct
    // the `form_canvas_props` request parameter expected by ComponentInstanceForm.
    // @see \Drupal\canvas\Form\ComponentInstanceForm::buildForm()
    // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase::buildConfigurationForm()
    $actual_form_canvas_props = [
      // Used by client to render previews.
      'resolved' => [],
      // Used by client to provider server with metadata on how to construct an
      // input UX.
      'source' => [],
    ];
    foreach ($client_side_info_prop_sources as $sdc_prop_name => $prop_source) {
      $actual_form_canvas_props['resolved'][$sdc_prop_name] = $prop_source['default_values']['resolved'] ?? [];
      $actual_form_canvas_props['source'][$sdc_prop_name]['value'] = $prop_source['default_values']['source'] ?? [];
      $actual_form_canvas_props['source'][$sdc_prop_name] += array_intersect_key($prop_source, array_flip([
        'sourceType',
        'sourceTypeSettings',
        'expression',
      ]));
      if (array_key_exists($sdc_prop_name, $values_to_set)) {
        $actual_form_canvas_props['resolved'][$sdc_prop_name] = $values_to_set[$sdc_prop_name]['resolved'];
        $actual_form_canvas_props['source'][$sdc_prop_name]['value'] = $values_to_set[$sdc_prop_name]['source'];
      }
    }
    self::assertSame($expected_form_canvas_props, $actual_form_canvas_props);

    $component_entity = Component::load($component);
    \assert($component_entity instanceof ComponentInterface);
    $this->request(Request::create('/canvas/api/v0/form/component-instance/node/1', 'PATCH', [
      'form_canvas_tree' => json_encode([
        'nodeType' => 'component',
        'slots' => [],
        'type' => "$component@{$component_entity->getActiveVersion()}",
        'uuid' => '5f18db31-fa2f-4f4e-a377-dc0c6a0b7dc4',
      ], JSON_THROW_ON_ERROR),
      'form_canvas_props' => json_encode($expected_form_canvas_props, JSON_THROW_ON_ERROR),
      'form_canvas_selected' => '5f18db31-fa2f-4f4e-a377-dc0c6a0b7dc4',
    ]));
  }

  public static function providerOptionalImages(): array {
    return [
      'sdc.canvas_test_sdc.image-optional-without-example as in component list' => [
        'sdc.canvas_test_sdc.image-optional-without-example',
        [],
        [
          'resolved' => [
            'image' => [],
          ],
          'source' => [
            'image' => [
              'value' => [],
              'sourceType' => 'static:field_item:entity_reference',
              'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
              'sourceTypeSettings' => [
                'storage' => ['target_type' => 'media'],
                'instance' => [
                  'handler' => 'default:media',
                  'handler_settings' => [
                    'target_bundles' => ['image' => 'image'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'image-optional-with-example-and-additional-prop as in component list' => [
        'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop',
        [],
        [
          'resolved' => [
            'heading' => [],
            'image' => [
              'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-optional-with-example-and-additional-prop/gracie.jpg',
              'alt' => 'A good dog',
              'width' => 601,
              'height' => 402,
            ],
          ],
          'source' => [
            'heading' => [
              'value' => [],
              'sourceType' => 'static:field_item:string',
              'expression' => 'ℹ︎string␟value',
            ],
            'image' => [
              'value' => [],
              'sourceType' => 'static:field_item:entity_reference',
              'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
              'sourceTypeSettings' => [
                'storage' => ['target_type' => 'media'],
                'instance' => [
                  'handler' => 'default:media',
                  'handler_settings' => [
                    'target_bundles' => ['image' => 'image'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'image-optional-with-example-and-additional-prop with heading set by user' => [
        'sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop',
        [
          'heading' => [
            'resolved' => 'test',
            'source' => 'test',
          ],
        ],
        [
          'resolved' => [
            'heading' => 'test',
            'image' => [
              'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-optional-with-example-and-additional-prop/gracie.jpg',
              'alt' => 'A good dog',
              'width' => 601,
              'height' => 402,
            ],
          ],
          'source' => [
            'heading' => [
              'value' => 'test',
              'sourceType' => 'static:field_item:string',
              'expression' => 'ℹ︎string␟value',
            ],
            'image' => [
              'value' => [],
              'sourceType' => 'static:field_item:entity_reference',
              'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
              'sourceTypeSettings' => [
                'storage' => ['target_type' => 'media'],
                'instance' => [
                  'handler' => 'default:media',
                  'handler_settings' => [
                    'target_bundles' => ['image' => 'image'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'image-gallery as in component list' => [
        'sdc.canvas_test_sdc.image-gallery',
        [],
        [
          'resolved' => [
            'caption' => [],
            'images' => [
              0 => [
                'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg',
                'alt' => 'A good dog',
                'width' => 601,
                'height' => 402,
              ],
              1 => [
                'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg',
                'alt' => 'Still a good dog',
                'width' => 601,
                'height' => 402,
              ],
              2 => [
                'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/UPPERCASE-GRACIE.JPG',
                'alt' => 'THE BEST DOG!',
                'width' => 601,
                'height' => 402,
              ],
            ],
          ],
          'source' => [
            'caption' => [
              'value' => [],
              'sourceType' => 'static:field_item:string',
              'expression' => 'ℹ︎string␟value',
            ],
            'images' => [
              'value' => [],
              'sourceType' => 'static:field_item:entity_reference',
              'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
              'sourceTypeSettings' => [
                'storage' => ['target_type' => 'media'],
                'instance' => [
                  'handler' => 'default:media',
                  'handler_settings' => [
                    'target_bundles' => ['image' => 'image'],
                  ],
                ],
                'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
              ],
            ],
          ],
        ],
      ],
      'image-gallery with caption set by user' => [
        'sdc.canvas_test_sdc.image-gallery',
        [
          'caption' => [
            'resolved' => 'Delightful dogs!',
            'source' => 'Delightful dogs!',
          ],
        ],
        [
          'resolved' => [
            'caption' => 'Delightful dogs!',
            'images' => [
              0 => [
                'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg',
                'alt' => 'A good dog',
                'width' => 601,
                'height' => 402,
              ],
              1 => [
                'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/gracie.jpg',
                'alt' => 'Still a good dog',
                'width' => 601,
                'height' => 402,
              ],
              2 => [
                'src' => self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/image-gallery/UPPERCASE-GRACIE.JPG',
                'alt' => 'THE BEST DOG!',
                'width' => 601,
                'height' => 402,
              ],
            ],
          ],
          'source' => [
            'caption' => [
              'value' => 'Delightful dogs!',
              'sourceType' => 'static:field_item:string',
              'expression' => 'ℹ︎string␟value',
            ],
            'images' => [
              'value' => [],
              'sourceType' => 'static:field_item:entity_reference',
              'expression' => 'ℹ︎entity_reference␟{src↝entity␜␜entity:media:image␝field_media_image␞␟src_with_alternate_widths,alt↝entity␜␜entity:media:image␝field_media_image␞␟alt,width↝entity␜␜entity:media:image␝field_media_image␞␟width,height↝entity␜␜entity:media:image␝field_media_image␞␟height}',
              'sourceTypeSettings' => [
                'storage' => ['target_type' => 'media'],
                'instance' => [
                  'handler' => 'default:media',
                  'handler_settings' => [
                    'target_bundles' => ['image' => 'image'],
                  ],
                ],
                'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
              ],
            ],
          ],
        ],
      ],
    ];
  }

}

