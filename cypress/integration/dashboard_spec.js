describe('Dashboard', function () {

    beforeEach(function () {
        cy.login();
    });

    it('should be at the dashboard page', function() {

        cy.visit('/statusdashboard');

        cy.url().should('include', 'dashboard');

        cy.contains('xibo_admin');

        cy.contains('Dashboard');
    });

    it('should show the welcome tutorial', function() {

        cy.visit('/statusdashboard');

        // Open user dropdown menu
        cy.get('.dropdown-toggle img.nav-avatar').click();

        // Click Reshow welcome
        cy.get('#reshowWelcomeMenuItem').click();

        cy.get('.popover.tour').contains('Welcome to the Xibo CMS!');
    });

    it('should dismiss the welcome tutorial', function() {

        cy.visit('/statusdashboard');

        cy.contains('Welcome to the Xibo CMS!');
        cy.get('button[data-role="end"]').click();

        cy.visit('/statusdashboard').then(() => {
            cy.get('.popover.tour').should('not.be.visible');
        });
    });
});