export const edit = (cy) => {
  cy.findByLabelText('Option 2', { exact: false })
    .invoke('attr', 'data-drupal-canvas-checked')
    .should('equal', 'true');
  cy.findByText('Option 3', { exact: false }).click();
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_options_buttons).to.equal('option3');
};
