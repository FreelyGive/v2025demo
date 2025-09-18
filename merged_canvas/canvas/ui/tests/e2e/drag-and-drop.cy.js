// WARNING: describe.skip() is used to ignore this spec.
// @todo Rewrite in Playwright! See #3473617: Write end-to-end test for dragging and dropping components
// https://www.drupal.org/project/canvas/issues/3473617
// eslint-disable-next-line mocha/no-pending-tests
describe.skip('Drag and drop functionality in the Layers menu', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  function preparePage() {
    cy.loadURLandWaitForCanvasLoaded();

    cy.get('#canvasPreviewOverlay .canvas--viewport-overlay')
      .first()
      .as('desktopPreviewOverlay');
    cy.get('.primaryPanelContent').as('layersTree');

    // TODO don't even have this image here in the first place! For now, we delete it
    cy.clickComponentInPreview('Image', 1);
    cy.realType('{del}');

    // Open the layers in the Tree.
    cy.get('@layersTree')
      .findByLabelText('Two Column')
      .findByLabelText('Expand component tree')
      .click();
    cy.get('@layersTree')
      .findAllByLabelText('Column One')
      .first()
      .findByLabelText('Expand slot')
      .click();
    cy.get('@layersTree').findAllByText('Image').should('be.visible');
    cy.get('@layersTree').findAllByText('Hero').should('be.visible');
  }

  function assertInitialPageState() {
    // Before dragging, check that the component is in the column one slot in the layers menu and preview.
    cy.log('Image component exists in the first slot in the layers panel');
    cy.get('@layersTree').within(() => {
      cy.findAllByText('Column One')
        .first()
        .closest('.canvas--collapsible-root')
        .within(() => {
          cy.findAllByText('Image');
        });
    });

    cy.log('Image component exists in the first slot in the overlay UI');
    cy.get('@desktopPreviewOverlay').within(() => {
      cy.findByLabelText('Column One (Two Column)').within(() => {
        cy.findByLabelText('Image');
      });
    });
  }

  function assertPageStateAfterFirstDrag() {
    cy.log(
      'Image component no longer exists in the first slot in the layers panel and is now a sibling of Two Column',
    );
    cy.get('@layersTree').within(() => {
      cy.findAllByText('Column One')
        .first()
        .within(() => {
          cy.findAllByText('Image').should('not.exist');
        });
      cy.checkSiblings(
        cy.findByLabelText('Two Column'),
        cy.findByLabelText('Image'),
      );
    });

    cy.log(
      'Image component no longer exists in the first slot in the overlay UI and is now a sibling of Two Column',
    );
    cy.get('@desktopPreviewOverlay').within(() => {
      cy.findByLabelText('Column One (Two Column)').within(() => {
        cy.findByLabelText('Image').should('not.exist');
      });
      cy.checkSiblings(
        cy.findByLabelText('Two Column'),
        cy.findByLabelText('Image'),
      );
    });
  }

  it('Drag a component from the column one slot to the root level then to the column two slot', () => {
    preparePage();
    assertInitialPageState();

    cy.log('Drag image component out of the slot and to the root level.');
    cy.get('.primaryPanelContent span').contains('Image').should('exist');
    cy.get('@layersTree').within(() => {
      cy.get('#layer-static-image-udf7d-name span').realDnd(
        '[data-canvas-uuid="two-column-uuid"]',
        {
          position: 'top',
          preReleaseWait: 500,
        },
      );
    });
    assertPageStateAfterFirstDrag();
    // This is the "Image" inside the layers tree
    cy.get(
      // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
      '.primaryPanelContent [data-canvas-uuid="8f6780cd-7b64-499e-9545-321a14951a0d"][data-state]',
    ).should('exist');
    cy.get(
      // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
      '.primaryPanelContent [data-canvas-uuid="16176e0b-8197-40e3-ad49-48f1b6e9a7f9/column_two"] [aria-label="Expand slot"]',
    ).should('exist');
    cy.get(
      // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
      '.primaryPanelContent [data-canvas-uuid="16176e0b-8197-40e3-ad49-48f1b6e9a7f9/column_two"] [aria-label="Expand slot"]',
    ).click();
    // This confirms the slot is expanded.
    cy.get(
      // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
      '.primaryPanelContent [data-canvas-uuid="4d866c38-7261-45c6-9b1e-0b94096d51e8"]',
    ).should('exist');

    // Next, drag the image component from the root level to column two's slot.
    cy.get(
      // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
      '.primaryPanelContent [data-canvas-uuid="8f6780cd-7b64-499e-9545-321a14951a0d"][data-state]',
    ).realDnd(
      // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
      '.primaryPanelContent [data-canvas-uuid="16176e0b-8197-40e3-ad49-48f1b6e9a7f9/column_two"][data-canvas-type="slot"]',
      {
        position: 'center',
        scrollBehavior: false,
        force: true,
      },
    );

    // After dragging, check that the image is now in column two's slot in the layers menu and preview.
    cy.log('Image component exists in the second slot in the layers panel');
    cy.get(
      // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
      '.primaryPanelContent [data-canvas-slot-id="16176e0b-8197-40e3-ad49-48f1b6e9a7f9/column_two"] span',
    )
      .contains('Image')
      .should('have.length', 1);

    cy.log('Image component exists in the column_two slot in the overlay UI');
    // Ensure there is only one Image in each preview and we didn't clone it or anything!
    cy.get(
      '#canvasPreviewOverlay .canvas--viewport-overlay [aria-label="Column Two (Two Column)"] [data-canvas-component-id="sdc.canvas_test_sdc.image"]',
    ).should('have.length', 2);
  });

  it('Check undo/redo works with the layers menu', () => {
    preparePage();
    assertInitialPageState();

    cy.log('Drag image component out of the slot and to the root level.');
    cy.get('@layersTree').within(() => {
      cy.get('#layer-static-image-udf7d-name span').realDnd(
        '[data-canvas-slot-id="content"]',
        {
          position: 'top',
          preReleaseWait: 900,
        },
      );
    });
    assertPageStateAfterFirstDrag();

    // Hit the undo button.
    cy.realPress(['Meta', 'Z']);
    assertInitialPageState();

    // Hit Redo
    cy.realPress(['Meta', 'Shift', 'Z']);
    assertPageStateAfterFirstDrag();
  });
});
