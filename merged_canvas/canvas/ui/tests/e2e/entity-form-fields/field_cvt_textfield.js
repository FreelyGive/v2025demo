const text = 'Personal Responsibilities';
export const edit = (cy) => {
  cy.findByLabelText('Canvas Text Field').as('textfield');
  cy.get('@textfield').should('have.value', '');
  cy.get('@textfield').type(text);
  cy.get('@textfield').should('have.value', text);
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_textfield).to.equal(text);
};
