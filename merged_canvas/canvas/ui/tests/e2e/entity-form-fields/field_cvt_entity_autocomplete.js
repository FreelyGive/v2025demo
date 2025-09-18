export const edit = (cy) => {
  cy.findByLabelText('Canvas Entity Reference (Autocomplete)').as(
    'autocomplete',
  );
  cy.get('@autocomplete').should(
    'have.value',
    'Canvas With a block in the layout (3)',
  );
  cy.get('@autocomplete').clear({ force: true });
  cy.get('@autocomplete').realType('Canvas Needs This For', { force: true });
  cy.get('ul.ui-autocomplete').should('exist');
  cy.get('ul.ui-autocomplete li a').should(
    'have.text',
    'Canvas Needs This For The Time Being',
  );
  cy.get('ul.ui-autocomplete li a').type('{enter}', { force: true });
  cy.get('@autocomplete').should(
    'have.value',
    'Canvas Needs This For The Time Being (1)',
  );
};
export const assertData = (response) => {
  expect(
    response.relationships.field_cvt_entity_ref_auto.data.meta
      .drupal_internal__target_id,
  ).to.equal(1);
};
