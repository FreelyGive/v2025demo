<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Unit\TypedData;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Url;
use Drupal\canvas\TypedData\LinkUrl;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\canvas\Plugin\DataType\ComponentInputs
 * @see \Drupal\Tests\canvas\Kernel\DataType\ComponentInputsDependenciesTest
 * @group canvas
 */
class LinkUrlTest extends UnitTestCase {

  /**
   * @covers ::getValue
   * @dataProvider providerValues
   */
  public function testGetValue(string $uri, string $expected): void {
    $data = $this->createMock(TypedDataInterface::class);
    $data->expects($this->once())
      ->method('getValue')
      ->willReturn($uri);

    $url = $this->createMock(Url::class);
    $url->expects($this->any())
      ->method('toString')
      ->willReturn($expected);

    $item = $this->createMock(LinkItemInterface::class);
    $item->expects($this->any())
      ->method('set')
      ->with('uri', $expected);
    $item->expects($this->once())
      ->method('get')
      ->with('uri')
      ->willReturn($data);
    $item->expects($this->any())
      ->method('getUrl')
      ->willReturn($url);

    $link_url = new LinkUrl(
      $this->prophesize(DataDefinitionInterface::class)->reveal(),
      NULL,
      $item
    );

    // Test getting values for a existing UUID.
    $this->assertEquals(
      $expected,
      $link_url->getValue(),
    );
  }

  public static function providerValues(): array {
    return [
      ['<nolink>', 'route:<nolink>'],
      ['<none>', 'route:<none>'],
      ['<button>', 'route:<button>'],
      ['<front>', 'internal:/'],
      ['<front>?query=string#fragment', 'internal:/?query=string#fragment'],
      ['/node/1', 'internal:/node/1'],
      ['/node/1?query=string#fragment', 'internal:/node/1?query=string#fragment'],
      ['?query=string#fragment', '?query=string#fragment'],
      ['#fragment', '#fragment'],
    ];
  }

}
