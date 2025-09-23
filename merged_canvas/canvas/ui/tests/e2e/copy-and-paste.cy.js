describe('Copy and paste a node using keyboard shortcuts', () => {
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

  it('Copy and paste a node using keyboard shortcuts', () => {
    // Transfer message listener from Cypress window to the Canvas application
    // window.
    cy.window().then((win) => {
      win.top.addEventListener('message', (e) => {
        win.postMessage(e.data, '*');
      });
    });

    cy.loadURLandWaitForCanvasLoaded();
    cy.clearLocalStorage();
    cy.getIframeBody().findAllByText('hello, world!').should('have.length', 1);

    // text occurs 3 times (one is the page title)
    cy.getIframeBody()
      .findAllByText('Canvas Needs This For The Time Being')
      .should('have.length', 3);

    cy.log('Click and copy the first Hero component.');
    cy.clickComponentInPreview('Hero', 0);
    cy.realPress(['Meta', 'c']);

    cy.getAllLocalStorage().then((ls) => {
      expect(Object.keys(ls).length).to.equal(1);
    });

    cy.log(
      'Delete all the heroes including the one we just copied to ensure you can still paste it.',
    );
    cy.getComponentInPreview('Hero', 2).trigger('contextmenu');
    cy.findByText('Delete').click();

    cy.getComponentInPreview('Hero', 1).trigger('contextmenu');
    cy.findByText('Delete').click();

    cy.getComponentInPreview('Hero', 0).trigger('contextmenu');
    cy.findByText('Delete').click();
    cy.waitForElementContentNotInIframe('div', 'hello, world!');
    cy.getIframeBody()
      .findAllByText('Canvas Needs This For The Time Being')
      .should('have.length', 1);

    cy.log(
      'Select the Image component and then paste the Hero we copied after it.',
    );
    cy.clickComponentInPreview('Test SDC Image', 1);
    cy.realPress(['Meta', 'v']);

    cy.log(
      'The Hero we copied had the text hello, world! so that should have been pasted, not a different Hero.',
    );

    cy.waitForElementContentInIframe('div', 'hello, world!');
    cy.getIframeBody().findAllByText('hello, world!').should('have.length', 1);

    // this text should now occur only once (the page title)
    cy.getIframeBody()
      .findAllByText('Canvas Needs This For The Time Being')
      .should('have.length', 1);

    cy.log('The Hero that was pasted should be selected');
    cy.getComponentInPreview('Hero', 0)
      .invoke('attr', 'class')
      .then((classList) => {
        expect(classList).to.include('selected');
      });

    cy.log('Refresh the page and confirm the component can still be pasted');
    cy.loadURLandWaitForCanvasLoaded();

    cy.getAllLocalStorage().then((ls) => {
      expect(Object.keys(ls).length).to.equal(1);
    });

    cy.getIframeBody().findAllByText('hello, world!').should('have.length', 1);

    cy.clickComponentInPreview('Hero', 2);

    cy.realPress(['Meta', 'v']);
    cy.waitForElementInIframe('.column-two [data-canvas-uuid]:nth-child(4)');
    cy.getIframeBody().findAllByText('hello, world!').should('have.length', 2);
  });

  it('Copy and paste a node with children using keyboard shortcuts', () => {
    cy.loadURLandWaitForCanvasLoaded();
    cy.clearLocalStorage();

    cy.clickComponentInLayersView('Two Column');
    cy.realPress(['Meta', 'c']);
    cy.realPress(['Meta', 'v']);
    cy.log('The Two Column that was pasted should be selected');
    cy.getComponentInPreview('Two Column', 1)
      .invoke('attr', 'class')
      .then((classList) => {
        expect(classList).to.include('selected');
      });
    cy.realPress(['Meta', 'v']);
    cy.log('The Two Column that was pasted should be selected');
    cy.getComponentInPreview('Two Column', 2)
      .invoke('attr', 'class')
      .then((classList) => {
        expect(classList).to.include('selected');
      });
  });
});
