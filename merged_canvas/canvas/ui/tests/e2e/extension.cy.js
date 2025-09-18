describe('extending Drupal Canvas', () => {
  before(() => {
    cy.drupalCanvasInstall(['canvas_test_extension', 'canvas_dev_mode']);
  });

  after(() => {
    cy.drupalUninstall();
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  it('Insert, focus, delete a component', () => {
    cy.loadURLandWaitForCanvasLoaded();
    cy.openLibraryPanel();

    // Get the components list from the sidebar so it can be compared to the
    // component select dropdown provided by the extension.
    cy.get('.primaryPanelContent [data-canvas-name]').then(($components) => {
      const availableComponents = [];

      $components.each((index, item) => {
        availableComponents.push(item.textContent.trim());
      });

      cy.findByLabelText('Extensions').click();
      cy.findByText('Canvas Test Extension').click();

      cy.findByTestId('ex-select-component').then(($select) => {
        const extensionComponents = [];
        // Get all the items with values in the extension component list, which
        // will be compared to the component list from the Canvas UI.
        $select.find('option').each((index, item) => {
          if (item.value) {
            extensionComponents.push(item.textContent.trim());
          }
        });

        // Check if every available component is included in the extension components
        const allAvailableComponentsExist = availableComponents.every(
          (component) => extensionComponents.includes(component),
        );
        expect(
          allAvailableComponentsExist,
          'All library components exist in the extension component dropdown',
        ).to.be.true;
      });
    });

    cy.log(
      'Confirm that an extension can select an item in the layout, focus it, update it, then delete it',
    );
    cy.waitForElementContentInIframe('div', 'hello, world!');
    // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
    const heroUuid = '208452de-10d6-4fb8-89a1-10e340b3744c';
    cy.findByTestId('ex-select-in-layout').select(heroUuid);
    cy.findByTestId('ex-selected-element').should('be.empty');
    cy.findByTestId('ex-focus').click();
    cy.findByTestId('ex-selected-element').should('have.text', heroUuid);
    cy.findByTestId('canvas-contextual-panel').should('exist');
    cy.findByTestId('ex-delete').click();
    cy.waitForElementContentNotInIframe('div', 'hello, world!');

    // Choose a component to add to the layout.
    cy.findByTestId('ex-select-component').select(
      'sdc.canvas_test_sdc.my-hero',
    );
    // Add it.
    cy.findByTestId('ex-insert').click();
    // Confirm the programmatically inserted component has a non-default value.
    cy.findByLabelText('Heading').should('have.value', 'Hijacked Value');
    cy.waitForElementContentInIframe('.my-hero__heading', 'Hijacked Value');

    cy.findByTestId('ex-update').click();
    cy.findByLabelText('Heading').should(
      'have.value',
      'an extension updated this',
    );
    cy.waitForElementContentInIframe(
      '.my-hero__heading',
      'an extension updated this',
    );
  });
});
