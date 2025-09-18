describe('Primary panel', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it(
    'Should ensure the library panel is scrollable',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      // Stub the HTTP request to return many components to make scrolling necessary
      cy.intercept('GET', '**/canvas/api/v0/config/component', {
        statusCode: 200,
        body: Array(50)
          .fill()
          .reduce((acc, _, index) => {
            const paddedIndex = String(index + 1).padStart(2, '0');
            const id = `canvas:component_${paddedIndex}`;
            acc[id] = {
              id,
              name: `Component ${paddedIndex}`,
              library: 'elements',
            };
            return acc;
          }, {}),
      }).as('getComponents');

      cy.loadURLandWaitForCanvasLoaded();

      cy.openLibraryPanel();
      cy.wait('@getComponents');

      cy.get('[data-testid="canvas-primary-panel"]')
        .realMouseWheel({ deltaY: 2500 })
        .then(() => {
          cy.get('[data-canvas-component-id="canvas:component_50"]').should(
            'be.visible',
          );
        });
    },
  );

  it('previews components on hover', () => {
    cy.loadURLandWaitForCanvasLoaded();

    cy.openLibraryPanel();

    const imageSelect =
      '.primaryPanelContent [data-canvas-component-id="sdc.canvas_test_sdc.image"]';
    const heroSelect =
      '.primaryPanelContent [data-canvas-component-id="sdc.canvas_test_sdc.my-hero"]';
    const codeComponentSelect =
      '.primaryPanelContent [data-canvas-component-id="js.my-cta"]';

    // Hover over "Image" and a preview should appear.
    cy.get(`${imageSelect}`).should('exist').realHover();
    cy.waitForElementInIframe(
      'img[alt="Boring placeholder"]',
      'iframe[data-preview-component-id="sdc.canvas_test_sdc.image"]',
    );

    // Hover over "My Hero" and a preview should appear and load correct CSS
    cy.get(`${heroSelect}`).should('exist').realHover();
    cy.waitForElementInIframe(
      'div.my-hero__container > .my-hero__actions > .my-hero__cta--primary',
      'iframe[data-preview-component-id="sdc.canvas_test_sdc.my-hero"]',
    );
    cy.getIframeBody(
      'iframe[data-preview-component-id="sdc.canvas_test_sdc.my-hero"]',
    )
      .find(
        'div.my-hero__container > .my-hero__actions > .my-hero__cta--primary',
      )
      .should('exist')
      .should(($cta) => {
        // Retry until the background-color is the expected one
        const bgColor = window.getComputedStyle($cta[0])['background-color'];
        expect(bgColor, 'The "My Hero" SDC is styled').to.equal(
          'rgb(0, 123, 255)',
        );
      });

    // Hover over "My First Code Component" and a preview should appear and
    // result in an accurate preview.
    cy.get(`${codeComponentSelect}`).should('exist').realHover();
    // @todo Make the test code component have meaningful markup and then assert details about its preview in https://www.drupal.org/i/3499988
  });
});
