<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_cms_helper\Functional;

use Drupal\drupal_cms_helper\Hook\EntityHooks;
use Drupal\drupal_cms_helper\UrlField;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

#[Group('drupal_cms_helper')]
#[CoversMethod(EntityHooks::class, 'entityBaseFieldInfo')]
#[CoversClass(UrlField::class)]
final class UrlFieldTest extends BrowserTestBase {

  protected $defaultTheme = 'stark';

  protected static $modules = ['node', 'drupal_cms_helper'];

  public function test(): void {
    $node_type = $this->drupalCreateContentType()->id();
    $node = $this->drupalCreateNode(['type' => $node_type]);
    $this->assertSame($node->toUrl()->toString(), $node->url->value);
  }

}
