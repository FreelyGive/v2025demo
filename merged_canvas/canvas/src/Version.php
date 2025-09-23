<?php

declare(strict_types=1);

namespace Drupal\canvas;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * @internal
 * @see \Drupal\canvas\Hook\LibraryHooks::libraryInfoAlter()
 */
class Version {

  private ?string $version = NULL;

  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
  ) {}

  public function getVersion(): string {
    if ($this->version === NULL) {
      $package_file_name = $this->moduleExtensionList->getPath('canvas') . '/ui/package.json';
      assert(file_exists($package_file_name));
      $package_file_contents = file_get_contents($package_file_name);
      assert(is_string($package_file_contents));
      $package_file_json = Json::decode($package_file_contents);
      assert(is_array($package_file_json) && isset($package_file_json['version']));
      $this->version = $package_file_json['version'];
    }
    return $this->version;
  }

}
