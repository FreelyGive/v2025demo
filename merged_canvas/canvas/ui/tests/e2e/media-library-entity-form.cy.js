const testMediaLibraryInEntityForm = (cy, loadOptions = {}, title) => {
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

  cy.drupalLogin('canvasUser', 'canvasUser');
  // Node 1 includes prop sources that make use of adapters, we need to
  // make sure there are no auto-save entries for that node before we attempt
  // to publish. This test interacts with that node in the "Can open the media
  // library widget in an article props form" case which causes an invalid entry
  // in auto-save that prevents publishing.
  cy.clearAutoSave('node', 1);

  cy.loadURLandWaitForCanvasLoaded(loadOptions);

  cy.findByTestId('canvas-contextual-panel--page-data').should(
    'have.attr',
    'data-state',
    'active',
  );
  cy.findByTestId('canvas-page-data-form').as('entityForm');
  // Log all ajax form requests to help with debugging.
  cy.intercept('POST', '**/canvas/api/v0/form/content-entity/**');
  // Make a record of the starting form build ID for the form
  cy.get('@entityForm').recordFormBuildId();

  // Perform media operations.
  iterations.forEach((step, ix) => {
    cy.log(`Iteration ${ix + 1}: start`);
    cy.findByRole('dialog').should('not.exist');
    cy.get('@entityForm').findByRole(step.expectedAlt).should('not.exist');
    if (ix > 0) {
      cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
      cy.get('@entityForm')
        .findByRole('button', { name: step.removeText })
        .should('exist')
        .click();
      // Wait for the preview to finish loading.
      cy.wait('@updatePreview');
      cy.findByLabelText('Loading Preview').should('not.exist');
      cy.get('@entityForm').shouldHaveUpdatedFormBuildId();
      cy.log(`Iteration ${ix + 1}: ${step.removeText} complete`);
    }
    cy.get('@entityForm')
      .findByRole('button', { name: 'Add media', timeout: 10000 })
      .should('not.be.disabled')
      .click();
    // The first time the media dialog opens there are a lot of CSS files to
    // load, and it can take more than the default timeout of 4s.
    cy.findByRole('dialog', { timeout: 10000 }).as('dialog');
    cy.get('@entityForm').shouldHaveUpdatedFormBuildId();
    cy.get('@dialog').findByLabelText(step.selectNewText).check();
    cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
    cy.get('@dialog')
      .findByRole('button', {
        name: 'Insert selected',
      })
      .click();
    cy.findByRole('dialog').should('not.exist');
    // Wait for the preview to finish loading.
    cy.wait('@updatePreview', { timeout: 10000 });
    cy.findByLabelText('Loading Preview').should('not.exist');
    cy.get('@entityForm').findByAltText(step.expectedAlt).should('exist');
    cy.get('@entityForm')
      .findByRole('button', { name: step.removeAriaLabel })
      .should('exist');
    cy.get('@entityForm').shouldHaveUpdatedFormBuildId();
    cy.log(`Iteration ${ix + 1}: Adding ${step.expectedAlt} complete`);
  });

  // Add a new component which should trigger opening the component instance form
  // in the contextual panel.
  cy.openLibraryPanel();
  cy.get('.primaryPanelContent').should('contain.text', 'Components');
  cy.get('.primaryPanelContent').findByText('Hero').click();
  cy.findByTestId('canvas-contextual-panel').should('exist');
  cy.get(
    '[class*="contextualPanel"] [data-drupal-selector="component-instance-form"]',
  ).within(() => {
    cy.findAllByLabelText('Heading').should('exist');
  });
  const lastStep = iterations.pop();

  // Switch back to entity edit form.
  cy.findByTestId('canvas-contextual-panel--page-data').click();
  // It can take a bit for the entity form to load, so let's give it a bit
  // longer.
  cy.get('@entityForm')
    .findByAltText(lastStep.expectedAlt, { timeout: 10000 })
    .should('exist');
  cy.get('@entityForm')
    .findByRole('button', { name: lastStep.removeAriaLabel })
    .should('exist');

  // Switch to full screen preview.
  cy.findByText('Preview').click();
  cy.findByText('Exit Preview').click();
  cy.get('@entityForm')
    .findByAltText(lastStep.expectedAlt, { timeout: 10000 })
    .should('exist');
  cy.get('@entityForm')
    .findByRole('button', { name: lastStep.removeAriaLabel })
    .should('exist');

  cy.publishAllPendingChanges(title);

  // Reload the page and ensure the saved value persists.
  cy.loadURLandWaitForCanvasLoaded({ ...loadOptions, clearAutoSave: false });
  // It can take a bit for the entity form to load, so let's give it a bit
  // longer.
  cy.get('@entityForm')
    .findByAltText(lastStep.expectedAlt, { timeout: 10000 })
    .should('exist');
  cy.get('@entityForm')
    .findByRole('button', { name: lastStep.removeAriaLabel })
    .should('exist');
};

describe('Media Library In Entity (page data) Form', () => {
  before(() => {
    cy.drupalCanvasInstall([], {}, ['administer nodes']);
  });

  beforeEach(() => {
    cy.drupalSession();
    // A larger viewport makes it easier to debug in the test runner app.
    cy.viewport(2000, 1000);
  });

  after(() => {
    cy.drupalUninstall();
  });

  it(
    'Can open the media library widget on a page data entity form',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      testMediaLibraryInEntityForm(
        cy,
        { url: 'canvas/editor/canvas_page/2' },
        'Empty Page',
      );
    },
  );

  it(
    'Can open the media library widget on an article entity form',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      testMediaLibraryInEntityForm(
        cy,
        { url: 'canvas/editor/node/2' },
        'I am an empty node',
      );
    },
  );
});
