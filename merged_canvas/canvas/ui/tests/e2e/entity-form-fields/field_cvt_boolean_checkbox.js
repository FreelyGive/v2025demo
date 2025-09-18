export const edit = (cy) => {
  cy.findByLabelText('Canvas Boolean Checkbox (default true)').as('checkbox');
  cy.get('@checkbox').should('have.attr', 'aria-checked', 'true');
  cy.get('@checkbox').click();
  cy.get('@checkbox').should('have.attr', 'aria-checked', 'false');
  // Wait for the preview to finish loading.
  cy.wait('@updatePreview');
  cy.findByLabelText('Loading Preview').should('not.exist');

  // Trigger a new intercept for the main test to wait for.
  cy.intercept({
    url: '**/canvas/api/v0/layout/node/2',
    times: 1,
    method: 'POST',
  }).as('updatePreview');
  cy.findByLabelText('Canvas Boolean Checkbox (default false)').as('checkbox');
  cy.get('@checkbox').should('have.attr', 'aria-checked', 'false');
  cy.get('@checkbox').click();
  cy.get('@checkbox').should('have.attr', 'aria-checked', 'true');
};

export const assertData = (response) => {
  expect(response.attributes.field_cvt_boolean_checkbox).to.eq(false);
  expect(response.attributes.field_cvt_boolean_checkbox2).to.eq(true);
};
