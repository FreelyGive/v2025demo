// WARNING: describe.skip() is used to ignore this spec.
// @todo Rewrite in Playwright! See #3473617: Write end-to-end test for dragging and dropping components
// https://www.drupal.org/project/canvas/issues/3473617
// eslint-disable-next-line mocha/no-pending-tests
describe.skip('Component slots functionality', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Can add a component with slots and then add components into those slots', () => {
    cy.loadURLandWaitForCanvasLoaded();
    // Set the viewport to be 4k to ensure the full editor frame is visible without scrolling because
    // the scrolling messes up the realDnd command.
    cy.viewport(3840, 2160);
    cy.get('[data-canvas-uuid="root"]').findByText('Two Column').click();

    cy.log('Add a Two Column component at the bottom of the page');
    cy.findByText('Default components').click();
    cy.get('.MenubarSubContent').within(() => {
      cy.findByText('Two Column').click();
    });

    cy.previewReady();

    cy.log(
      'There should now be 2 Two Column components and the default content should be showing in the slots.',
    );
    cy.waitForElementContentInIframe('div', 'This is column 1 content');
    cy.waitForElementContentInIframe('div', 'This is column 2 content');
    cy.getIframeBody().within(() => {
      cy.get('[data-component-id="canvas_test_sdc:two_column"]').should(
        'have.length',
        2,
      );
      cy.get('[data-component-id="canvas_test_sdc:two_column"]')
        .first()
        .findByText('hello, world!');
    });

    cy.log('Drag an existing Hero from the preview into column 1');
    cy.getIframeBody()
      .findByText('This is column 1 content')
      .parent()
      .then(($dropTarget) => {
        cy.getIframeBody()
          .findByText('hello, world!')
          .realDnd($dropTarget, { position: 'center' });
      });

    cy.log(
      'The default content in the first slot of the 2nd Two Column component should have been replaced with the hello, world! hero component that was dragged from the first Two Column component.',
    );
    cy.waitForElementContentNotInIframe('div', 'This is column 1 content');
    cy.waitForElementContentInIframe('div', 'This is column 2 content');
    cy.getIframeBody().within(() => {
      cy.get('[data-component-id="canvas_test_sdc:two_column"]').should(
        'have.length',
        2,
      );
      cy.get('[data-component-id="canvas_test_sdc:two_column"]')
        .eq(1)
        .findByText('hello, world!');
    });

    cy.findByLabelText('Open add menu').click();
    cy.findByText('Default components').click();

    cy.log('Drag a new Pattern from the component list into column 2');
    cy.getIframeBody()
      .findByText('This is column 2 content')
      .parent()
      .then(($dropTarget) => {
        cy.get('.MenubarSubContent').within(() => {
          cy.findByText('Pattern').realDnd($dropTarget, { position: 'center' });
        });
      });

    cy.log(
      'The default content in the 2nd slot of the 2nd Two Column component should now have been replaced with the Pattern component that was dragged from the component list which has default text of "Our Mission".',
    );
    cy.waitForElementContentNotInIframe('div', 'This is column 1 content');
    cy.waitForElementContentNotInIframe('div', 'This is column 2 content');
    cy.getIframeBody().within(() => {
      cy.get('[data-component-id="canvas_test_sdc:two_column"]').should(
        'have.length',
        2,
      );
      cy.get('[data-component-id="canvas_test_sdc:two_column"]')
        .eq(1)
        .findByText('hello, world!');
      cy.get('[data-component-id="canvas_test_sdc:two_column"]')
        .eq(1)
        .findByText('Our Mission')
        .should('exist');
    });
  });
});
