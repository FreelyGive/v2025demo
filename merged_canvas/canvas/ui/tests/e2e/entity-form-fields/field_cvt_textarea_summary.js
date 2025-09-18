const newValue = `Thick heart of stone`;
const newSummary = 'Until I look out the window';
export const edit = (cy) => {
  // Get the main textarea element.
  cy.findByLabelText('Canvas Text Area with Summary').as('textarea-summary');
  // And the wrapper.
  cy.get('@textarea-summary')
    .parents('.js-text-format-wrapper')
    .as('textarea-summary-wrapper');
  // Check the default value in the main element.
  cy.get('@textarea-summary').should(
    'have.value',
    'Melting in a pot of thieves',
  );
  // Get the summary input.
  cy.get('@textarea-summary-wrapper')
    .findByRole('textbox', { name: 'Summary(Hide summary)' })
    .as('textarea-summary-input');
  // Check the default value in the summary element.
  cy.get('@textarea-summary-input').should(
    'have.value',
    'Wild card up my sleeve',
  );
  // Hide the summary input.
  cy.get('@textarea-summary-wrapper')
    .findByRole('button', { name: 'Hide summary', exact: false })
    .click();
  // The summary input should no longer exist.
  cy.get('[data-drupal-selector="edit-field-cvt-textarea-summary-0-value"]')
    .parents('.js-text-format-wrapper')
    .findByRole('button', { name: 'Hide summary' })
    .should('not.exist');

  // Now click 'Edit summary' to show it again.
  cy.get('[data-drupal-selector="edit-field-cvt-textarea-summary-0-value"]')
    .parents('.js-text-format-wrapper')
    .findByRole('button', { name: 'Edit summary', exact: false })
    .click();
  // Summary input should now exist again.
  cy.get('@textarea-summary-input').should('exist');
  // Clear the main input and add a new value.
  cy.get('@textarea-summary').clear();
  cy.get('@textarea-summary').type(newValue);
  cy.get('@textarea-summary').should('have.value', newValue);
  // Wait for the preview update for the textarea.
  cy.wait('@updatePreview');
  // Trigger a new intercept for the summary change. This ensures the format
  // wait below captures its own update.
  cy.intercept({
    url: '**/canvas/api/v0/layout/node/2',
    times: 1,
    method: 'POST',
  }).as('updatePreview');
  // Clear the summary input and add a new value.
  cy.get('@textarea-summary-input').clear();
  cy.get('@textarea-summary-input').type(newSummary);
  cy.get('@textarea-summary-input').should('have.value', newSummary);
  // Wait for the preview update for the summary.
  cy.wait('@updatePreview');
  // Trigger a new intercept for the format. This ensures the outer wait in the
  // main test waits for the third update.
  cy.intercept({
    url: '**/canvas/api/v0/layout/node/2',
    times: 1,
    method: 'POST',
  }).as('updatePreview');
  // Change the input format.
  cy.get('@textarea-summary-wrapper')
    .findByTestId('text-format-select')
    .should(($select) => {
      expect($select).to.have.value('basic_html');
    });
  cy.get('@textarea-summary-wrapper')
    .findByTestId('text-format-select')
    .select('restricted_html');
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_textarea_summary.format).to.eq(
    'restricted_html',
  );
  expect(response.attributes.field_cvt_textarea_summary.value).to.eq(newValue);
  expect(response.attributes.field_cvt_textarea_summary.summary).to.eq(
    newSummary,
  );
};
