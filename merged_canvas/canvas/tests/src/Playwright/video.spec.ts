import { readFile } from 'fs/promises';
import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';
import { getModuleDir } from './utilities/DrupalFilesystem';

// cspell:ignore videomedia

test.describe('Video Component', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['canvas']);
      await page.close();
    },
  );

  test('Can use a generic file widget to populate a video prop', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Video Test', '/video-test');
    await page.goto('/video-test');
    await canvasEditor.goToEditor();
    let previewFrame;

    const moduleDir = await getModuleDir();
    const code = await readFile(
      `${moduleDir}/canvas/tests/fixtures/code_components/videos/Video.jsx`,
      'utf-8',
    );
    await canvasEditor.createCodeComponent('Video', code);
    await canvasEditor.addCodeComponentProp('video', 'Video', [
      {
        type: 'select',
        label: 'Example aspect ratio',
        value: '16:9 (Widescreen)',
      },
    ]);
    await canvasEditor.addCodeComponentProp('text', 'Text', [
      { type: 'text', label: 'Example value', value: 'Example Text' },
    ]);
    await canvasEditor.saveCodeComponent('js.video');
    await canvasEditor.addComponent({ id: 'js.video' });

    const formBuildId = await page
      .locator(
        'input[type="hidden"][data-form-id="component_instance_form"][name="form_build_id"]',
      )
      .getAttribute('value');

    // Check hardcoded default values.
    previewFrame = await canvasEditor.getActivePreviewFrame();
    expect(await previewFrame.locator('video').getAttribute('src')).toContain(
      '/ui/assets/videos/mountain_wide',
    );
    expect(
      await previewFrame.locator('video').getAttribute('poster'),
    ).toContain('https://placehold.co/1920x1080.png?text=Widescreen');

    await canvasEditor.editComponentProp(
      'video',
      // @todo move this to tests/fixtures/videos
      '../../../../ui/assets/videos/mountain_wide.mp4',
      'file',
    );
    previewFrame = await canvasEditor.getActivePreviewFrame();
    await expect(previewFrame.locator('video')).not.toHaveAttribute('poster');

    // Click the remove button to remove the video.
    // @todo Regular .click() doesn't work for some reason.
    await page.evaluate(() => {
      const button = document.querySelector(
        '[data-drupal-selector^="edit-canvas-component-props-"][data-drupal-selector$="-video-0-remove-button"]',
      );

      ['mousedown', 'mouseup', 'click'].forEach((eventType) => {
        button.dispatchEvent(
          new MouseEvent(eventType, {
            bubbles: true,
            cancelable: true,
            view: window,
            button: 0,
          }),
        );
      });
    });
    await expect(
      page.locator(
        '[data-drupal-selector^="edit-canvas-component-props-"][data-drupal-selector$="-video-0-remove-button"]',
      ),
    ).not.toBeVisible();

    // Back to the default, which has a poster image.
    previewFrame = await canvasEditor.getActivePreviewFrame();
    await expect(previewFrame.locator('video')).toHaveAttribute('poster');

    // Add a different video
    await canvasEditor.editComponentProp(
      'video',
      // @todo move this to tests/fixtures/videos
      '../../../../ui/assets/videos/bird_vertical.mp4',
      'file',
    );
    await expect(
      page.locator(
        '[data-drupal-selector^="edit-canvas-component-props-"][data-drupal-selector$="-video-0-remove-button"]',
      ),
    ).toBeVisible();

    // Check the form build id was changed.
    expect(
      await page
        .locator(
          'input[type="hidden"][data-form-id="component_instance_form"][name="form_build_id"]',
        )
        .getAttribute('value'),
    ).not.toEqual(formBuildId);

    previewFrame = await canvasEditor.getActivePreviewFrame();
    await expect(previewFrame.locator('video')).not.toHaveAttribute('poster');
    expect(await previewFrame.locator('video').getAttribute('src')).toContain(
      'bird_vertical',
    );
  });

  test('Can use media to populate a video prop', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.applyRecipe('core/recipes/local_video_media_type');
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Video Media Test', '/video-media-test');
    await page.goto('/video-media-test');
    await canvasEditor.goToEditor();

    // Add the component again. The previous one can't be reused because it needs
    // resaving in order for the media widget to kick in.
    // Also, if test retries occur then we can't assume that the test above has run.
    const moduleDir = await getModuleDir();
    const code = await readFile(
      `${moduleDir}/canvas/tests/fixtures/code_components/videos/Video.jsx`,
      'utf-8',
    );
    await canvasEditor.createCodeComponent('VideoMedia', code);
    await canvasEditor.addCodeComponentProp('video', 'Video', [
      {
        type: 'select',
        label: 'Example aspect ratio',
        value: '16:9 (Widescreen)',
      },
    ]);
    await canvasEditor.addCodeComponentProp('text', 'Text', [
      { type: 'text', label: 'Example value', value: 'Example Text' },
    ]);
    await canvasEditor.saveCodeComponent('js.videomedia');
    await canvasEditor.addComponent({ id: 'js.videomedia' });

    await drupal.addMediaGenericFile(
      '../../../../ui/assets/videos/bird_vertical.mp4',
    );
    const previewFrame = await canvasEditor.getActivePreviewFrame();
    await expect(previewFrame.locator('video')).not.toHaveAttribute('poster');
    expect(await previewFrame.locator('video').getAttribute('src')).toContain(
      'bird_vertical',
    );
  });
});
