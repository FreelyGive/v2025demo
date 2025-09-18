const url = 'http://example.com/';
export const edit = (cy) => {
  cy.findByLabelText('Canvas URI').as('uri');
  cy.get('@uri').should('have.value', 'http://drupal.org');
  cy.get('@uri').clear();
  cy.get('@uri').type(url);
  cy.get('@uri').should('have.value', url);
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_uri).to.equal(url);
};
