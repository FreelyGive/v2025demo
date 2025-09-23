describe('Drupal Canvas editor frame controls/navigation', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  const roundValue = (value) => Math.round(value);

  it('Can zoom the editor frame with the Zoom Controls and keyboard', () => {
    cy.loadURLandWaitForCanvasLoaded();
    // Confirm that no component has a hover outline initially.
    cy.get('[data-canvas-component-outline]').should('not.exist');

    // Hover over a component to trigger the outline and get its bounding rect.
    cy.getIframeBody()
      .find('[data-component-id="canvas_test_sdc:my-hero"] h1')
      .first()
      .then(($h1) => {
        const element = $h1;
        cy.wrap(element).trigger('mouseover');
        cy.wrap(element)
          .closest('[data-canvas-uuid]')
          .then(($item) => {
            const rect = $item[0].getBoundingClientRect();
            cy.wrap(rect).as('initialComponentRect');
          });
      });

    // Verify the initial outline matches the component's size.
    cy.get('@initialComponentRect').then((initialComponentRect) => {
      cy.getComponentInPreview('Hero').should(($outline) => {
        expect($outline).to.exist;
        const outlineRect = $outline[0].getBoundingClientRect();
        expect(outlineRect.width).to.equal(initialComponentRect.width);
        expect(outlineRect.height).to.equal(initialComponentRect.height);
      });
    });

    cy.log('Zoom by pressing the + key');
    cy.get('html').realType('+');

    cy.findByTestId('canvas-editor-frame-scaling').should(
      'have.css',
      'transform',
      'matrix(1.1, 0, 0, 1.1, 0, 0)',
    );
    cy.findByTestId('canvas-editor-frame-controls').findByText('110%');

    // Re-hover over the component after zoom change and get its new bounding rect.
    cy.getIframeBody()
      .find('[data-component-id="canvas_test_sdc:my-hero"] h1')
      .first()
      .then(($h1) => {
        cy.wrap($h1).trigger('mouseover');
        cy.wrap($h1)
          .closest('[data-canvas-uuid]')
          .then(($item) => {
            // Calculate component height and width by scale value - as by default component's dimensions are not changing on zoom
            cy.getElementScaledDimensions($item[0]).then((dimensions) => {
              const zoomedComponentRect = $item[0].getBoundingClientRect();
              zoomedComponentRect.width = roundValue(dimensions.width);
              zoomedComponentRect.height = roundValue(dimensions.height);
              cy.wrap(zoomedComponentRect).as('zoomedComponentRect');
            });
          });
      });

    // Verify the outline matches the zoomed component's size.
    cy.get('@zoomedComponentRect').then((zoomedComponentRect) => {
      cy.getComponentInPreview('Hero').should(($outline) => {
        expect($outline).to.exist;
        const outlineRectAfterZoom = $outline[0].getBoundingClientRect();
        // Compare the outline dimensions with the zoomed component's dimensions.
        expect(roundValue(outlineRectAfterZoom.width)).to.equal(
          zoomedComponentRect.width,
        );
        expect(roundValue(outlineRectAfterZoom.height)).to.equal(
          zoomedComponentRect.height,
        );
      });
    });

    cy.get('html').realType('+');
    cy.findByTestId('canvas-editor-frame-scaling').should(
      'have.css',
      'transform',
      'matrix(1.25, 0, 0, 1.25, 0, 0)',
    );
    cy.findByTestId('canvas-editor-frame-controls').findByText('125%');
    cy.get('html').realType('-');

    // Re-hover over the component again after zoom-out.
    cy.getIframeBody()
      .find('[data-component-id="canvas_test_sdc:my-hero"] h1')
      .first()
      .then(($h1) => {
        cy.wrap($h1).trigger('mouseover', { force: true });
        cy.wrap($h1)
          .closest('[data-canvas-uuid]')
          .then(($item) => {
            // Calculate component height and width by scale value - as by default component's dimensions are not changing on zoom
            cy.getElementScaledDimensions($item[0]).then((dimensions) => {
              const resetOutlineRect = $item[0].getBoundingClientRect();
              resetOutlineRect.width = roundValue(dimensions.width);
              resetOutlineRect.height = roundValue(dimensions.height);
              cy.wrap(resetOutlineRect).as('resetOutlineRect');
            });
          });
      });

    // Verify the outline size matches the original size after zoom-out.
    cy.get('@resetOutlineRect').then((resetOutlineRect) => {
      cy.getComponentInPreview('Hero').should(($outline) => {
        expect($outline).to.exist;
        const outlineRect = $outline[0].getBoundingClientRect();
        // Assert that the outline is equal to the size of the element after zoom-out (back to 100%).
        expect(roundValue(outlineRect.width)).to.equal(resetOutlineRect.width);
        expect(roundValue(outlineRect.height)).to.equal(
          resetOutlineRect.height,
        );
      });
    });
    cy.findByTestId('canvas-editor-frame-scaling').should(
      'have.css',
      'transform',
      'matrix(1.1, 0, 0, 1.1, 0, 0)',
    );
    cy.findByTestId('canvas-editor-frame-controls').findByText('110%');

    cy.log(
      "The selected value in the drop down should match the zoom level if it's one of the available steps",
    );
    cy.findByLabelText('Select zoom level').click();
    cy.findByTestId('zoom-select-menu')
      .get('[role="option"][aria-selected="true"]')
      .should('have.text', '110%');
    cy.get('html').click(); // close the select menu

    Array(4)
      .fill()
      .forEach(() => {
        cy.get('html').realType('-');
      });
    cy.findByTestId('canvas-editor-frame-scaling').should(
      'have.css',
      'transform',
      'matrix(0.75, 0, 0, 0.75, 0, 0)',
    );
    cy.findByTestId('canvas-editor-frame-controls').findByText('75%');
  });

  it('Can zoom the editor frame with the mouse', () => {
    cy.loadURLandWaitForCanvasLoaded();

    cy.log(
      'Zoom in by holding ctrl and using the mousewheel (or pinch on track pad)',
    );

    cy.findByTestId('canvas-editor-frame').click({ force: true });
    cy.findByTestId('canvas-editor-frame').triggerMouseWheelWithCtrl(-20); // Simulate mouse wheel roll with ctrl.

    cy.findByTestId('canvas-editor-frame-scaling').should(
      'have.css',
      'transform',
      'matrix(1.1, 0, 0, 1.1, 0, 0)',
    );
    cy.findByTestId('canvas-editor-frame-controls').findByText('110%');

    cy.log(
      'Zoom out (twice) by holding ctrl and using the mousewheel (or pinch on track pad)',
    );

    cy.findByTestId('canvas-editor-frame').click({ force: true });
    // Zoom once, back to 100%.
    cy.findByTestId('canvas-editor-frame').triggerMouseWheelWithCtrl(20); // Simulate mouse wheel roll with ctrl.

    // wait here because the scroll event is throttled to 50ms. Waiting 200ms just to give some extra headroom.
    // See handleWheel and wheelEventBufferTimeMs in EditorFrame.ts.
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(200);

    // Zoom out second time, to 90%.
    cy.findByTestId('canvas-editor-frame').triggerMouseWheelWithCtrl(20); // Simulate mouse wheel roll with ctrl.

    cy.findByTestId('canvas-editor-frame-scaling').should(
      'have.css',
      'transform',
      'matrix(0.9, 0, 0, 0.9, 0, 0)',
    );
    cy.findByTestId('canvas-editor-frame-controls').findByText('90%');
  });
});
