export const edit = (cy) => {
  cy.findByText('Comment settings').click({ force: true });
  cy.findByText('Comment settings')
    .parents('[data-state="open"][data-drupal-selector]')
    .as('commentFieldset');
  cy.get('@commentFieldset')
    .findByLabelText('Open', { exact: false })
    .invoke('attr', 'data-drupal-canvas-checked')
    .should('equal', 'true');

  cy.get('@commentFieldset')
    .findByLabelText('Hidden', { exact: false })
    .invoke('attr', 'data-drupal-canvas-checked')
    .should('equal', 'false');

  cy.get('@commentFieldset')
    .findByLabelText('Hidden', { exact: false })
    .click();

  cy.get('@commentFieldset')
    .findByLabelText('Open', { exact: false })
    .invoke('attr', 'data-drupal-canvas-checked')
    .should('equal', 'false');

  cy.get('@commentFieldset')
    .findByLabelText('Hidden', { exact: false })
    .invoke('attr', 'data-drupal-canvas-checked')
    .should('equal', 'true');
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_comment.status).to.equal(0);
};
