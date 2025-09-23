describe('Multi-select components', () => {
  before(() => {
    // Temp. while multi-selection is still in development
    cy.drupalCanvasInstall(['canvas_dev_mode']);
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded();

    // Make sure we have multiple components visible for testing
    cy.testInIframe(
      '[data-component-id="canvas_test_sdc:my-hero"]',
      (myHeroComponent) => {
        expect(myHeroComponent.length).to.be.at.least(
          2,
          'Need at least 2 Hero components for multi-select tests',
        );
      },
    );
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('should select a single component when clicked', () => {
    // Click the first Hero component
    cy.clickComponentInPreview('Hero', 0);
    cy.previewReady();

    // Check that the component is selected
    cy.getAllComponentsInPreview('Hero')
      .eq(0)
      .should('have.attr', 'data-canvas-selected', 'true');

    // Check that only one component is selected (no multiselect active)
    cy.getAllComponentsInPreview('Hero')
      .filter('[data-canvas-selected="true"]')
      .should('have.length', 1);

    // Check that the single-component panel is shown
    cy.get('[data-testid="canvas-contextual-panel"]').should('exist');
    cy.get('[data-testid="canvas-contextual-panel--settings"]').should('exist');
    cy.findByLabelText('Heading').should('have.value', 'hello, world!');

    cy.location('pathname').should(
      'match',
      /\/editor\/[^/]+\/[^/]+\/component/,
    );
  });

  it('should select multiple components with cmd/meta + click', () => {
    // Click the first component
    cy.clickComponentInPreview('Hero', 0);
    cy.previewReady();

    // URL should contain the component ID for single selection
    cy.location('pathname').should('include', '/component/');

    // Then meta+click the second component
    cy.getAllComponentsInPreview('Hero')
      .eq(1)
      .click({ metaKey: true, force: true });
    cy.previewReady();

    // Both components should be selected
    cy.getAllComponentsInPreview('Hero')
      .filter('[data-canvas-selected="true"]')
      .should('have.length', 2);

    // The multi-select panel should show with count
    cy.get('[data-testid="canvas-contextual-panel"]')
      .contains('2 items selected')
      .should('be.visible');

    // URL should no longer contain the component ID for multi-selection
    cy.location('pathname').should('not.include', '/component/');
  });

  it('should toggle selection of component with cmd/meta + click', () => {
    // First select two components
    cy.clickComponentInPreview('Hero', 0);
    cy.previewReady();

    cy.getAllComponentsInPreview('Hero')
      .eq(1)
      .click({ metaKey: true, force: true });
    cy.previewReady();

    // Check both are selected
    cy.getAllComponentsInPreview('Hero')
      .filter('[data-canvas-selected="true"]')
      .should('have.length', 2);

    // URL should not contain component ID for multi-selection
    cy.location('pathname').should('not.include', '/component/');

    // Now meta+click one of them again to deselect it
    cy.getAllComponentsInPreview('Hero')
      .eq(1)
      .click({ metaKey: true, force: true });
    cy.previewReady();

    // Only one should remain selected
    cy.getAllComponentsInPreview('Hero')
      .filter('[data-canvas-selected="true"]')
      .should('have.length', 1);

    // Assert that the URL has the correct /component/:componentId in the URL
    cy.location('pathname').should('include', '/component/');

    // Assert that the multi select panel is no longer shown
    cy.get('[data-testid="canvas-contextual-panel"]')
      .contains('items selected')
      .should('not.exist');
  });

  it('should select components from Layers panel and support multi-selection', () => {
    cy.previewReady();

    // Find and click Hero components in layers view
    cy.findByTestId('canvas-primary-panel').within(() => {
      cy.findAllByText('Hero') // Try to find by text instead of label
        .first()
        .click();
    });

    // Verify a component is selected in preview
    cy.getAllComponentsInPreview('Hero')
      .filter('[data-canvas-selected="true"]')
      .should('have.length', 1);

    // Try to select a second one with meta key
    cy.findByTestId('canvas-primary-panel')
      .findAllByText('Hero')
      .eq(1)
      .click({ metaKey: true, force: true });
    cy.previewReady();

    // Both should be selected in preview
    cy.getAllComponentsInPreview('Hero')
      .filter('[data-canvas-selected="true"]')
      .should('have.length', 2);

    // Multi-select UI should be shown
    cy.get('[data-testid="canvas-contextual-panel"]')
      .contains('items selected')
      .should('exist');
  });

  it('should sync selection between preview and layers panel', () => {
    cy.previewReady();

    // Now select in preview
    cy.clickComponentInPreview('Hero', 0);
    cy.previewReady();

    // Verify that selection is reflected in the layers panel
    cy.findByTestId('canvas-primary-panel')
      .find('[data-canvas-selected="true"]')
      .should('exist');

    // Select a second component in preview with meta key
    cy.getAllComponentsInPreview('Hero')
      .eq(1)
      .click({ metaKey: true, force: true });
    cy.previewReady();

    // Should find at least 2 selected items in layers panel
    cy.findByTestId('canvas-primary-panel')
      .find('[data-canvas-selected="true"]')
      .should('have.length.at.least', 2);
  });

  it('should prevent selecting parent and child components simultaneously', () => {
    cy.previewReady();

    cy.log('Select the parent');
    cy.findByTestId('canvas-primary-panel').within(() => {
      cy.findByText('Two Column').click();
    });

    cy.findByTestId('canvas-primary-panel')
      .find('[data-canvas-selected="true"]')
      .should('have.length', 1);

    cy.log('Try to multi select one of its children');
    cy.findByTestId('canvas-primary-panel').within(() => {
      cy.findByText('One Column').click({ metaKey: true });
    });

    cy.log(
      'Still should have one item selected, selecting a child replaces the parent in the selection',
    );
    cy.findByTestId('canvas-primary-panel')
      .find('[data-canvas-selected="true"]')
      .should('have.length', 1);

    cy.log('Select a sibling child');
    cy.findByTestId('canvas-primary-panel').within(() => {
      cy.findAllByText('Hero').first().click({ metaKey: true });
    });

    cy.findByTestId('canvas-primary-panel')
      .find('[data-canvas-selected="true"]')
      .should('have.length', 2);

    cy.log('Multi select the parent again');
    cy.findByTestId('canvas-primary-panel').within(() => {
      cy.findByText('Two Column').click({ metaKey: true });
    });

    cy.log(
      'Should have one item selected, selecting a parent replaces the children in the selection',
    );
    cy.findByTestId('canvas-primary-panel')
      .find('[data-canvas-selected="true"]')
      .should('have.length', 1);
  });
});
