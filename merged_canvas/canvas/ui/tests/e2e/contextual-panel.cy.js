describe('Contextual panel', () => {
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

  it('should open the context menu on right-click', () => {
    cy.loadURLandWaitForCanvasLoaded();
    // Wait for the preview iframe to load and render something that confirms it is ready.
    cy.get('iframe[data-canvas-preview]').should('exist');
    // Right-click on the element that should trigger the context menu
    cy.getComponentInPreview('Hero').trigger('contextmenu');

    cy.findByLabelText('Context menu for Hero')
      .should('exist')
      .and('be.visible');
    // Assert that each menu item is inside the DropdownMenu.Content component
    cy.findByLabelText('Context menu for Hero').within(() => {
      cy.findByText('Edit code').should('not.exist');
      cy.findByText('Duplicate').should('be.visible');
      cy.findByText('Move').should('be.visible');
      cy.findByText('Delete').click();
    });
    cy.waitForElementContentNotInIframe('h1', 'hello, world!');
  });

  it('should open the context menu on right-click in primary content menu', () => {
    cy.loadURLandWaitForCanvasLoaded();
    // Wait for the preview iframe to load and render something that confirms it is ready.
    cy.get('iframe[data-canvas-preview]').should('exist');
    // Right-click on the element in primary content menu that should trigger the context menu.
    cy.get('.primaryPanelContent')
      .findByText('Two Column')
      .first()
      .trigger('contextmenu');

    cy.findByLabelText('Context menu for Two Column')
      .should('exist')
      .and('be.visible');

    // Assert that each menu item is inside the DropdownMenu.Content component
    cy.findByLabelText('Context menu for Two Column').within(() => {
      cy.findByText('Duplicate').should('be.visible');
      cy.findByText('Move').should('be.visible');
      cy.findByText('Delete').click();
    });
    cy.get('.primaryPanelContent').findByText('Two Column').should('not.exist');
  });

  it('should duplicate the element on clicking the "Duplicate" button', () => {
    cy.loadURLandWaitForCanvasLoaded();
    cy.getIframeBody()
      .find('[data-component-id="canvas_test_sdc:two_column"]')
      .should('have.length', 1);

    // Right-click on the element that should trigger the context menu
    cy.get('.primaryPanelContent')
      .findByText('Two Column')
      .trigger('contextmenu');

    cy.findByLabelText('Context menu for Two Column')
      .should('exist')
      .and('be.visible');
    cy.findByLabelText('Context menu for Two Column').within(() => {
      // Click on the "Duplicate" button
      cy.findByText('Duplicate').click();
    });
    cy.get('.primaryPanelContent')
      .findAllByText('Two Column')
      .should('have.length', 2);
  });

  it('Opens contextual panel on component selection with correct routing', () => {
    cy.loadURLandWaitForCanvasLoaded();

    // Find and alias the UUID of the "my-hero" component.
    cy.getComponentInPreview('Hero', 2)
      .find('[data-canvas-uuid]')
      .invoke('attr', 'data-canvas-uuid')
      .as('cid1');
    // Find and alias the UUID of the "image" component.
    cy.getComponentInPreview('Test SDC Image', 1)
      .find('[data-canvas-uuid]')
      .invoke('attr', 'data-canvas-uuid')
      .as('cid2');

    // Ensure both aliases are retrieved and compare them.
    cy.get('@cid1').then((uuid1) => {
      cy.get('@cid2').then((uuid2) => {
        expect(uuid2).to.not.equal(uuid1);
      });
    });

    // Click component 1.
    cy.get('@cid1').then((cid1) => {
      cy.clickComponentInPreview('Hero', 2);
      // Make sure the contextual panel opens for the clicked component.
      cy.findByTestId(`canvas-contextual-panel-${cid1}`).should('exist');
      // Make sure the component form is rendered for the clicked component.
      cy.findByTestId(`canvas-component-form-${cid1}`).should('exist');
      // Now on a path specific to that component.
      cy.url().should((url) => {
        expect(
          url,
          `After clicking on ${cid1}, path should include '/canvas/editor/node/1/component/${cid1}'`,
        ).to.contain(`/canvas/editor/node/1/component/${cid1}`);
      });
    });

    // Click component 2.
    cy.get('@cid2').then((cid2) => {
      cy.clickComponentInPreview('Test SDC Image', 1);

      // Make sure the contextual panel opens for the clicked component.
      cy.findByTestId(`canvas-contextual-panel-${cid2}`).should('exist');
      // Make sure the component form is rendered for the clicked component.
      cy.findByTestId(`canvas-component-form-${cid2}`).should('exist');
      // Now on a path specific to that component.
      cy.url().should((url) => {
        expect(
          url,
          `After clicking on ${cid2}, path should include '/canvas/editor/node/1/component/${cid2}'`,
        ).to.contain(`/canvas/editor/node/1/component/${cid2}`);
      });
    });

    cy.go('back');

    cy.get('@cid1').then((cid1) => {
      // Returns to the URL for the prior component.
      cy.url().should((url) => {
        expect(
          url,
          `Hit back once and path should again include '/canvas/editor/node/1/component/${cid1}'`,
        ).to.contain(`/canvas/editor/node/1/component/${cid1}`);
      });
      // Returns to the contextual form for the prior component.
      cy.findByTestId(`canvas-contextual-panel-${cid1}`).should('exist');
    });

    cy.go('back');

    cy.url().should((url) => {
      expect(
        url,
        `Hit back twice and the and path should not have 'component' in it`,
      ).to.not.contain('/canvas/editor/node/1/component');
      expect(
        url,
        `Hit back twice and the path should still have /canvas`,
      ).to.contain('/canvas/editor/node/1');
    });
  });

  it('Handles empty values in required inputs', () => {
    cy.loadURLandWaitForCanvasLoaded();

    // Extra debug output for component patching.
    cy.intercept('PATCH', '**/canvas/api/v0/layout/node/1');

    // Make note of the number of Hero components currently in the preview.
    cy.getIframeBody()
      .find('[data-component-id="canvas_test_sdc:my-hero"]')
      .its('length')
      .then((count) => {
        cy.wrap(count).as('initialHeroCount');
      });

    // Click a Hero component to open the component form.
    cy.clickComponentInPreview('Hero');

    // `@componentFormHeading`
    cy.findByTestId(/^canvas-component-form-.*/)
      .findByLabelText('Heading')
      .as('componentFormHeading');

    // Check if the "Heading" prop's <input> tag has the `required` attribute.
    cy.get('@componentFormHeading').should('have.attr', 'required');
    // Clear the value.
    cy.get('@componentFormHeading').clear();
    cy.get('@componentFormHeading').then(($input) => {
      // Ensure the input is invalid
      expect($input[0].validity.valid).to.be.false;
      expect($input[0].matches(':invalid')).to.be.true;
    });

    // Make sure the number of Hero components in the preview hasn't changed.
    cy.get('@initialHeroCount').then((initialHeroCount) => {
      cy.getIframeBody()
        .find('[data-component-id="canvas_test_sdc:my-hero"]')
        .its('length')
        .should('eq', initialHeroCount);
    });

    // Update the value of the "Heading" prop's <input>.
    cy.get('@componentFormHeading').type('New heading text');
    // Ensure the new value shows in the preview.
    cy.waitForElementContentInIframe(
      'div[data-component-id="canvas_test_sdc:my-hero"] h1',
      'New heading text',
    );

    // `@componentFormCTA1Link`
    cy.findByTestId(/^canvas-component-form-.*/)
      .findByLabelText('CTA 1 link')
      .as('componentFormCTA1Link');
    // `@componentFormCTA1Text`
    cy.findByTestId(/^canvas-component-form-.*/)
      .findByLabelText('CTA 1 text')
      .as('componentFormCTA1Text');

    // Check if the "CTA 1 link" prop's <input> tag has the `required` attribute.
    cy.get('@componentFormCTA1Link').should('have.attr', 'required');
    // Clear the value.
    cy.get('@componentFormCTA1Link').clear({ force: true });
    // Ensure the input is invalid.
    cy.get('@componentFormCTA1Link').then(($input) => {
      expect($input[0].validity.valid).to.be.false;
      expect($input[0].matches(':invalid')).to.be.true;
    });

    // Make sure the number of Hero components in the preview hasn't changed.
    cy.get('@initialHeroCount').then((initialHeroCount) => {
      cy.getIframeBody()
        .find('[data-component-id="canvas_test_sdc:my-hero"]')
        .its('length')
        .should('eq', initialHeroCount);
    });

    // Update the value of the "CTA 1 link" prop's <input>.
    cy.get('@componentFormCTA1Link').clear({ force: true });
    // Ensure the input is invalid.
    cy.get('@componentFormCTA1Link')
      .then(($input) => {
        expect($input[0].validity.valid).to.be.false;
        expect($input[0].matches(':invalid')).to.be.true;
      })
      .type('https://www.example.com/', { force: true });
    // Also update the value of the "CTA 1 text" prop's <input>.
    cy.get('@componentFormCTA1Text').clear();
    cy.get('@componentFormCTA1Text').type('Example link');
    // Ensure the new value shows in the preview.
    cy.waitForElementContentInIframe(
      'div[data-component-id="canvas_test_sdc:my-hero"] a',
      'Example link',
    );
    cy.getIframeBody()
      .findByText('Example link')
      .should('have.attr', 'href', 'https://www.example.com/');

    // Make sure the number of Hero components in the preview hasn't changed.
    cy.get('@initialHeroCount').then((initialHeroCount) => {
      cy.getIframeBody()
        .find('[data-component-id="canvas_test_sdc:my-hero"]')
        .its('length')
        .should('eq', initialHeroCount);
    });

    // Ensure enum/select required field does not have None option
    // Click on the first image component
    cy.clickComponentInPreview('Test SDC Image');

    cy.openLibraryPanel();

    // Click Heading in the side menu
    cy.insertComponent({ name: 'Heading' });
    // Check if heading component has been added in the preview
    cy.waitForElementContentInIframe(
      'h1[data-component-id="canvas_test_sdc:heading"]',
      'A heading element',
    );
    // Find added Heading component above and click on it
    cy.clickComponentInPreview('Heading');

    // Find the Element enum/select component and check for None option - it should not be there
    cy.findByTestId(/^canvas-component-form-.*/)
      .find('select[required]')
      .find('option')
      .should('not.contain', '- None -');

    // Hitting enter within a field should not submit the form or alter that
    // prop within the layout.
    cy.findByTestId(/^canvas-component-form-.*/)
      .find('input[required]')
      .click();
    cy.findByTestId(/^canvas-component-form-.*/)
      .find('input[required]')
      .type('{enter}');
    cy.getIframeBody().findByText('A heading element').should('exist');
  });

  it('should show "Edit code" option for code components in context menu', () => {
    cy.loadURLandWaitForCanvasLoaded();
    // Wait for the preview iframe to load
    cy.get('iframe[data-canvas-preview]').should('exist');

    // Right-click on the element in the preview should trigger the context menu.
    cy.getComponentInPreview('Test Code Component').trigger('contextmenu');

    // Verify the context menu opened with component name.
    cy.findByLabelText('Context menu for Test Code Component')
      .should('exist')
      .and('be.visible');

    // Verify it contains the "Edit code" option for code components and
    // click it to open the code editor.
    cy.findByLabelText('Context menu for Test Code Component').within(() => {
      cy.findByText('Edit code').should('be.visible');
      cy.findByText('Edit code').click();
    });

    // Verify we are redirected to the code editor page for this component
    cy.url().should('include', '/code-editor/component/test-code-component');
  });
});
