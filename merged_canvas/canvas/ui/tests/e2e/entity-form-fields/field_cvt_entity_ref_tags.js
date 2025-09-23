export const edit = (cy) => {
  cy.findByLabelText('Canvas Entity Reference (Tags)').as('tags');
  cy.get('@tags').should(
    'have.value',
    'Air-Sea Dolphin (1), The Apples in Stereo (2)',
  );
  cy.get('@tags').type(', Black Swan', { force: true });
  cy.get('ul.ui-autocomplete:visible').should('exist');
  cy.get('ul.ui-autocomplete:visible li').should(
    'have.text',
    'Black Swan Network',
  );
  cy.clock();
  cy.get('ul.ui-autocomplete:visible li').click();
  cy.get('@tags').should(
    'have.value',
    'Air-Sea Dolphin (1), The Apples in Stereo (2), Black Swan Network (4)',
  );
  // Advance timers to trigger timeouts. TextFieldAutocomplete relies on a
  // timeout to trigger form/model updates.
  cy.tick(500);

  // Once the timeout is triggered, disable the clock so other time based
  // operations work as expected.
  cy.clock().then((clock) => {
    clock.restore();
  });
};
export const assertData = (response) => {
  expect(
    response.relationships.field_cvt_entity_ref_tags.data.map(
      (item) => item.meta.drupal_internal__target_id,
    ),
  ).to.deep.eq([1, 2, 4]);
};
