describe('Canary — verify logging in & installing a module works', () => {
  before(() => {
    cy.drupalInstall();
  });

  beforeEach(() => {
    cy.drupalSession();
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Test login', () => {
    cy.drupalCreateUser({
      name: 'user',
      password: '123',
      permissions: ['access site reports', 'administer site configuration'],
    });
  });

  it('test installing a module', () => {
    cy.drupalInstallModule('views', true);
  });
});
