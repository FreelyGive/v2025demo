const testMediaLibraryInComponentInstanceForm = (
  cy,
  entityType = 'canvas_page',
) => {
  const iterations = [
    {
      removeText: 'Remove The bones are their money',
      selectNewText: 'Select Sorry I resemble a dog',
      removeAriaLabel: 'Remove Sorry I resemble a dog',
      expectedAlt: 'My barber may have been looking at a picture of a dog',
    },
    {
      removeText: 'Remove Sorry I resemble a dog',
      selectNewText: 'Select The bones are their money',
      removeAriaLabel: 'Remove The bones are their money',
      expectedAlt: 'The bones equal dollars',
    },
    {
      removeText: 'Remove The bones are their money',
      selectNewText: 'Select Sorry I resemble a dog',
      removeAriaLabel: 'Remove Sorry I resemble a dog',
      expectedAlt: 'My barber may have been looking at a picture of a dog',
    },
  ];
  cy.get('div[role="dialog"]', { timeout: 20000 }).should('exist');
  cy.findByLabelText('Select The bones are their money').should(
    'not.be.checked',
  );
  cy.findByLabelText('Select The bones are their money').check();
  cy.get('button:contains("Insert selected")').click();
  cy.waitForAjax();
  cy.get('div[role="dialog"]').should('not.exist');
  cy.get(
    '[class*="contextualPanel"] input[aria-label="Remove The bones are their money"]',
  ).should('exist');
  cy.get(
    '[class*="contextualPanel"] article .js-media-library-item-preview img[alt="The bones equal dollars"]',
  ).should('exist');
  cy.waitForElementInIframe('img[alt="The bones equal dollars"]');

  // Use the Media Library widget an additional time. This effectively
  // confirms that CanvasTemplateRenderer is not loading JS assets that already
  // exist on the page. Click to the second image to change the form, then
  // click back again.
  cy.clickComponentInPreview('Test SDC Image', 1);
  cy.waitForAjax();
  cy.get('.js-media-library-item-preview img').should('not.exist');
  cy.clickComponentInPreview('Test SDC Image');
  cy.get('.js-media-library-item-preview img').should('exist');
  cy.waitForAjax();

  cy.get('[data-testid*="canvas-component-form-"]').as('inputForm');
  cy.get('@inputForm').recordFormBuildId();
  cy.intercept('PATCH', '**/canvas/api/v0/form/component-instance/**').as(
    'patch',
  );

  iterations.forEach((step, index) => {
    // The image location in the preview is different depending on the entity
    // type.
    const defaultPlaceholder =
      entityType === 'canvas_page'
        ? `[id^="block-"] > img[alt="Boring placeholder"][src$="components/image/600x400.png"]:first-of-type`
        : `.column-one > img[alt="Boring placeholder"][src$="components/image/600x400.png"]`;
    cy.log(
      `Iteration ${index + 1}: start ${index % 2 === 0 ? iterations[1].expectedAlt : iterations[0].expectedAlt}`,
    );
    cy.get('[class*="contextualPanel"]').should('exist');
    cy.get('div[role="dialog"]').should('not.exist');
    const removeIt = `[class*="contextualPanel"] .js-media-library-selection  [aria-label="${step.removeText}"][data-once="drupal-ajax"]`;

    cy.get(removeIt).realClick({ force: true });
    cy.waitForAjax();

    cy.log(
      `Confirm removing a required image in step ${index + 1} results in the example appearing in the preview.`,
    );

    // The default image should appear because the prop is required.
    cy.waitForElementInIframe(defaultPlaceholder);

    // Waiting for the build id does not work - it does not update.
    // Waiting for the preview (the last request after clicking remove) does not
    // appear to work reliably either. Hence, the fixed wait. Presumably there is
    // something that can be waited on, but it is not clear what.
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000);
    const addIt = `[class*="contextualPanel"] .js-media-library-widget .js-media-library-open-button[data-once="drupal-ajax"]`;
    cy.get(addIt).first().click({ force: true });
    cy.waitForAjax();

    cy.get('div[role="dialog"]').should('exist');
    cy.findByLabelText(step.selectNewText).check();
    cy.get('button:contains("Insert selected")').realClick({ force: true });
    cy.waitForAjax();
    cy.wait('@patch');
    cy.get('div[role="dialog"]').should('not.exist');
    cy.get('@inputForm').shouldHaveUpdatedFormBuildId({ timeout: 11000 });
    cy.get(
      `[class*="contextualPanel"] input[aria-label="${step.removeAriaLabel}"]`,
    ).should('exist');
    cy.get(
      `[class*="contextualPanel"] article .js-media-library-item-preview img[alt="${step.expectedAlt}"]`,
    ).should('exist');
    cy.waitForElementInIframe(`img[alt="${step.expectedAlt}"]`);
  });

  const lastStep = iterations.pop();

  // Switch back to entity edit form.
  cy.findByTestId('canvas-contextual-panel--page-data').click();
  // Then back to the component.
  cy.findByTestId('canvas-contextual-panel--settings').click();
  // Media entity value should persist.
  cy.get(
    `[class*="contextualPanel"] input[aria-label="${lastStep.removeAriaLabel}"]`,
  ).should('exist');
  cy.get(
    `[class*="contextualPanel"] article .js-media-library-item-preview img[alt="${lastStep.expectedAlt}"]`,
  ).should('exist');
  cy.waitForElementInIframe(`img[alt="${lastStep.expectedAlt}"]`);

  // Switch to full screen preview.
  cy.findByText('Preview').click();
  cy.findByText('Exit Preview').click();
  cy.clickComponentInPreview('Test SDC Image');
  // Media entity value should persist.
  cy.get(
    `[class*="contextualPanel"] input[aria-label="${lastStep.removeAriaLabel}"]`,
  ).should('exist');
  cy.get(
    `[class*="contextualPanel"] article .js-media-library-item-preview img[alt="${lastStep.expectedAlt}"]`,
  ).should('exist');
  cy.waitForElementInIframe(`img[alt="${lastStep.expectedAlt}"]`);
};

describe('Media Library component instance', () => {
  beforeEach(() => {
    cy.drupalCanvasInstall();
    cy.drupalSession();
    // A larger viewport makes it easier to debug in the test runner app.
    cy.viewport(2000, 1000);
  });

  afterEach(() => {
    cy.drupalUninstall();
  });

  it('Can open the media library widget in an article props form', () => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded();
    cy.getComponentInPreview('Test SDC Image', 0);

    cy.findByTestId('canvas-contextual-panel--page-data').should(
      'have.attr',
      'data-state',
      'active',
    );

    // There are two images here, the second one is making use of an image
    // adapter which we don't support yet. We have to use the first one instead.
    cy.clickComponentInPreview('Test SDC Image', 0);

    cy.findByTestId('canvas-contextual-panel--settings').should(
      'have.attr',
      'data-state',
      'active',
    );

    cy.get('div[role="dialog"]').should('not.exist');
    // Click the remove button to reveal the open button.
    cy.get(`[class*="contextualPanel"]`)
      .findByLabelText('Remove Hero image')
      .click();
    cy.get(
      '[class*="contextualPanel"] .js-media-library-open-button[data-once="drupal-ajax"]',
    )
      .first()
      .click();
    testMediaLibraryInComponentInstanceForm(cy, 'article');
  });

  it(
    'Can open the media library widget in an canvas_page props form',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.drupalLogin('canvasUser', 'canvasUser');
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/2' });
      cy.openLibraryPanel();
      cy.get('.primaryPanelContent').findByText('Test SDC Image').click();

      cy.get('.primaryPanelContent').findByText('Test SDC Image').click();
      cy.get(
        '.previewOverlay [data-canvas-component-id="sdc.canvas_test_sdc.image"]',
      ).should('have.length', 2);
      cy.clickComponentInPreview('Test SDC Image', 0);
      cy.waitForAjax();

      cy.get(
        '[class*="contextualPanel"] .js-media-library-open-button[data-once="drupal-ajax"]',
      )
        .first()
        .click();

      cy.waitForAjax();
      testMediaLibraryInComponentInstanceForm(cy, 'canvas_page');
    },
  );
});
