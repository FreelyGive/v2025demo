<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\Traits;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\canvas\Version;

trait CacheBustingTrait {

  protected function setCacheBustingQueryString(ContainerInterface $container, string $queryString): void {
    $mockVersion = new MockVersion($container->get(ModuleExtensionList::class), $queryString);
    $container->set(Version::class, $mockVersion);
  }

}

/**
 * @phpstan-ignore-next-line classExtendsInternalClass.classExtendsInternalClass
 */
class MockVersion extends Version {

  public function __construct(ModuleExtensionList $moduleExtensionList, protected string $queryString) {
    parent::__construct($moduleExtensionList);
  }

  public function getVersion(): string {
    return $this->queryString;
  }

}
