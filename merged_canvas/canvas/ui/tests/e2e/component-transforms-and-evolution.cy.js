describe('Component transforms', () => {
  before(() => {
    // Enable canvas_test_storage_prop_shape_alter after nodes have been created in
    // CanvasTestSetup. The stored nodes make use of a link item, but by enabling
    // this module, the my-hero component will now make use of an uri item.
    // We should still be able to edit existing data where the source type is
    // link rather than uri.
    cy.drupalCanvasInstall(['canvas_test_storage_prop_shape_alter']);
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Applies transforms based on the stored component instance, not the component metadata for new instances, can edit old versions', () => {
    cy.loadURLandWaitForCanvasLoaded();

    // Click a Hero component to open the component form.
    cy.clickComponentInPreview('Hero');

    cy.intercept('PATCH', '**/canvas/api/layout/node/1');

    cy.findByTestId(/^canvas-component-form-.*/)
      .findByLabelText('CTA 1 link')
      .as('componentFormCTA1Link');

    cy.get('@componentFormCTA1Link').clear({ force: true });
    // Ensure the input is invalid.
    const newUri = 'https://example.com/abdedfg';
    cy.get('@componentFormCTA1Link').type(newUri, { force: true });

    // Assert this uses the old 'link' widget because the component version was
    // from before CanvasTestStoragePropShapeAlterHooks::storagePropShapeAlter() was running.
    cy.get('@componentFormCTA1Link')
      .should('have.attr', 'name')
      .and('match', /\[0]\[uri]$/);
    // And should still be valid.
    cy.get('@componentFormCTA1Link').then(($input) => {
      expect($input[0].validity.valid).to.be.true;
      expect($input[0].matches(':invalid')).to.be.false;
    });

    cy.findByTestId(/^canvas-component-form-.*/)
      .findByLabelText('CTA 1 text')
      .as('componentFormCTA1Text');

    const linkText = 'Read more!';
    cy.get('@componentFormCTA1Text').clear();
    cy.get('@componentFormCTA1Text').type(linkText);

    // Ensure the new value shows in the preview.
    cy.waitForElementContentInIframe(
      `div[data-component-id="canvas_test_sdc:my-hero"] a[href="${newUri}"]`,
      linkText,
    );
  });
});
