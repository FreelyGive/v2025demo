describe('Multivalue widget drag and drop', () => {
  // Note that more extensive add/remove item testing is covered by
  // entity-form-field-types-test.cy.js and this test is only about drag and
  // drop support.
  before(() => {
    cy.drupalCanvasInstall(['canvas_test_article_fields']);
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('can use a multivalue widget in the page data form', () => {
    cy.loadURLandWaitForCanvasLoaded();
    cy.findByTestId('canvas-page-data-form').as('entityForm');
    cy.get('@entityForm').recordFormBuildId();
    cy.findByRole('heading', { name: 'Canvas Unlimited Text' })
      .parents('.js-form-wrapper')
      .as('unlimited-text');

    // Confirms the count and visibility of the weight select dropdowns.
    // This is run many times during the test as these elements being visible
    // when they shouldn't be is a useful canary for identifying AJAX problems.
    const confirmWeightSelectCount = (count, visible = false) => {
      cy.get('@unlimited-text')
        .get('.delta-order select')
        .should(($selects) => {
          expect($selects).to.have.length(count);
          $selects.each((index, weightSelect) => {
            expect(Cypress.$(weightSelect).is(':visible')).to.equal(visible);
          });
        });
    };

    // Confirms the contents of every text input in the table.
    const confirmTextInputs = (inputContent) => {
      cy.get('@unlimited-text')
        .findAllByRole('textbox')
        .then(($items) => {
          const items = [];
          $items.each((ix, el) => {
            items.push(el.value);
          });
          expect(items).to.deep.equal(inputContent);
        });
    };
    cy.get('@unlimited-text').findAllByRole('textbox').should('have.length', 2);
    confirmTextInputs(['Marshmallow Coast', '']);
    // Populate the empty second item.
    cy.get('@unlimited-text')
      .findAllByRole('textbox')
      .eq(1)
      .type('Neutral Milk Hotel');
    confirmTextInputs(['Marshmallow Coast', 'Neutral Milk Hotel']);

    // Adding another item puts the focus on the last added item. Blurring this
    // item will trigger an update to the layout. Do this before we click the
    // add another button so we can intercept and wait for the blur POST before
    // we click the button. If the button is clicked while the blur POST is
    // running we could have a race scenario where the form state is missing
    // whilst the AJAX call is running.
    // @todo Remove in https://drupal.org/i/3521641
    const waitForPreview = () => {
      cy.intercept({
        url: '**/canvas/api/v0/layout/node/1',
        times: 1,
        method: 'POST',
      }).as('updatePreview');
      // Trigger a blur.
      cy.get(document.activeElement).blur();
      cy.wait('@updatePreview');
    };

    // Add another item.
    waitForPreview();
    cy.get('@unlimited-text')
      .findByRole('button', { name: 'Add another item' })
      .click();
    cy.get('@entityForm').shouldHaveUpdatedFormBuildId();
    // Wait for ajax behaviors to finish.
    cy.get('body[data-canvas-ajax-behaviors="true"]').should('not.exist');
    cy.get('@unlimited-text').findAllByRole('textbox').should('have.length', 3);

    // Populate the new item.
    cy.get('@unlimited-text')
      .findAllByRole('textbox')
      .eq(2)
      .type('The Olivia Tremor Control');
    confirmWeightSelectCount(3);
    waitForPreview();
    cy.waitForAjax();

    confirmTextInputs([
      'Marshmallow Coast',
      'Neutral Milk Hotel',
      'The Olivia Tremor Control',
    ]);

    cy.log('Move "item 3" to position 2');
    // Ensure the drop target is in the viewport.
    cy.get('@unlimited-text').findAllByRole('textbox').eq(0).scrollIntoView();

    const dndDefaults = {
      position: 'topLeft',
      // Passing false here prevents scrolling the item into view before
      // calculating the element position. The default scroll behavior is 'top'
      // so scrolling each of the items into view before calculating their
      // position means they end up with the identical position. This is a
      // shortcoming in getCypressElementCoordinates. The only way to prevent
      // this is to pass false as scroll behavior. Playwright has native
      // support for drag and drop so hopefully this becomes irrelevant when we
      // move to it from Cypress.
      scrollBehavior: false,
    };
    cy.get(
      '[data-drupal-selector="edit-field-cvt-unlimited-text"] tr.draggable:nth-child(3) [title="Change order"]',
    ).realDnd('input[value="Neutral Milk Hotel"]', dndDefaults);

    confirmTextInputs([
      'Marshmallow Coast',
      'The Olivia Tremor Control',
      'Neutral Milk Hotel',
    ]);
    confirmWeightSelectCount(3);
    cy.get('@unlimited-text')
      .findByRole('button', { name: 'Add another item' })
      .click();
    cy.get('@entityForm').shouldHaveUpdatedFormBuildId();
    // Wait for ajax behaviors to finish.
    cy.get('body[data-canvas-ajax-behaviors="true"]').should('not.exist');
    cy.get('@unlimited-text').findAllByRole('textbox').should('have.length', 4);

    cy.log(
      'Move an item that has been entered but "Add new item" is not clicked yet',
    );

    cy.get('@unlimited-text')
      .findAllByRole('textbox')
      .eq(3)
      .type('The Music Tapes');
    waitForPreview();
    cy.waitForAjax();

    confirmTextInputs([
      'Marshmallow Coast',
      'The Olivia Tremor Control',
      'Neutral Milk Hotel',
      'The Music Tapes',
    ]);
    confirmWeightSelectCount(4);

    // Reloading should re-instate the previous form values.
    cy.reload();
    cy.previewReady();
    confirmTextInputs([
      'Marshmallow Coast',
      'The Olivia Tremor Control',
      'Neutral Milk Hotel',
      'The Music Tapes',
      // Reloading should append a new empty item.
      '',
    ]);

    // Ensure the drop target is in the viewport.
    cy.get('@unlimited-text').findAllByRole('textbox').eq(0).scrollIntoView();
    cy.get(
      '[data-drupal-selector="edit-field-cvt-unlimited-text"] tr.draggable:nth-child(4) [title="Change order"]',
    ).realDnd('input[value="Neutral Milk Hotel"]', dndDefaults);
    cy.get('@unlimited-text')
      .findAllByRole('textbox')
      .eq(2)
      .should('have.value', 'The Music Tapes');

    confirmTextInputs([
      'Marshmallow Coast',
      'The Olivia Tremor Control',
      'The Music Tapes',
      'Neutral Milk Hotel',
      '',
    ]);
    confirmWeightSelectCount(5);

    cy.findByText('Hide row weights').should('not.exist');
    cy.findByText('Show row weights').click();
    cy.findByText('Hide row weights').should('exist');

    confirmWeightSelectCount(5, true);

    cy.get('@unlimited-text')
      .get('[title="Change order"]')
      .should(($handles) => {
        expect($handles).to.have.length(5);
        $handles.each((index, handle) => {
          expect(
            Cypress.$(handle).is(':visible'),
            `Drag handle ${index} is hidden`,
          ).to.be.false;
        });
      });
  });
});
