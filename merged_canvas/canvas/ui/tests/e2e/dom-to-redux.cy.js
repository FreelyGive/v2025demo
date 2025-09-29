describe('DOM to Redux functionality', () => {
  before(() => {
    cy.drupalCanvasInstall(['canvas_test_native_value_js']);
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
    cy.openLibraryPanel();
    cy.get('.primaryPanelContent').should('contain.text', 'Components');
    cy.insertComponent({ name: 'Test Value Update' });
    cy.waitForElementContentInIframe('.text-value', 'The Default!');
  });

  it('update text value', () => {
    //tests/modules/canvas_test_native_value_js/js/test-value-updates.js

    // Confirm the initial state relevant to this test:
    // - The heading input value is "The Default!", which should also be
    //   reflected in the preview.
    // - The "Default Visible" text input is visible. It can be made invisible
    //   when the "Controlling Text" input has the value 'make visible
    //   invisible' @see canvas_test_state_api.module.
    // - The div that should be visible when the "Controlling Text" input is
    //   empty is visible. @see canvas_test_state_api.module
    // - The div that should be invisible when the "Controlling Text" input is
    //   empty is invisible. @see canvas_test_state_api.module
    cy.findByLabelText('Heading').should('have.value', 'The Default!');
    cy.waitForElementContentInIframe('.text-value', 'The Default!');
    cy.get('#edit-default-visible').scrollIntoView();
    cy.get('#edit-default-visible').should('be.visible');
    cy.findByLabelText('Control Text Input').should('have.value', '');
    cy.get('[data-visible-when-text-only-empty]').scrollIntoView();
    cy.get('[data-visible-when-text-only-empty]').should('be.visible');
    cy.get('[data-visible-when-text-not-empty]').should('not.be.visible');

    // Clicking this button will programmatically update two text inputs:
    // - The "Heading" input value will be "SURPRISE!". This is to confirm
    //   programmatic updates are  reflected in the preview.
    // - The "Controlling Text" input value will be "make visible invisible".
    //   This is to confirm that programmatic updates trigger the state API.
    // @see test-value-updates.js in the canvas_test_native_value_js module for the
    // implementation of this button's click handler.
    cy.get('#trigger-text-update').click();

    // - The heading input value is now "SURPRISE", which is also reflected in
    //   the preview.
    //   when the "Controlling Text" input has the value 'make visible
    //   invisible'.
    // - The "Default Visible" text input is no longer visible due to
    //   "Controlling Text" input having the value 'make visible invisible'.
    // - The div that should be visible when the "Controlling Text" input is
    //   empty is no longer visible.
    // - The div that should be invisible when the "Controlling Text" input is
    //   empty is now visible.
    cy.findByLabelText('Heading').should('have.value', 'SURPRISE!');
    cy.waitForElementContentInIframe('.text-value', 'SURPRISE!');
    cy.get('#edit-controlling-text').should(
      'have.value',
      'make visible invisible',
    );
    cy.get('#edit-default-visible').scrollIntoView();
    cy.get('#edit-default-visible').should('not.be.visible');
    cy.get('[data-visible-when-text-not-empty]').scrollIntoView();
    cy.get('[data-visible-when-text-not-empty]').should('be.visible');
    cy.get('[data-visible-when-text-only-empty]').should('not.be.visible');
  });

  it('update select value', () => {
    cy.findByLabelText('Select Value').should('have.value', 'foo');
    cy.waitForElementContentInIframe('.select-value', 'foo');
    cy.get('#trigger-select-update').click();
    cy.findByLabelText('Select Value').should('have.value', 'baz');
    cy.waitForElementContentInIframe('.select-value', 'baz');
  });

  it('update boolean value', () => {
    // Confirm the boolean value is initially true and that is reflected in the
    // preview.
    cy.findByLabelText('Bool Value').assertToggleState(true);
    cy.waitForElementContentInIframe('.test-value-update-bool code', 'true');

    // Click this button to programmatically toggle the boolean element.
    // @see test-value-updates.js in the canvas_test_native_value_js module for the
    // implementation of this button's click handler.
    cy.get('#trigger-toggle-update').click();

    // Confirm the boolean value is now false and that is reflected in the
    // preview.
    cy.findByLabelText('Bool Value').assertToggleState(false);
    cy.waitForElementContentInIframe('.test-value-update-bool code', 'false');
  });

  it('update number value', () => {
    // Confirm the number value is initially 999 and that is reflected in the
    // preview.
    cy.findByLabelText('Number Value').should('have.value', '999');
    cy.waitForElementContentInIframe('.number-value', '999');

    // Click this button to programmatically set the number element to 2000.
    // @see test-value-updates.js in the canvas_test_native_value_js module for the
    // implementation of this button's click handler.
    cy.get('#trigger-number-update').click();

    // Confirm the number value is now 2000 and that is reflected in the
    // preview.
    cy.findByLabelText('Number Value').should('have.value', '2000');
    cy.waitForElementContentInIframe('.number-value', '2000');
  });

  it('update checkbox value', () => {
    // Note that there are not currently props that use checkboxes, so this
    // is only testing that programmatic checkbox updates will trigger the state
    // API.

    // Confirm the checkbox is initially unchecked, and the field it controls is
    // not visible.
    cy.findByLabelText('Checkbox A: Toggle conditionally visible field').should(
      'not.be.checked',
    );
    cy.get('[name="conditional_visible_field"]').scrollIntoView();
    cy.get('[name="conditional_visible_field"]').should('not.be.visible');

    // Click this button to programmatically check the checkbox.
    // @see test-value-updates.js in the canvas_test_native_value_js module for the
    // implementation of this button's click handler.
    cy.get('#trigger-checkbox-update').click();

    // Confirm the checkbox is now checked, and the field it controls is now
    // visible.
    cy.findByLabelText('Checkbox A: Toggle conditionally visible field').should(
      'be.checked',
    );
    cy.get('[name="conditional_visible_field"]').should('be.visible');
  });
});
