// cspell:ignore macbook
describe('Operate on components + interact in global regions', () => {
  before(() => {
    cy.drupalCanvasInstall();
    cy.drupalEnableTheme('olivero');
    cy.drupalEnableThemeForCanvas('olivero');
    cy.viewport('macbook-13');
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it(
    'Can interact with components in global regions',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded();
      cy.findByTestId('scale-to-fit').click();
      cy.findByLabelText('Select zoom level').realClick({ force: true });
      cy.get('[role="option"]').contains('50%').click();
      cy.get('#canvasPreviewOverlay').realMouseWheel({ deltaY: -5 });

      cy.get('#canvasPreviewOverlay .canvas--viewport-overlay')
        .first()
        .as('desktopPreviewOverlay');
      cy.get('.primaryPanelContent').as('layersTree');

      // Open the layers in the Tree.
      cy.get('@layersTree')
        .findAllByText('Test SDC Image')
        .should('be.visible');
      cy.get('@layersTree')
        .findAllByText('Hero')
        .should('be.visible')
        .and('have.length', 3);

      cy.get('@layersTree').findAllByText('Hero').first().click();

      cy.intercept('POST', '**/canvas/api/v0/layout/node/1').as('getPreview');
      cy.log(
        'Move static hero component out of the content region into the highlighted region.',
      );

      cy.getComponentInPreview('Hero', 2).should('exist');
      cy.sendComponentToRegion('Hero', 'Highlighted');
      cy.getComponentInPreview('Hero', 2).should('not.exist');

      cy.focusRegion('Highlighted');
      // But a hero component should now be in highlighted region too.
      cy.clickComponentInPreview('Hero', 0, 'highlighted');

      cy.log('Test region overlays.');
      let lgPreviewRect = {};
      // Enter the iframe to find an element in the preview iframe and hover over it.
      cy.waitForElementInIframe(
        '[data-canvas-uuid="208452de-10d6-4fb8-89a1-10e340b3744c"] h1',
      );
      cy.getIframeBody()
        // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
        .find('[data-canvas-uuid="208452de-10d6-4fb8-89a1-10e340b3744c"] h1')
        .first()
        .then(($h1) => {
          // While in the iframe, get the dimensions of the component so we can
          // compare the outline dimensions to it
          const $item = $h1.closest('[data-canvas-uuid]');
          lgPreviewRect = $item[0].getBoundingClientRect();
        });

      cy.getComponentInPreview('Hero', 0, 'highlighted').then(($component) => {
        cy.wrap($component).realHover({
          position: 'center',
          scrollBehavior: false,
        });
      });
      cy.getComponentInPreview('Hero', 0, 'highlighted')
        .should(($outline) => {
          expect($outline).to.exist;
          // Ensure the width is set before moving on to then().
          expect($outline[0].getBoundingClientRect().width).to.not.equal(0);
        })
        .then(($outline) => {
          // The outline width and height should be the same as the dimensions of
          // the corresponding component in the iframe.
          const outlineRect = $outline[0].getBoundingClientRect();
          expect(outlineRect.width * 2).to.be.closeTo(lgPreviewRect.width, 0.1);
          expect(outlineRect.height * 2).to.be.closeTo(
            lgPreviewRect.height,
            0.1,
          );
          expect($outline).to.have.css('position', 'absolute');
        });

      // Click the component in the highlighted region to trigger the opening of the
      // right drawer.
      cy.clickComponentInPreview('Hero', 0, 'highlighted');

      cy.editHeroComponent();
    },
  );
});
