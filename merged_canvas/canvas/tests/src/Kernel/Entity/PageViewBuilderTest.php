<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Entity;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\PageViewBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\canvas\Kernel\Traits\PageTrait;
use Drupal\Tests\canvas\Kernel\Traits\RequestTrait;
use Drupal\Tests\canvas\Traits\GenerateComponentConfigTrait;

/**
 * @group canvas
 */
final class PageViewBuilderTest extends KernelTestBase {

  use GenerateComponentConfigTrait;
  use PageTrait;
  use RequestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'block',
    'sdc',
    'sdc_test',
    'canvas_test_sdc',
    // Modules providing field types + widgets for the SDC Components'
    // `prop_field_definitions`.
    'file',
    'image',
    'options',
    'link',
    'system',
    ...self::PAGE_TEST_MODULES,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->generateComponentConfig();
    $this->installPageEntitySchema();

    $this->config('system.site')
      ->set('name', 'Canvas Test Site')
      ->set('slogan', 'Drupal Canvas Test Site')
      ->save();
  }

  public function testView(): void {
    $test_heading_text = $this->randomString();
    $sut = Page::create([
      'title' => 'Test page',
      'description' => 'This is a test page.',
      'path' => ['alias' => '/test-page'],
      'components' => [
        [
          'uuid' => '66e4c177-8e29-42a6-8373-b82eee2841c0',
          'component_id' => 'sdc.canvas_test_sdc.props-slots',
          'inputs' => [
            'heading' => $test_heading_text,
          ],
        ],
        [
          'uuid' => 'b1eba8d5-be93-4b11-9757-4493e685252c',
          'component_id' => 'block.system_branding_block',
          'inputs' => [
            'use_site_logo' => TRUE,
            'use_site_name' => TRUE,
            'use_site_slogan' => TRUE,
            'label_display' => FALSE,
            'label' => '',
          ],
        ],

      ],
      'canvas_test_field' => '3rd party based field should not be displayed!',
    ]);
    self::assertSaveWithoutViolations($sut);
    self::assertEquals(
      '3rd party based field should not be displayed!',
      $sut->canvas_test_field->value
    );

    $view_builder = $this->container->get('entity_type.manager')->getViewBuilder(Page::ENTITY_TYPE_ID);
    self::assertInstanceOf(PageViewBuilder::class, $view_builder);

    // Verify `canvas_test_field` is part of the display components, but then is not
    // rendered later.
    $build = [$sut->id() => []];
    $view_builder->buildComponents(
      $build,
      [$sut->id() => $sut],
      [Page::ENTITY_TYPE_ID => EntityViewDisplay::collectRenderDisplay($sut, 'default')],
      'default'
    );
    self::assertArrayHasKey('components', $build[$sut->id()]);
    self::assertArrayHasKey('canvas_test_field', $build[$sut->id()]);

    // Render the page and verify the expected output. The content of
    // `canvas_test_field` should not be rendered.
    $build = $view_builder->view($sut);
    $this->render($build);

    self::assertStringNotContainsString('Components', $this->getTextContent());
    self::assertStringNotContainsString($sut->description->value, $this->getTextContent());

    self::assertStringNotContainsString('Test field', $this->getTextContent());
    self::assertStringNotContainsString('3rd party based field should not be displayed!', $this->getTextContent());

    self::assertCount(1, $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"]'));
    self::assertCount(1, $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"] .component--props-slots--body'));
    self::assertCount(1, $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"] .component--props-slots--footer'));
    self::assertCount(1, $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"] .component--props-slots--colophon'));
    self::assertEquals(
      $test_heading_text,
      (string) $this->cssSelect('[data-component-id="canvas_test_sdc:props-slots"] h1')[0]
    );

    self::assertStringContainsString('<a href="/" rel="home">Canvas Test Site</a>', $this->getRawContent());
    self::assertStringContainsString('Drupal Canvas Test Site', $this->getTextContent());

    // Verify `canvas_test_page_canvas_page_view` output was ignored, but attachments
    // were allowed.
    self::assertArrayHasKey('canvas_test_page', $this->drupalSettings);
    self::assertEquals(['foo' => 'Bar'], $this->drupalSettings['canvas_test_page']);
    self::assertStringNotContainsString('canvas_test_page_canvas_page_view markup', $this->getRawContent());
  }

  public function testConfiguredViewDisplayNotAllowed(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Pages do not have configurable view displays. The view display is computed from base field definitions, to ensure there is never a need for an update path.');

    EntityViewDisplay::create([
      'targetEntityType' => Page::ENTITY_TYPE_ID,
      'bundle' => Page::ENTITY_TYPE_ID,
      'mode' => 'default',
      'status' => TRUE,
    ])->save();

    $sut = Page::create([
      'title' => 'Test page',
      'description' => 'This is a test page.',
      'path' => ['alias' => '/test-page'],
      'components' => [],
    ]);
    self::assertSaveWithoutViolations($sut);

    $view_builder = $this->container->get('entity_type.manager')->getViewBuilder(Page::ENTITY_TYPE_ID);
    $build = $view_builder->view($sut);
    $this->render($build);
  }

}
