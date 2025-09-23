export const edit = (cy) => {
  cy.findByLabelText('Canvas Telephone').as('telephone');
  cy.get('@telephone').should('have.value', '');
  cy.get('@telephone').type('1-800-444-4444');
  cy.get('@telephone').should('have.value', '1-800-444-4444');
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_telephone).to.equal('1-800-444-4444');
};
