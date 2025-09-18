/* cspell:ignore Ronk mander mando bination mentary */
describe('Prop types editing', () => {
  const textFieldIterations = {
    'String: Required': {
      valuePre: 'Hello, required world!',
      valuePost: 'Hello, required world! Goodbye shack',
      typeThis: ' Goodbye shack',
      iframeSelector: '#test-required-string',
      labelText: 'String (required)',
    },
    'String — single line': {
      valuePre: 'Hello, world!',
      valuePost: 'Hello, world! My name is Ronk',
      typeThis: ' My name is Ronk',
      iframeSelector: '#test-string',
      labelText: 'String — single line',
    },
    'String: Multiline': {
      valuePre: 'Hello,\nmultiline\nworld!',
      valuePost: 'Hello,\nmultiline\nworld! yay',
      typeThis: ' yay',
      iframeSelector: '#test-string-multiline',
      labelText: 'String — multi-line',
    },

    'String: Format email': {
      valuePre: 'hello@example.com',
      valuePost: 'hello@example.commander',
      typeThis: 'mander',
      iframeSelector: '#test-string-format-email',
      labelText: 'String, format=email',
    },
    'String: Format idn email': {
      valuePre: 'hello@idn.example.com',
      valuePost: 'hello@idn.example.commando',
      typeThis: 'mando',
      iframeSelector: '#test-string-format-idn-email',
      labelText: 'String, format=idn-email',
    },
    'String: Format uri': {
      valuePre: 'https://uri.example.com',
      valuePost: 'https://uri.example.combination',
      typeThis: 'bination',
      iframeSelector: '#test-string-format-uri',
      labelText: 'String, format=uri',
    },
    'String: Format iri': {
      valuePre: 'https://iri.example.com',
      valuePost: 'https://iri.example.commentary',
      typeThis: 'mentary',
      iframeSelector: '#test-string-format-iri',
      labelText: 'String, format=iri',
    },
  };

  before(() => {
    cy.drupalCanvasInstall(['sdc_test_all_props']);
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded();
    cy.get('.primaryPanelContent').findByText('Two Column').click();
    cy.findByLabelText('Column Width').should('exist');
    cy.openLibraryPanel();
    cy.get('.primaryPanelContent').findByText('All props').click();
    cy.openLayersPanel();
    cy.clickComponentInLayersView('All props');
    cy.findByLabelText('String — single line').should('exist');
  });

  afterEach(() => {
    cy.drupalRelativeURL('');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it(
    'Boolean - default false',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.waitForElementContentInIframe(
        '#test-bool-default-false code',
        'false',
      );
      cy.waitForElementContentNotInIframe(
        '#test-bool-default-false code',
        'true',
      );
      cy.findByLabelText('Bool (default false)')
        .assertToggleState(false)
        .toggleToggle()
        .assertToggleState(true);

      cy.waitForElementContentInIframe('#test-bool-default-false code', 'true');
      cy.waitForElementContentNotInIframe(
        '#test-bool-default-false code',
        'false',
      );
    },
  );

  it('Boolean - default true', () => {
    cy.waitForElementContentInIframe('#test-bool-default-true code', 'true');
    cy.waitForElementContentNotInIframe(
      '#test-bool-default-true code',
      'false',
    );
    cy.findByLabelText('Bool (default true)')
      .assertToggleState(true)
      .toggleToggle()
      .assertToggleState(false);

    cy.waitForElementContentInIframe('#test-bool-default-true code', 'false');
    cy.waitForElementContentNotInIframe('#test-bool-default-true code', 'true');
  });

  it(
    'Single textfields - valid input',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.intercept(
        'PATCH',
        '**/canvas/api/v0/form/component-instance/node/1*',
      ).as('patch');
      Object.entries(textFieldIterations).forEach(([testName, testData]) => {
        cy.log(`Test ${testName}`);
        cy.findByLabelText(testData.labelText).should(
          'have.value',
          testData.valuePre,
        );
        cy.waitForElementContentInIframe(
          testData.iframeSelector,
          testData.valuePre,
        );
        cy.findByLabelText(testData.labelText, { exact: true }).type(
          testData.typeThis,
          { force: true },
        );
        if (
          testData.labelText.includes('uri') ||
          testData.labelText.includes('iri')
        ) {
          // Autocomplete fields that did not select an autocomplete suggestion
          // must be blurred before the store is updated.
          cy.findByLabelText(testData.labelText, { exact: true }).blur();
        }
        cy.wait('@patch', { timeout: 10000 });
        cy.waitForElementContentInIframe(
          testData.iframeSelector,
          testData.valuePost,
        );
      });
    },
  );

  it('Enum (select) - string', { retries: { openMode: 0, runMode: 3 } }, () => {
    cy.findByLabelText('String - Enum')
      .parent()
      .find('select')
      .as('select')
      .should('have.value', 'foo');
    cy.waitForElementContentInIframe('#test-string-enum', 'foo');
    cy.get('@select').within(() => {
      cy.get('option:selected').should('have.text', 'Foo');
    });
    cy.get('@select').select(0, { force: true });
    cy.get('@select').should('have.value', '_none');
    cy.get('@select').within(() => {
      cy.get('option:selected').should('have.text', '- None -');
    });
    cy.waitForElementContentNotInIframe('#test-string-enum', 'foo');
    cy.testInIframe('#test-string-enum code', (enumPreview) => {
      expect(enumPreview.textContent).to.eq('');
    });
    cy.get('@select').select(2, { force: true });
    cy.get('@select').should('have.value', 'bar');
    cy.get('@select').within(() => {
      cy.get('option:selected').should('have.text', 'Bar');
    });
    cy.waitForElementContentInIframe('#test-string-enum', 'bar');

    // See if an empty value is maintained on reload.
    cy.get('@select').select(0, { force: true });
    cy.get('@select').should('have.value', '_none');
    cy.waitForElementContentNotInIframe('#test-string-enum', 'bar');
    cy.loadURLandWaitForCanvasLoaded({ clearAutoSave: false });
    cy.clickComponentInLayersView('All props');
    cy.findByLabelText('String — single line').should('exist');
    cy.findByLabelText('String - Enum').should('have.value', '_none');
    cy.waitForElementContentInIframe(
      '#test-required-string',
      'Hello, required world!',
    );
    cy.waitForElementContentNotInIframe('#test-string-enum', 'bar');
    cy.waitForElementContentNotInIframe('#test-string-enum', 'foo');
  });

  it(
    'Enum (select) - integer',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.findByLabelText('Integer - Enum')
        .parent()
        .find('select')
        .as('select')
        .should('have.value', '1');
      cy.waitForElementContentInIframe('#test-integer-enum', '1');
      cy.get('@select').within(() => {
        cy.get('option:selected').should('have.text', '1');
      });
      cy.get('@select').select(0, { force: true });
      cy.get('@select').should('have.value', '_none');
      cy.get('@select').within(() => {
        cy.get('option:selected').should('have.text', '- None -');
      });
      cy.waitForElementContentNotInIframe('#test-integer-enum', '1');
      cy.testInIframe('#test-integer-enum code', (enumPreview) => {
        expect(enumPreview.textContent).to.eq('');
      });
      cy.get('@select').select(2, { force: true });
      cy.get('@select').should('have.value', '2');
      cy.get('@select').within(() => {
        cy.get('option:selected').should('have.text', '2');
      });
      cy.waitForElementContentInIframe('#test-integer-enum', '2');
    },
  );

  it('Date + Time widget', { retries: { openMode: 0, runMode: 3 } }, () => {
    // @todo these tests confirm that the date+time inputs can be changed and the
    // preview updates in response. It is not yet confirmed if the values found
    // in the form and preview are *correct*. This may require time zone/locale
    // adjustments - do not interpret the presence of this test as evidence that
    // time zone offsets are working as they should.
    const dateSelector =
      '[name$="[test_string_format_date_time][0][value][date]"]';
    const timeSelector =
      '[name$="[test_string_format_date_time][0][value][time]"]';

    cy.get(dateSelector).should('have.value', '2016-09-17');

    cy.get(timeSelector).should('have.value', '06:20:39');
    cy.waitForElementContentInIframe(
      '#test-string-format-date-time',
      '2016-09-16T20:20:39+00:00',
    );

    cy.get(dateSelector).focus();
    cy.realType('628{uparrow}');

    cy.get(timeSelector).focus();
    cy.realType('72135');

    cy.get(dateSelector).should('have.value', '2017-06-28');

    cy.get(timeSelector).should('have.value', '07:21:35');
    cy.waitForElementContentInIframe(
      '#test-string-format-date-time',
      '2017-06-28T07:21:35.000Z',
    );
  });

  it(
    'Individual date and time inputs',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      // @todo The time prop isn't appearing in the form so this is just date
      // for now.
      // @todo these tests confirm that the date+time inputs can be changed and the
      // preview updates in response. It is not yet confirmed if the values found
      // in the form and preview are *correct*. This may require time zone/locale
      // adjustments - do not interpret the presence of this test as evidence that
      // time zone offsets are working as they should.

      const dateSelector =
        '[name$="[test_string_format_date][0][value][date]"]';
      cy.get(dateSelector).should('have.value', '2018-11-12');
      cy.waitForElementContentInIframe(
        '#test-string-format-date',
        '2018-11-13',
      );
      cy.get(dateSelector).clear();
      cy.get(dateSelector).type('2017-06-28');
      cy.waitForElementContentInIframe(
        '#test-string-format-date',
        '2017-06-28',
      );
    },
  );

  it('Integer', { retries: { openMode: 0, runMode: 3 } }, () => {
    cy.findByLabelText('Integer').should('have.value', -42);
    cy.waitForElementContentInIframe('#test-integer', '-42');
    cy.findByLabelText('Integer').clear();
    cy.findByLabelText('Integer').type(12);
    cy.findByLabelText('Integer').should('have.value', 12);
    cy.waitForElementContentInIframe('#test-integer', '12');

    cy.findByLabelText('Integer, minimum=0').should('have.value', 42);
    cy.waitForElementContentInIframe('#test-integer-range-minimum', '42');
    cy.findByLabelText('Integer, minimum=0').clear();
    cy.findByLabelText('Integer, minimum=0').type(55);
    cy.findByLabelText('Integer, minimum=0').should('have.value', 55);
    cy.waitForElementContentInIframe('#test-integer-range-minimum', '55');

    cy.findByLabelText(
      'Integer, minimum=-2147483648, maximum=2147483648',
    ).should('have.value', 1730718000);
    cy.waitForElementContentInIframe(
      '#test-integer-range-minimum-maximum-timestamps',
      '1730718000',
    );
    cy.findByLabelText(
      'Integer, minimum=-2147483648, maximum=2147483648',
    ).clear();
    cy.findByLabelText('Integer, minimum=-2147483648, maximum=2147483648').type(
      543211,
    );
    cy.findByLabelText(
      'Integer, minimum=-2147483648, maximum=2147483648',
    ).should('have.value', 543211);
    cy.waitForElementContentInIframe(
      '#test-integer-range-minimum-maximum-timestamps',
      '543211',
    );
    cy.findByLabelText(
      'Integer, minimum=-2147483648, maximum=2147483648',
    ).clear();
    cy.findByLabelText('Integer, minimum=-2147483648, maximum=2147483648').type(
      2147483648,
    );
    cy.waitForElementContentInIframe(
      '#test-integer-range-minimum-maximum-timestamps',
      '2147483648',
    );
    cy.findByLabelText('Integer, minimum=-2147483648, maximum=2147483648').type(
      '{uparrow}',
    );
    cy.findByLabelText(
      'Integer, minimum=-2147483648, maximum=2147483648',
    ).should('have.value', '2147483648');
    cy.waitForElementContentInIframe(
      '#test-integer-range-minimum-maximum-timestamps',
      '2147483648',
    );
  });

  it('url field', { retries: { openMode: 0, runMode: 3 } }, () => {
    // not sure if this is THE test yet, but it resembles it.
    const previewSelector = '#test-string-format-uri code';
    cy.waitForElementContentInIframe(
      previewSelector,
      'https://uri.example.com',
    );

    cy.findByLabelText('String, format=uri')
      .as('theInput')
      .should('have.value', 'https://uri.example.com');
    cy.get('@theInput').clear({ force: true });
    // Autocomplete fields that did not select an autocomplete suggestion
    // must be blurred before the store is updated.
    cy.get('@theInput').blur();
    cy.get('@theInput')
      .should('have.value', '')
      .then(($el) => $el[0].checkValidity())
      .should('be.true');

    cy.waitForElementContentNotInIframe(
      previewSelector,
      'https://uri.example.com',
    );

    cy.testInIframe(previewSelector, (uriPreview) => {
      expect(uriPreview.textContent.trim()).to.equal('');
    });

    cy.get('@theInput').type('start', { force: true });
    cy.get('@theInput').should('have.value', 'start');
    // Autocomplete fields that did not select an autocomplete suggestion
    // must be blurred before the store is updated.
    cy.get('@theInput').blur();

    cy.testInIframe(previewSelector, (uriPreview) => {
      expect(uriPreview.textContent.trim()).to.equal('');
    });

    cy.get('button').first().focus();
    cy.get('@theInput').should('have.attr', 'data-invalid-prop-value');
    cy.get('[data-prop-message]')
      .should('have.length', 1)
      .should('have.text', '❌ data must match format "uri"');
  });

  it('idn-email', { retries: { openMode: 0, runMode: 3 } }, () => {
    const previewSelector = '#test-string-format-idn-email code';
    const initialValue = 'hello@idn.example.com';
    cy.waitForElementContentInIframe(previewSelector, initialValue);

    cy.findByLabelText('String, format=idn-email')
      .as('theInput')
      .should('have.value', initialValue);
    cy.get('@theInput').clear();
    cy.get('@theInput')
      .should('have.value', '')
      .then(($el) => $el[0].checkValidity())
      .should('be.true');

    cy.waitForElementContentNotInIframe(previewSelector, initialValue);

    cy.testInIframe(previewSelector, (preview) => {
      expect(preview.textContent.trim()).to.equal('');
    });

    cy.get('@theInput').type('not-email');
    cy.get('@theInput').should('have.value', 'not-email');

    cy.testInIframe(previewSelector, (preview) => {
      expect(preview.textContent.trim()).to.equal('');
    });

    cy.get('button').first().focus();
    cy.get('@theInput').should('have.attr', 'data-invalid-prop-value');
    cy.get('[data-prop-message]')
      .should('have.length', 1)
      .should('have.text', '❌ data must match format "idn-email"');
  });

  it('String, format=email', { retries: { openMode: 0, runMode: 3 } }, () => {
    const previewSelector = '#test-string-format-email code';
    const initialValue = 'hello@example.com';
    cy.waitForElementContentInIframe(previewSelector, initialValue);

    cy.findByLabelText('String, format=email')
      .as('theInput')
      .should('have.value', initialValue);
    cy.get('@theInput').clear();
    cy.get('@theInput')
      .should('have.value', '')
      .then(($el) => $el[0].checkValidity())
      .should('be.true');

    cy.waitForElementContentNotInIframe(previewSelector, initialValue);

    cy.testInIframe(previewSelector, (preview) => {
      expect(preview.textContent.trim()).to.equal('');
    });

    cy.get('@theInput').type('not-email');
    cy.get('@theInput').should('have.value', 'not-email');

    cy.testInIframe(previewSelector, (preview) => {
      expect(preview.textContent.trim()).to.equal('');
    });

    cy.get('button').first().focus();
    cy.get('@theInput').should('have.attr', 'data-invalid-prop-value');
    cy.get('[data-prop-message]')
      .should('have.length', 1)
      .should('have.text', '❌ data must match format "email"');
  });

  it(
    'String, format=uri-reference',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      const previewSelector = '#test-string-format-uri-reference code';
      const initialValue = '/example-uri';
      cy.waitForElementContentInIframe(previewSelector, initialValue);

      cy.findByLabelText('String, format=uri-reference')
        .as('theInput')
        .should('have.value', initialValue);
      cy.get('@theInput').clear({ force: true });
      // Autocomplete fields that did not select an autocomplete suggestion
      // must be blurred before the store is updated.
      cy.get('@theInput').blur();
      cy.get('@theInput')
        .should('have.value', '')
        .then(($el) => $el[0].checkValidity())
        .should('be.true');

      cy.waitForElementContentNotInIframe(previewSelector, initialValue);

      cy.testInIframe(previewSelector, (preview) => {
        expect(preview.textContent.trim()).to.equal('');
      });

      cy.get('@theInput').focus();
      cy.realType('not');
      // Autocomplete fields that did not select an autocomplete suggestion
      // must be blurred before the store is updated.
      cy.get('@theInput').blur();
      cy.get('@theInput').should('have.value', 'not');

      cy.testInIframe(previewSelector, (preview) => {
        expect(preview.textContent.trim()).to.equal('');
      });

      // @todo HTML5 validation works IRL but not in these tests.
      // Fortunately we are still confirming values in an invalid format does not
      // result in errors.

      cy.get('@theInput').clear({ force: true });
      cy.get('@theInput').focus();
      cy.realType('/whatever');
      // Autocomplete fields that did not select an autocomplete suggestion
      // must be blurred before the store is updated.
      cy.get('@theInput').blur();

      cy.waitForElementContentInIframe(previewSelector, '/whatever');
    },
  );

  it(
    'String, format=iri-reference',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      const previewSelector = '#test-string-format-iri-reference code';
      const initialValue = '/example-iri';
      cy.waitForElementContentInIframe(previewSelector, initialValue);

      cy.findByLabelText('String, format=iri-reference')
        .as('theInput')
        .should('have.value', initialValue);
      cy.findByLabelText('String, format=iri-reference').clear({ force: true });
      // Autocomplete fields that did not select an autocomplete suggestion
      // must be blurred before the store is updated.
      cy.findByLabelText('String, format=iri-reference').blur();
      cy.findByLabelText('String, format=iri-reference')
        .should('have.value', '')
        .then(($el) => $el[0].checkValidity())
        .should('be.true');

      cy.waitForElementContentNotInIframe(previewSelector, initialValue);

      cy.testInIframe(previewSelector, (preview) => {
        expect(preview.textContent.trim()).to.equal('');
      });

      cy.get('@theInput').focus();
      cy.realType('not');
      // Autocomplete fields that did not select an autocomplete suggestion
      // must be blurred before the store is updated.
      cy.get('@theInput').blur();
      cy.get('@theInput').should('have.value', 'not');

      cy.testInIframe(previewSelector, (preview) => {
        expect(preview.textContent.trim()).to.equal('');
      });

      // @todo HTML5 validation works IRL but not in these tests.
      // Fortunately we are still confirming values in an invalid format does not
      // result in errors.

      cy.get('@theInput').clear({ force: true });
      cy.get('@theInput').focus();
      cy.realType('/whatever');
      // Autocomplete fields that did not select an autocomplete suggestion
      // must be blurred before the store is updated.
      cy.get('@theInput').blur();

      cy.waitForElementContentInIframe(previewSelector, '/whatever');
    },
  );

  it(
    'can enter number into a text field',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      const iframeSelector = '#test-string';
      const labelText = 'String — single line';
      const valuePre = 'Hello, world!';
      const valuePost = '1999';
      cy.waitForElementContentInIframe(iframeSelector, valuePre);
      cy.findByLabelText(labelText).should('have.value', valuePre);
      cy.findByLabelText(labelText).clear({ force: true });
      cy.findByLabelText(labelText).type(valuePost);
      cy.waitForElementContentNotInIframe(iframeSelector, valuePre);
      cy.waitForElementContentInIframe(iframeSelector, valuePost);
    },
  );

  it('can enter just a space into a text field', () => {
    const iframeSelector = '#test-string';
    const labelText = 'String — single line';
    const valuePre = 'Hello, world!';
    const valuePost = ' ';
    cy.waitForElementContentInIframe(iframeSelector, valuePre);
    cy.findByLabelText(labelText).should('have.value', valuePre);
    cy.findByLabelText(labelText).clear({ force: true });
    cy.findByLabelText(labelText).type(valuePost);
    cy.waitForElementContentNotInIframe(iframeSelector, valuePre);
    cy.waitForElementContentInIframe(iframeSelector, valuePost);
  });

  it('can empty an optional text field and it is saved that way', () => {
    const iframeSelector = '#test-string';
    const labelText = 'String — single line';
    const valuePre = 'Hello, world!';
    cy.waitForElementContentInIframe(iframeSelector, valuePre);
    cy.findByLabelText(labelText).should('have.value', valuePre);
    cy.findByLabelText(labelText).clear({ force: true });
    cy.waitForElementContentNotInIframe(iframeSelector, valuePre);
    cy.loadURLandWaitForCanvasLoaded({ clearAutoSave: false });
    cy.clickComponentInLayersView('All props');
    cy.findByLabelText('String — single line').should('exist');
    cy.waitForElementContentInIframe(
      '#test-required-string',
      'Hello, required world!',
    );
    cy.waitForElementContentNotInIframe(iframeSelector, valuePre);
  });

  it(
    'HTML block formatting field uses CKEditor with appropriate configuration',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded();
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });

      cy.openLibraryPanel();
      cy.get('.primaryPanelContent').findByText('All props').click();
      cy.findByLabelText('String with HTML formatting (block)').should('exist');

      const selector = '#test-string-html-block';
      const initialHtml =
        '<p>This is a paragraph with <strong>bold</strong> text.</p><ul><li>List item 1</li><li>List item 2</li></ul>';
      const id = '[id$="test-string-html-block-wrapper"]';

      // Verify the content is showing up correctly in preview
      cy.waitForElementHTMLInIframe(selector, initialHtml);

      // Open the field and verify CKEditor is loaded
      cy.findByLabelText('String with HTML formatting (block)').click({
        force: true,
      });

      // Check that CKEditor 5 is loaded
      cy.get(`${id} .ck-editor__editable`).should('exist');
      cy.get(`${id} .ck-toolbar`).should('exist');

      // Verify toolbar has both inline and block formatting buttons.
      // CKEditor uses OS shortcuts. That's Bold (⌘B) for Mac, but Bold (Ctrl+B)
      // for e.g. Windows. So we only look for the actual word and no shortcut
      // for those that have shortcuts.
      cy.get(
        `${id} .ck-toolbar__items button[data-cke-tooltip-text^="Bold"]`,
      ).should('exist');
      cy.get(
        `${id} .ck-toolbar__items button[data-cke-tooltip-text^="Italic"]`,
      ).should('exist');
      cy.get(
        `${id} .ck-toolbar__items button[data-cke-tooltip-text^="Underline"]`,
      ).should('exist');
      cy.get(
        `${id} .ck-toolbar__items button[data-cke-tooltip-text^="Link"]`,
      ).should('exist');
      cy.get(
        `${id} .ck-toolbar__items button[data-cke-tooltip-text="Bulleted List"]`,
      ).should('exist');
      cy.get(
        `${id} .ck-toolbar__items button[data-cke-tooltip-text="Numbered List"]`,
      ).should('exist');

      // Verify text format configuration
      cy.window().then((win) => {
        const editorConfig =
          win.drupalSettings?.editor.formats.canvas_html_block.editorSettings;
        expect(editorConfig).to.exist;
        expect(editorConfig.toolbar.items).to.include(
          'bold',
          'italic',
          'underline',
          'link',
          'bulletedList',
          'numberedList',
        );
      });

      // Add some content and verify it updates in preview
      cy.get(`${id} .ck-editor__editable`).clear({ force: true });
      cy.get(`${id} .ck-editor__editable`).realType('A paragraph{enter}');
      cy.get(
        `${id} .ck-toolbar__items button[data-cke-tooltip-text="Bulleted List"]`,
      ).click();
      cy.get(`${id} .ck-editor__editable`).realType('A list item');
      cy.log('type all done');
      cy.get('label').first().click({ force: true }); // Blur the editor
      cy.log('blurred the editor');
      cy.waitForElementHTMLInIframe(
        selector,
        '<p>A paragraph</p><ul><li>A list item</li></ul>',
      );
    },
  );

  it('Select prop with _none', () => {
    cy.loadURLandWaitForCanvasLoaded();
    cy.openLibraryPanel();
    cy.get('.primaryPanelContent').findByText('Heading').click();
    cy.findByLabelText('Style').should('have.value', 'primary');
    cy.findByLabelText('Style').within(() => {
      cy.get('option:selected').should('have.text', 'Primary');
    });
    cy.findByLabelText('Style').select('_none');
    cy.findByLabelText('Style').should('have.value', '_none');
    cy.findByLabelText('Style').within(() => {
      cy.get('option:selected').should('have.text', '- None -');
    });
    cy.findByLabelText('Style').should(
      'not.have.attr',
      'data-invalid-prop-value',
    );
    cy.get('[data-prop-message="true"]').should('not.exist');
  });
});
