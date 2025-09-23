<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\canvas\AutoSave\AutoSaveManager;
use Drupal\canvas\Entity\JavaScriptComponent;
use Drupal\Tests\canvas\Kernel\Traits\CiModulePathTrait;
use Symfony\Component\Yaml\Yaml;

trait CreateTestJsComponentTrait {

  use CiModulePathTrait;

  private function createMyCtaComponentFromSdc(): void {
    // Create a "code component" that has the same explicit inputs as the
    // `canvas_sdc_test:my-cta`.
    // @phpstan-ignore-next-line
    $sdc_yaml = Yaml::parseFile($this->root . self::getCiModulePath() . '/tests/modules/canvas_test_sdc/components/my-cta/my-cta.component.yml');
    $props = array_diff_key(
      $sdc_yaml['props']['properties'],
      // SDC has special infrastructure for a prop named "attributes".
      array_flip(['attributes']),
    );
    // @todo Consider supporting this in https://www.drupal.org/i/3514672
    unset($props['target']['default']);
    JavaScriptComponent::create([
      'uuid' => '83ba5c41-6d66-4e93-a55f-eb99702f5d5f',
      'machineName' => 'my-cta',
      'name' => 'My First Code Component',
      'status' => TRUE,
      'props' => $props,
      'required' => $sdc_yaml['props']['required'],
      'js' => ['original' => '', 'compiled' => ''],
      'css' => ['original' => '', 'compiled' => ''],
      'dataDependencies' => [],
    ])->save();
  }

  private function createMyCtaAutoSaveComponentFromSdc(): void {
    $my_cta = JavaScriptComponent::load('my-cta');
    if (!$my_cta) {
      throw new \LogicException();
    }
    $my_cta_with_auto_save = JavaScriptComponent::create([
      'uuid' => 'b4bc6c8f-66f7-458a-99a9-41c74b2801e7',
      'machineName' => 'my-cta-with-auto-save',
      'name' => 'My Code Component with Auto-Save',
      'dataDependencies' => [],
    ] + array_diff_key($my_cta->toArray(), array_flip(['uuid'])));
    $my_cta_with_auto_save->save();

    // Now also create an auto-save entry for it.
    $client_side_data = $my_cta_with_auto_save->normalizeForClientSide()->values;
    $client_side_data['name'] .= ' - Draft';
    // @phpstan-ignore-next-line
    $autoSave = $this->container->get(AutoSaveManager::class);
    // Add updated values the auto-save entry.
    $my_cta_with_auto_save->updateFromClientSide($client_side_data +
      [
        'importedJsComponents' => [],
      ]);
    $autoSave->saveEntity($my_cta_with_auto_save);
  }

  private function createTestCodeComponent(): void {
    // Create a simple test code component for testing the "Edit component" action
    JavaScriptComponent::create([
      'machineName' => 'test-code-component',
      'name' => 'Test Code Component',
      'status' => TRUE,
      'props' => [
        'heading' => [
          'title' => 'Heading',
          'type' => 'string',
          'examples' => ['Example Heading'],
        ],
        'content' => [
          'title' => 'Content',
          'type' => 'string',
          'examples' => ['Example Content'],
        ],
      ],
      'required' => [],
      'js' => [
        'original' => <<<JSX
export default function TestCodeComponent({ heading, content }) {
  return (
    <div>
      <h2>{heading}</h2>
      <p>{content}</p>
    </div>
  );
}
JSX,
        'compiled' => <<<JSX
import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
export default function TestCodeComponent({ heading, content }) {
    return /*#__PURE__*/ _jsxs("div", {
        children: [
            /*#__PURE__*/ _jsx("h2", {
                children: heading
            }),
            /*#__PURE__*/ _jsx("p", {
                children: content
            })
        ]
    });
}
JSX
      ],
      'css' => ['original' => '', 'compiled' => ''],
      'dataDependencies' => [],
    ])->save();
  }

}
