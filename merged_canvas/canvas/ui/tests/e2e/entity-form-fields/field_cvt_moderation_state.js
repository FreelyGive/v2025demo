export const edit = (cy) => {
  cy.findByLabelText('Change to').as('moderation-state');
  cy.get('@moderation-state')
    .parents('.js-form-wrapper')
    .as('moderation-state-wrapper');
  cy.get('@moderation-state-wrapper')
    .findByLabelText('Change to', { exact: false })
    .as('changeTo');
  cy.get('@changeTo').should('have.value', 'published');
  cy.get('@changeTo').select('draft');
  cy.get('@changeTo').should('have.value', 'draft');
};
export const assertData = (response) => {
  expect(response.attributes.moderation_state).to.equal('draft');
};
