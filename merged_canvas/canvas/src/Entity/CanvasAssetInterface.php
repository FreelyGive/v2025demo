<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

/**
 * @see \Drupal\canvas\AssetManager
 * @internal This interface must be implemented by any Drupal Canvas config
 *   entity that manages assets in the public file system.
 */
interface CanvasAssetInterface extends CanvasHttpApiEligibleConfigEntityInterface {

  public function hasCss(): bool;

  public function hasJs(): bool;

  public function getJs(): string;

  public function getCss(): string;

  public function getJsPath(): string;

  public function getCssPath(): string;

  /**
   * The (generated) asset library for this config entity.
   *
   * @param bool $isPreview
   *   Whether this asset library will be used for a preview or not; allows
   *   returning a different asset library in previews.
   *
   * @return string
   *   An asset library.
   */
  public function getAssetLibrary(bool $isPreview): string;

  /**
   * The (computed) asset library dependencies for this config entity.
   *
   * @return string[]
   *   Asset libraries this asset depends upon.
   */
  public function getAssetLibraryDependencies(): array;

}
