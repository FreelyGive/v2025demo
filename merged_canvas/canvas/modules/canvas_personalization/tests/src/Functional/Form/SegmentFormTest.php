<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas_personalization\Functional\Form;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\canvas\Traits\ContribStrictConfigSchemaTestTrait;
use Drupal\canvas_personalization\Entity\Segment;
use Drupal\canvas_personalization\Plugin\Condition\UtmParameters;
use Symfony\Component\HttpFoundation\Response;

/**
 * Basic testing of the critical path for the segment forms.
 *
 * ⚠️ This is highly experimental and *will* be refactored or even removed.
 *
 * @todo Revisit in https://www.drupal.org/i/3527086
 *
 * @group canvas
 * @group canvas_personalization
 */
final class SegmentFormTest extends BrowserTestBase {

  use ContribStrictConfigSchemaTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'canvas',
    'user',
    'canvas_personalization',
    // @todo Remove once ComponentSourceInterface is a public API, i.e. after https://www.drupal.org/i/3520484#stable is done.
    'canvas_dev_mode',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Test callback.
   */
  public function testCreatingSegment(): void {
    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
      Segment::ADMIN_PERMISSION,
    ]);
    assert($admin_user instanceof AccountInterface);
    $this->drupalLogin($admin_user);
    $this->drupalGet('/admin/structure/segment/add');
    $this->assertSession()->elementExists('xpath', '//table[@id="rules-id"]//td[text() = "No rules added yet."]');
    $edit = [
      'id' => 'my_segment',
      'label' => 'My segment',
      'description' => 'My segment description',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->addressEquals('admin/structure/segment');
    $this->assertSession()->statusMessageContains('Created new personalization segment My segment.');

    $this->clickLink('Edit');
    $this->assertSession()->addressEquals('admin/structure/segment/my_segment');
    $this->clickLink('New segment rule');
    $edit = [
      'plugin_id' => 'current_theme',
      'settings[theme]' => 'stark',
      'settings[negate]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('Updated personalization segment My segment.');

    $this->clickLink('New segment rule');
    $edit = [
      'plugin_id' => 'user_role',
      'settings[roles][authenticated]' => TRUE,
      'settings[negate]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('Updated personalization segment My segment.');
    $this->assertSession()->addressEquals('admin/structure/segment/my_segment');
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "Current Theme");
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "User Role");

    $this->clickLink('New segment rule');
    $edit = [
      'plugin_id' => 'utm_parameters',
      'settings[parameters][_new_parameter][key]' => UtmParameters::CUSTOM,
      'settings[parameters][_new_parameter][custom_key]' => 'utm_author',
      'settings[parameters][_new_parameter][value]' => 'Jim Morrison',
      'settings[negate]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('Updated personalization segment My segment.');
    $this->assertSession()->addressEquals('admin/structure/segment/my_segment');
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "Current Theme");
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "User Role");
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "UTM Parameters");

    // As we cannot have repeated rules, verify the form doesn't fail
    // when none are available.
    $this->clickLink('New segment rule');
    $this->assertSession()->elementTextContains('xpath', '//form[contains(@class,"segment-add-rule-form-form")]', "No applicable conditions found.");

    // I can't delete a rule without a valid csrf token.
    $this->drupalGet('admin/structure/segment/my_segment/rule-delete/current_theme');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // If I delete a rule, I can re-add it.
    $this->drupalGet('admin/structure/segment/my_segment');
    $this->clickLink('Delete Current Theme');
    $this->assertSession()->elementTextNotContains('xpath', '//table[@id="rules-id"]', "Current Theme");
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "User Role");
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "UTM Parameters");

    $this->clickLink('New segment rule');
    $edit = [
      'plugin_id' => 'current_theme',
      'settings[theme]' => 'stark',
      'settings[negate]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('Updated personalization segment My segment.');
    $this->assertSession()->addressEquals('admin/structure/segment/my_segment');
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "Current Theme");
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "User Role");
    $this->assertSession()->elementTextContains('xpath', '//table[@id="rules-id"]', "UTM Parameters");

    $segment = Segment::load('my_segment');
    assert($segment instanceof Segment);
    $this->assertEquals([
      'user_role' => [
        'id' => 'user_role',
        'negate' => TRUE,
        'roles' => [
          'authenticated' => 'authenticated',
        ],
      ],
      'utm_parameters' => [
        'id' => 'utm_parameters',
        'negate' => FALSE,
        'all' => TRUE,
        'parameters' => [
          [
            'key' => 'utm_author',
            'value' => 'Jim%20Morrison',
            'matching' => 'exact',
          ],
        ],
      ],
      'current_theme' => [
        'id' => 'current_theme',
        'negate' => FALSE,
        'theme' => 'stark',
      ],
    ], $segment->get('rules'));

    $this->assertSame('current_theme', array_key_first($segment->getSegmentRules()));
  }

}
