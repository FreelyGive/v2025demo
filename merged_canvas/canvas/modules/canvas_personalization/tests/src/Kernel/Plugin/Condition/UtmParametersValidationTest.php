<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Kernel\Plugin\Condition;

use Drupal\Tests\canvas_personalization\Kernel\Config\SegmentValidationTest;
use Drupal\canvas_personalization\Entity\Segment;
use Drupal\canvas_personalization\Plugin\Condition\UtmParameters;

/**
 * @group canvas
 * @group canvas_personalization
 */
final class UtmParametersValidationTest extends SegmentValidationTest {

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('segment');
    $this->entity = Segment::create([
      'id' => 'test_utm_params_segment',
      'label' => 'Test UTM params segment',
      'description' => 'Test UTM params segment description',
      'status' => TRUE,
      'rules' => [
        UtmParameters::PLUGIN_ID => [
          'id' => UtmParameters::PLUGIN_ID,
          'parameters' => [
            [
              'key' => UtmParameters::UTM_CAMPAIGN,
              'value' => 'my-campaign',
              'matching' => 'exact',
            ],
          ],
          'all' => TRUE,
          'negate' => FALSE,
        ],
      ],
    ]);
    $this->entity->save();
  }

  public static function providerSegmentsDependencies(): \Generator {
    yield 'a module provided plugin' => [
      [
        UtmParameters::PLUGIN_ID => [
          'id' => UtmParameters::PLUGIN_ID,
          'parameters' => [
            [
              'key' => UtmParameters::UTM_CAMPAIGN,
              'value' => 'my-campaign',
              'matching' => 'starts_with',
            ],
            [
              'key' => 'a-custom-one',
              'value' => 'my%20custom%20value',
              'matching' => 'exact',
            ],
          ],
          'all' => TRUE,
          'negate' => FALSE,
        ],
      ],
      [],
    ];

  }

}
