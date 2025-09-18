describe('Media Library', () => {
  before(() => {
    cy.drupalCanvasInstall([
      'canvas_test_sdc',
      'canvas_test_e2e_code_components',
    ]);
  });

  beforeEach(() => {
    cy.drupalSession();
    // A larger viewport makes it easier to debug in the test runner app.
    cy.viewport(2000, 1000);
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Can remove an optional image no example and there is no image in the preview', () => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
    cy.openLibraryPanel();
    cy.get(
      '[data-canvas-component-id="sdc.canvas_test_sdc.image-optional-without-example"]',
    ).realClick();
    cy.waitForElementNotInIframe('.layout-content img');
    cy.get(
      '[class*="contextualPanel"] .js-media-library-open-button[data-once="drupal-ajax"]',
    )
      .first()
      .click();
    cy.get('div[role="dialog"]').should('exist');
    cy.findByLabelText('Select The bones are their money').check();
    cy.get('button:contains("Insert selected")').click();
    cy.get('div[role="dialog"]').should('not.exist');
    cy.waitForElementInIframe('img[alt="The bones equal dollars"]');
    cy.get('[class*="contextualPanel"]')
      .findByLabelText('Remove The bones are their money')
      .click();

    // Confirms the removed optional image prop is not rendered at all, vs the
    // example/default value reappearing.
    cy.waitForElementNotInIframe('.layout-content img');
  });

  it('Can remove an optional image with example and there is no image in the preview', () => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
    cy.openLibraryPanel();
    cy.get(
      '[data-canvas-component-id="sdc.canvas_test_sdc.image-optional-with-example"]',
    ).realClick();
    cy.waitForElementInIframe('.layout-content img[alt="Boring placeholder"]');
    cy.get(
      '[class*="contextualPanel"] .js-media-library-open-button[data-once="drupal-ajax"]',
    )
      .first()
      .click();
    cy.get('div[role="dialog"]').should('exist');
    cy.findByLabelText('Select The bones are their money').check();
    cy.get('button:contains("Insert selected")').click();
    cy.get('div[role="dialog"]').should('not.exist');
    cy.waitForElementInIframe('img[alt="The bones equal dollars"]');
    cy.get('[class*="contextualPanel"]')
      .findByLabelText('Remove The bones are their money')
      .click();

    // Confirms the removed optional image prop is not rendered at all, vs the
    // example/default value reappearing.
    cy.waitForElementNotInIframe('.layout-content img');
  });

  it('Can remove an optional code component image with example and there is no image in the preview', () => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
    cy.openLibraryPanel();
    cy.get(
      '[data-canvas-component-id="js.canvas_test_e2e_code_components_optional_image"]',
    ).realClick();
    cy.waitForElementInIframe(
      '.layout-content img[alt="Example image placeholder"]',
    );
    cy.get(
      '[class*="contextualPanel"] .js-media-library-open-button[data-once="drupal-ajax"]',
    )
      .first()
      .click();
    cy.get('div[role="dialog"]').should('exist');
    cy.findByLabelText('Select The bones are their money').check();
    cy.get('button:contains("Insert selected")').click();
    cy.get('div[role="dialog"]').should('not.exist');
    cy.waitForElementInIframe('img[alt="The bones equal dollars"]');
    cy.waitForElementNotInIframe(
      '.layout-content img[alt="Example image placeholder"]',
    );
    cy.findByLabelText('text').type('{selectall}{del}A new value');
    cy.findByLabelText('text').should('have.value', 'A new value');
    cy.waitForElementContentInIframe('p', 'A new value');
    cy.get('[class*="contextualPanel"]')
      .findByLabelText('Remove The bones are their money')
      .click();

    // Confirms the removed optional image prop is not rendered at all, vs the
    // example/default value reappearing.
    cy.waitForElementNotInIframe('.layout-content img');

    // Text prop is still intact after image removal.
    cy.waitForElementContentInIframe('p', 'A new value');
    // Confirm other props still work.
    cy.findByLabelText('text').type(
      '{selectall}{del}Further changes to the value',
    );
    cy.findByLabelText('text').should(
      'have.value',
      'Further changes to the value',
    );
    cy.waitForElementContentInIframe('p', 'Further changes to the value');
  });

  it('Can remove a required code component image with example and there is no image in the preview', () => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
    cy.openLibraryPanel();
    cy.get(
      '[data-canvas-component-id="js.canvas_test_e2e_code_components_req_image"]',
    ).realClick();
    cy.waitForElementInIframe(
      '.layout-content img[alt="Example image placeholder"]',
    );
    cy.get(
      '[class*="contextualPanel"] .js-media-library-open-button[data-once="drupal-ajax"]',
    )
      .first()
      .click();
    cy.get('div[role="dialog"]').should('exist');
    cy.findByLabelText('Select The bones are their money').check();
    cy.get('button:contains("Insert selected")').click();
    cy.get('div[role="dialog"]').should('not.exist');
    cy.waitForElementInIframe('img[alt="The bones equal dollars"]');
    cy.waitForElementNotInIframe(
      '.layout-content img[alt="Example image placeholder"]',
    );
    cy.findByLabelText('text').type('{selectall}{del}A new value');
    cy.findByLabelText('text').should('have.value', 'A new value');
    cy.waitForElementContentInIframe('p', 'A new value');
    cy.get('[class*="contextualPanel"]')
      .findByLabelText('Remove The bones are their money')
      .click();
    // Confirm the widget is now empty.
    cy.get('.js-media-library-widget .description')
      .contains('One media item remaining.')
      .should('exist');

    // The previously added image is still in the preview due to it being a
    // required prop.
    cy.waitForElementInIframe('img[alt="The bones equal dollars"]');

    // Confirms the example does not return.
    cy.waitForElementNotInIframe(
      '.layout-content img[alt="Example image placeholder"]',
    );

    // Text prop is still intact after image removal.
    cy.waitForElementContentInIframe('p', 'A new value');

    cy.findByLabelText('text').type(
      '{selectall}{del}Further changes to the value',
    );
    cy.findByLabelText('text').should(
      'have.value',
      'Further changes to the value',
    );
    cy.waitForElementContentInIframe('p', 'Further changes to the value');
  });
});
