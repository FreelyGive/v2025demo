describe('Vh units should not cause issues', () => {
  before(() => {
    cy.drupalCanvasInstall(['canvas_test_vh_preview']);
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('does not continually increase the height of the iframe when there are elements that have height defined in vh units', () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
    cy.openLibraryPanel();
    cy.get('.primaryPanelContent').findByText('Hero').click();
    cy.get('.primaryPanelContent').findByText('VH Half').click();
    cy.get('.primaryPanelContent').findByText('VH Full').click();
    cy.waitForElementInIframe('[data-div="vh-half"]');
    cy.waitForElementInIframe('#vh-full');
    cy.testInIframe('[data-div="vh-half"]', (vhDiv) => {
      expect(vhDiv.getBoundingClientRect().height).to.be.closeTo(384, 10);
    });
    cy.testInIframe('#vh-full', (vhDiv) => {
      expect(vhDiv.getBoundingClientRect().height).to.be.closeTo(768, 10);
    });

    // Intentionally wait two seconds to ensure the heights of the VH styled
    // styled elements have not changed.
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(2000);
    cy.testInIframe('[data-div="vh-half"]', (vhDiv) => {
      expect(vhDiv.getBoundingClientRect().height).to.be.closeTo(384, 10);
    });
    cy.testInIframe('#vh-full', (vhDiv) => {
      expect(vhDiv.getBoundingClientRect().height).to.be.closeTo(768, 10);
    });

    // Edit a component to ensure the VH styled elements do not increase in
    // size when the preview updates.
    cy.clickComponentInPreview('Hero');
    cy.findByLabelText('Heading').type('{selectall}{del}');
    cy.findByLabelText('Heading').type('NO GROW');
    cy.waitForElementContentInIframe('.my-hero__heading', 'NO GROW');
    cy.testInIframe('[data-div="vh-half"]', (vhDiv) => {
      expect(vhDiv.getBoundingClientRect().height).to.be.closeTo(384, 10);
    });
    cy.testInIframe('#vh-full', (vhDiv) => {
      expect(vhDiv.getBoundingClientRect().height).to.be.closeTo(768, 10);
    });
  });
});
