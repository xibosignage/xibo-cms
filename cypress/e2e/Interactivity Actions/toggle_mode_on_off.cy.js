describe('Xibo CMS - Interactive Actions Mode', () => {
  
  beforeEach(() => {
    cy.login();

    // Navigate to Layouts page
    cy.visit('/layout/view');
    
    //Click the Add Layout button
    cy.get('button.layout-add-button').click();
    cy.get('#layout-viewer').should('be.visible');
  });

  it('should verify default status = OFF and checks the status of IA Mode when toggled to ON or OFF', () => {
    
    //check default IA Mode = OFF
    cy.get('li.nav-item.interactive-control')
    .should('have.attr', 'data-status', 'off')
    .then(($el) => {
        cy.wrap($el).click({ force: true })
    })

    //Toggle Mode = ON
    cy.get('li.nav-item.interactive-control')
    .should('have.attr', 'data-status', 'on')
    .and('contain.text', 'ON')

    //Toggle OFF back to Layout Editor
    cy.get('li.nav-item.interactive-control').click({ force: true })
    cy.get('li.nav-item.interactive-control')
    .should('have.attr', 'data-status', 'off')
    .and('contain.text', 'OFF')
  });
});