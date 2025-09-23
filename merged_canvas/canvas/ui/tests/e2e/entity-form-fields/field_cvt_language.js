export const edit = (cy) => {
  cy.findByLabelText('Canvas Language')
    .parent()
    .find('select')
    .as('languageSelect');
  cy.get('@languageSelect').should('have.value', 'und');
  // Radix renders this as a hidden element with a button to trigger, so
  // we have to use force.
  cy.get('@languageSelect').select('English', { force: true });
  cy.get('@languageSelect').should('have.value', 'en');
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_language).to.equal('en');
};
