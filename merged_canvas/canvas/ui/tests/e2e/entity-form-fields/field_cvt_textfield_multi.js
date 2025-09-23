const items = [
  'The Music Tapes',
  'Neutral Milk Hotel',
  'of Montreal',
  'The Olivia Tremor Control',
];
export const edit = (cy) => {
  cy.findByRole('heading', { name: 'Canvas Unlimited Text' })
    .parents('.js-form-wrapper')
    .as('textfield_multi');
  cy.get('@textfield_multi')
    .findByRole('button', { name: 'Add another item' })
    .as('add-another-text');
  cy.findByLabelText('Canvas Unlimited Text (value 1)').should(
    'have.value',
    'Marshmallow Coast',
  );
  items.forEach((item, ix) => {
    cy.findByLabelText(`Canvas Unlimited Text (value ${ix + 2})`).type(item);
    cy.findByLabelText(`Canvas Unlimited Text (value ${ix + 2})`).should(
      'have.value',
      item,
    );
    // Wait for the preview to finish loading.
    cy.wait('@updatePreview');
    // Queue another intercept for the wait in the main test and/or the next
    // iteration in the loop.
    cy.intercept({
      url: '**/canvas/api/v0/layout/node/2',
      times: 1,
      method: 'POST',
    }).as('updatePreview');
    cy.waitForAjax();

    // Despite waiting on the layout request and AJAX completion, this wait is
    // still necessary in order to prevent a specific problem where the first
    // only the first item in the loop makes it to the published version of the
    // node, despite all items being properly added to the form.
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(400);
    cy.get('@add-another-text').click({ force: true });
    cy.get('@entityForm').shouldHaveUpdatedFormBuildId(10000);
  });
};
export const assertData = (response) => {
  // Add the default field value.
  // @see \canvas_test_article_fields_install().
  expect(response.attributes.field_cvt_unlimited_text).to.deep.eq([
    'Marshmallow Coast',
    ...items,
  ]);
};
