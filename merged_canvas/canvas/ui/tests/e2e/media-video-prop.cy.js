describe('Media Library', () => {
  before(() => {
    cy.drupalCanvasInstall(['canvas_test_video_fixture']);
  });

  beforeEach(() => {
    cy.drupalSession();
    // A larger viewport makes it easier to debug in the test runner app.
    cy.viewport(2000, 1000);
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Can use a video component that uses the media library widget', () => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/2' });
    cy.insertComponent({ name: 'Video' });
    cy.insertComponent({ name: 'Video' });
    cy.waitForElementInIframe('video:nth-of-type(2)');
    cy.clickComponentInPreview('Video', 0);
    cy.waitForAjax();
    cy.get('[data-testid*="canvas-component-form-"]').as('inputForm');
    cy.get('@inputForm').recordFormBuildId();
    cy.get(
      '[class*="contextualPanel"] .js-media-library-open-button[data-once="drupal-ajax"]',
    )
      .first()
      .click();
    cy.get('.media-library-item__name').should('have.length', 2);
    cy.findByLabelText('Select Duck Landscape').check();
    cy.get('button:contains("Insert selected")').realClick({ force: true });
    cy.waitForAjax();
    cy.get('div[role="dialog"]').should('not.exist');
    cy.waitForAjax();
    cy.get('@inputForm').shouldHaveUpdatedFormBuildId({ timeout: 11000 });
    cy.get('[aria-label="Remove Duck Landscape"]').should('exist');
    cy.waitForElementInIframe('video > source[src*="duck.mp4"]');

    // @todo Click the `Remove` button (with the `Remove Duck Landscape` ARIA label)
    // @todo assert that that the preview updates to again show the example

    // @todo Re-select the "Duck Landscape" video", now the rest of the test suite should continue to work as-is.

    cy.findByLabelText('Display width').clear();
    cy.findByLabelText('Display width').type(190);
    cy.findByLabelText('Display width').trigger('change');
    cy.get('[aria-label="Video"]')
      .first()
      .should(($videoBox) => {
        const videoRect = $videoBox[0].getBoundingClientRect();
        expect(videoRect.width).to.be.closeTo(190, 1);
      });
    cy.waitForElementInIframe('video[width="190"] > source[src*="duck.mp4"]');
    cy.findByLabelText('Display width').clear();
    cy.findByLabelText('Display width').type(500);
    cy.findByLabelText('Display width').trigger('change');
    cy.waitForElementInIframe('video[width="500"] > source[src*="duck.mp4"]');
    cy.get('[aria-label="Video"]')
      .first()
      .should(($videoBox) => {
        const videoRect = $videoBox[0].getBoundingClientRect();
        expect(videoRect.width).to.be.closeTo(500, 1);
      });
    cy.clickComponentInPreview('Video', 1);
    cy.waitForAjax();
    cy.get(
      '[class*="contextualPanel"] .js-media-library-open-button[data-once="drupal-ajax"]',
    )
      .first()
      .click();
    cy.get('.media-library-item__name').should('have.length', 2);
    cy.findByLabelText('Select Waterfall Portrait').check();
    cy.get('button:contains("Insert selected")').realClick({ force: true });
    cy.waitForAjax();
    cy.get('div[role="dialog"]').should('not.exist');
    cy.waitForAjax();
    cy.waitForElementInIframe('video > source[src*="waterfall.mp4"]');
    cy.get('[aria-label="Remove Waterfall Portrait"]').should('exist');
    cy.get('[aria-label="Remove Duck Landscape"]').should('not.exist');
    cy.clickComponentInPreview('Video', 0);
    cy.get('[aria-label="Remove Waterfall Portrait"]').should('not.exist');
    cy.get('[aria-label="Remove Duck Landscape"]').should('exist');
  });

  it('Can handle not immediately having a value', () => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/2' });
    cy.insertComponent({ name: 'Video' });
    cy.waitForElementInIframe('video > source');
    cy.findByLabelText('Display width').clear();
    cy.intercept({
      url: '**/canvas/api/v0/layout/canvas_page/2',
      times: 1,
      method: 'PATCH',
    }).as('updatePreview');
    cy.findByLabelText('Display width').type(190);
    cy.findByLabelText('Display width').trigger('change');
    // Make sure the preview has been updated with the new width.
    cy.wait('@updatePreview');
    cy.waitForElementInIframe('video > source');
    // Components that fail to render have this data attribute.
    cy.waitForElementNotInIframe('[data-component-uuid]');
  });
});
