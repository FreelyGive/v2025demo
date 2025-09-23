const input = `<p>1983...(A mermaid I should turn to be)</p>`;
export const edit = (cy) => {
  cy.findByLabelText('Canvas Text Area').as('textarea');
  cy.get('@textarea').parents('.js-text-format-wrapper').as('textarea-wrapper');
  cy.get('@textarea').should('have.value', '');
  cy.get('@textarea').type(input);
  cy.get('@textarea').should('have.value', input);
  // Wait for the preview update for the textarea.
  cy.wait('@updatePreview');
  // Trigger a new intercept for the format. This ensures the outer wait in the
  // main test waits for the second update.
  cy.intercept({
    url: '**/canvas/api/v0/layout/node/2',
    times: 1,
    method: 'POST',
  }).as('updatePreview');
  cy.get('@textarea-wrapper')
    .findByTestId('text-format-select')
    .should(($select) => {
      const options = Object.values(
        $select.find('option').map((_, option) => option.value),
      );
      expect(options).to.include('basic_html');
      expect(options).to.include('restricted_html');
      expect(options).to.include('minimal_html');
      // Confirm text formats exclusive to the component instance form are not
      // present here.
      expect(options).to.not.include('canvas_html_block');
      expect(options).to.not.include('canvas_html_inline');
    });
  cy.get('@textarea-wrapper')
    .findByTestId('text-format-select')
    .select('restricted_html');
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_textarea.format).to.eq(
    'restricted_html',
  );
  expect(response.attributes.field_cvt_textarea.value).to.eq(input);
};
