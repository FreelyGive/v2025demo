<?php

declare(strict_types=1);

namespace Drupal\canvas\Entity;

/**
 * @internal
 * @see \Drupal\canvas\Entity\Component::computeUiLibrary()
 */
enum LibraryEnum: string {
  case Elements = 'elements';
  case ExtensionComponents = 'extension_components';
  case DynamicComponents = 'dynamic_components';
  case PrimaryComponents = 'primary_components';
}
