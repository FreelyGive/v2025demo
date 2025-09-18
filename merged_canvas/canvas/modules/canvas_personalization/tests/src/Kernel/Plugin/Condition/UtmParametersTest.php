<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Kernel\Plugin\Condition;

use Drupal\Core\Condition\ConditionManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\canvas_personalization\Plugin\Condition\UtmParameters;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @group canvas
 * @group canvas_personalization
 */
class UtmParametersTest extends KernelTestBase {

  protected static $modules = [
    'canvas',
    'canvas_personalization',
  ];

  /**
   * @dataProvider providerSegments
   */
  public function testConditionApplies(array $configuration, bool $matches): void {
    // Apparently we need a session for creating a request.
    $request = new Request(['utm_id' => 'chocolate', 'utm_campaign' => 'HALLOWEEN', 'custom_param' => 'Jim+Morrison']);
    $request->setSession(new Session());
    $this->container->set('request_stack', new RequestStack([$request]));

    $condition_manager = \Drupal::service('plugin.manager.condition');
    assert($condition_manager instanceof ConditionManager);
    $condition = $condition_manager->createInstance(UtmParameters::PLUGIN_ID, $configuration);
    assert($condition instanceof UtmParameters);
    $this->assertSame($matches, $condition->evaluate());
  }

  public static function providerSegments(): \Generator {
    // @todo Improve coverage here in https://www.drupal.org/i/3527075
    yield 'a non matching condition does not match' => [
      [
        'id' => UtmParameters::PLUGIN_ID,
        'parameters' => [
          [
            'key' => UtmParameters::UTM_CAMPAIGN,
            'value' => 'my-campaign',
            'matching' => 'exact',
          ],
          [
            'key' => 'a-custom-one',
            'value' => 'my%20custom%20value',
            'matching' => 'exact',
          ],
        ],
        'all' => FALSE,
        'negate' => FALSE,
      ],
      FALSE,
    ];
    yield 'a negated non matching condition does match' => [
      [
        'id' => UtmParameters::PLUGIN_ID,
        'parameters' => [
          [
            'key' => UtmParameters::UTM_CAMPAIGN,
            'value' => 'my-campaign',
            'matching' => 'exact',
          ],
        ],
        'all' => FALSE,
        'negate' => TRUE,
      ],
      TRUE,
    ];
    yield 'an exact matching single condition' => [
      [
        'id' => UtmParameters::PLUGIN_ID,
        'parameters' => [
          [
            'key' => UtmParameters::UTM_CAMPAIGN,
            'value' => 'HALLOWEEN',
            'matching' => 'exact',
          ],
        ],
        'all' => TRUE,
        'negate' => FALSE,
      ],
      TRUE,
    ];
    yield 'a partial matching single condition' => [
      [
        'id' => UtmParameters::PLUGIN_ID,
        'parameters' => [
          [
            'key' => UtmParameters::UTM_CAMPAIGN,
            'value' => 'HALLO',
            'matching' => 'starts_with',
          ],
        ],
        'all' => TRUE,
        'negate' => FALSE,
      ],
      TRUE,
    ];

  }

}
