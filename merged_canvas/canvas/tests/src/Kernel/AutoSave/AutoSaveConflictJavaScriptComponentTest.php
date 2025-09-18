<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Kernel\AutoSave;

use Drupal\canvas\Entity\JavaScriptComponent;

/**
 * Tests auto-save conflict handling for code components.
 *
 * @see \Drupal\canvas\Entity\JavaScriptComponent
 */
final class AutoSaveConflictJavaScriptComponentTest extends AutoSaveConflictConfigTestBase {

  protected string $updateKey = 'name';

  protected function setUpEntity(): void {
    $this->entity = JavaScriptComponent::createFromClientSide([
      'machineName' => 'test',
      'name' => 'Test Code Component',
      'status' => FALSE,
      'required' => [],
      'props' => [],
      'slots' => [],
      'sourceCodeJs' => '',
      'sourceCodeCss' => '',
      'compiledJs' => '',
      'compiledCss' => '',
      'importedJsComponents' => [],
      'dataDependencies' => [],
    ]);
    $this->entity->save();
  }

}
