const value = 42;
export const edit = (cy) => {
  cy.findByLabelText('Canvas Integer').as('integer');
  cy.get('@integer').should('have.value', 0);
  cy.get('@integer').clear();
  cy.get('@integer').type(value);
  cy.get('@integer').should('have.value', value);
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_int).to.equal(value);
};
