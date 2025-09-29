describe('states', () => {
  before(() => {
    cy.drupalCanvasInstall(['canvas_test_state_api']);
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
    cy.openLibraryPanel();
    cy.get('.primaryPanelContent').should('contain.text', 'Components');
    cy.insertComponent({ name: 'Heading' });
    cy.waitForElementContentInIframe('div', 'A heading element');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('checkbox (default unchecked) toggles the visibility of a text field', () => {
    const controller = 'Checkbox A: Toggle conditionally visible field';
    const target = 'Visible when Checkbox A is checked';
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.visible');
    cy.findByLabelText(controller).check();
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.visible');
    cy.findByLabelText(controller).uncheck();
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.visible');
  });

  it('checkbox (default unchecked) toggles the disabled state of a text field', () => {
    const controller = 'Checkbox B: Toggle conditionally enabled field';
    const target = 'Enabled when Checkbox B is checked';
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.enabled');
    cy.findByLabelText(controller).check();
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.enabled');
    cy.findByLabelText(controller).uncheck();
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.enabled');
  });

  it('checkbox (default unchecked) toggles the visibility of another checkbox', () => {
    const controller = 'Checkbox C: Toggle visibility of another checkbox';
    const target = 'Visible when Checkbox C is checked';
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.visible');
    cy.findByLabelText(controller).check();
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.visible');
    cy.findByLabelText(controller).uncheck();
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.visible');
  });

  it('checkbox (default unchecked) toggles the checked state of another checkbox', () => {
    const controller = 'Checkbox D: Toggle checked state of another checkbox';
    const target = 'Checked when Checkbox D is checked';
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.checked');
    cy.findByLabelText(controller).check();
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.checked');
    cy.findByLabelText(controller).uncheck();
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.checked');
  });

  // Default checked

  it('checkbox (default checked) toggles the visibility of a text field', () => {
    const controller = '[REV] Checkbox A: Toggle conditionally visible field';
    const target = '[REV] Visible when Checkbox A is checked';
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.visible');
    cy.findByLabelText(controller).uncheck();
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.visible');
    cy.findByLabelText(controller).check();
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.visible');
  });

  it('checkbox (default checked) toggles the disabled state of a text field', () => {
    const controller = '[REV] Checkbox B: Toggle conditionally enabled field';
    const target = '[REV] Enabled when Checkbox B is checked';
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.enabled');
    cy.findByLabelText(controller).uncheck();
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.enabled');
    cy.findByLabelText(controller).check();
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.enabled');
  });

  it('checkbox (default checked) toggles the visibility of another checkbox', () => {
    const controller =
      '[REV] Checkbox C: Toggle visibility of another checkbox';
    const target = '[REV] Visible when Checkbox C is checked';
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.visible');
    cy.findByLabelText(controller).uncheck();
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.visible');
    cy.findByLabelText(controller).check();
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.visible');
  });

  it('checkbox (default checked) toggles the checked state of another checkbox', () => {
    const controller =
      '[REV] Checkbox D: Toggle checked state of another checkbox';
    const target = '[REV] Checked when Checkbox D is checked';
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.checked');
    cy.findByLabelText(controller).uncheck();
    cy.findByLabelText(controller).should('not.be.checked');
    cy.findByLabelText(target).should('not.be.checked');
    cy.findByLabelText(controller).check();
    cy.findByLabelText(controller).should('be.checked');
    cy.findByLabelText(target).should('be.checked');
  });

  it('responds to select changes', () => {
    cy.get('[data-when-not-empty]').should('not.be.visible');

    const states = [
      {
        option: 'make_default_enabled_disabled',
        target: 'Select: Default enabled field',
        before: ['be.enabled'],
        after: ['not.be.enabled'],
      },
      {
        option: 'make_default_disabled_enabled',
        target: 'Select: Default disabled field',
        before: ['not.be.enabled'],
        after: ['be.enabled'],
      },
      {
        option: 'make_required_optional',
        target: 'Select: Default required field',
        before: ['have.attr', 'required'],
        after: ['not.have.attr', 'required'],
      },
      {
        option: 'make_optional_required',
        target: 'Select: Default optional field',
        before: ['not.have.attr', 'required'],
        after: ['have.attr', 'required'],
      },
      {
        option: 'make_visible_invisible',
        target: 'Select: Default visible field',
        before: ['be.visible'],
        after: ['not.be.visible'],
      },
      {
        option: 'make_invisible_visible',
        target: 'Select: Default invisible field',
        before: ['not.be.visible'],
        after: ['be.visible'],
      },
      {
        option: 'make_checked_unchecked',
        target: 'Select: Default checked checkbox',
        before: ['be.checked'],
        after: ['not.be.checked'],
      },
      {
        option: 'make_unchecked_checked',
        target: 'Select: Default unchecked checkbox',
        before: ['not.be.checked'],
        after: ['be.checked'],
      },
    ];
    states.forEach(({ option, target, before, after }) => {
      cy.log(`Select state change: ${option}`);
      cy.findByLabelText(target).should(...before);
      cy.findByLabelText('Select to change states').select(option);
      cy.findByLabelText(target).should(...after);
      cy.findByLabelText('Select to change states').select('just_a_value');
      cy.findByLabelText(target).should(...before);
    });
    cy.get('[data-when-not-empty]').should('be.visible');
  });

  it('responds to radio changes', () => {
    cy.get('[data-when-any-radio]').should('not.be.visible');

    const states = [
      {
        option: 'Radio: Make default enabled disabled',
        target: 'Radio: Default enabled field',
        before: ['be.enabled'],
        after: ['not.be.enabled'],
      },
      {
        option: 'Radio: Make default disabled enabled',
        target: 'Radio: Default disabled field',
        before: ['not.be.enabled'],
        after: ['be.enabled'],
      },
      {
        option: 'Radio: Make required optional',
        target: 'Radio: Default required field',
        before: ['have.attr', 'required'],
        after: ['not.have.attr', 'required'],
      },
      {
        option: 'Radio: Make optional required',
        target: 'Radio: Default optional field',
        before: ['not.have.attr', 'required'],
        after: ['have.attr', 'required'],
      },
      {
        option: 'Radio: Make visible invisible',
        target: 'Radio: Default visible field',
        before: ['be.visible'],
        after: ['not.be.visible'],
      },
      {
        option: 'Radio: Make invisible visible',
        target: 'Radio: Default invisible field',
        before: ['not.be.visible'],
        after: ['be.visible'],
      },
      {
        option: 'Radio: Make checked unchecked',
        target: 'Radio: Default checked checkbox',
        before: ['be.checked'],
        after: ['not.be.checked'],
      },
      {
        option: 'Radio: Make unchecked checked',
        target: 'Radio: Default unchecked checkbox',
        before: ['not.be.checked'],
        after: ['be.checked'],
      },
    ];
    states.forEach(({ option, target, before, after }) => {
      cy.log(`Radio state change: ${option}`);
      cy.findByLabelText(target).should(...before);
      cy.findByLabelText(option).check();
      cy.findByLabelText(target).should(...after);
      cy.findByLabelText(
        'Radio: A value that does nothing but be a value',
      ).check();
      cy.findByLabelText(target).should(...before);
    });
    cy.get('[data-when-any-radio]').should('be.visible');
  });

  it('responds to text input changes', () => {
    const states = [
      {
        typeThis: 'make default enabled disabled',
        target: 'Text Target: Default enabled field',
        before: ['be.enabled'],
        after: ['not.be.enabled'],
      },
      {
        typeThis: 'make default disabled enabled',
        target: 'Text Target: Default disabled field',
        before: ['not.be.enabled'],
        after: ['be.enabled'],
      },
      {
        typeThis: 'make required optional',
        target: 'Text Target: Default required field',
        before: ['have.attr', 'required'],
        after: ['not.have.attr', 'required'],
      },
      {
        typeThis: 'make optional required',
        target: 'Text Target: Default optional field',
        before: ['not.have.attr', 'required'],
        after: ['have.attr', 'required'],
      },
      {
        typeThis: 'make visible invisible',
        target: 'Text Target: Default visible field',
        before: ['be.visible'],
        after: ['not.be.visible'],
      },
      {
        typeThis: 'make invisible visible',
        target: 'Text Target: Default invisible field',
        before: ['not.be.visible'],
        after: ['be.visible'],
      },
      {
        typeThis: 'make checked unchecked',
        target: 'Text Target: Default checked checkbox',
        before: ['be.checked'],
        after: ['not.be.checked'],
      },
      {
        typeThis: 'make unchecked checked',
        target: 'Text Target: Default unchecked checkbox',
        before: ['not.be.checked'],
        after: ['be.checked'],
      },
    ];

    states.forEach(({ typeThis, target, before, after }) => {
      cy.log(`Text input changes: ${target}`);
      cy.findByLabelText(target).should(...before);
      cy.findByLabelText('Control Text Input').type(typeThis);
      cy.get('[data-visible-when-text-not-empty]').should('be.visible');
      cy.get('[data-visible-when-text-only-empty]').should('not.be.visible');
      cy.findByLabelText(target).should(...after);
      cy.findByLabelText('Control Text Input').clear();
      cy.findByLabelText(target).should(...before);
      cy.get('[data-visible-when-text-not-empty]').should('not.be.visible');
      cy.get('[data-visible-when-text-only-empty]').should('be.visible');
    });
  });
});
