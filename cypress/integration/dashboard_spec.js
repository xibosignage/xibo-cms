describe('Dashboard', function() {

    beforeEach(function() {
        cy.login();
    });

    it('should be at the dashboard page', function() {

        cy.visit('/statusdashboard');


        cy.url().should('include', 'dashboard');

        // Check for the dashboard elements
        cy.contains('Bandwidth Usage');
        cy.contains('Library Usage');
        cy.contains('Display Activity');
        cy.contains('Latest News');
    });

    // TODO: replace
    /*it('should show the welcome tutorial, and then disable it', function() {
        cy.server();
        cy.route('POST', '/user/welcome').as('showTour');
        cy.route('PUT', '/user/welcome').as('disableTour');

        cy.visit('/statusdashboard');

        // Open user dropdown menu
        cy.get('.dropdown-toggle img.nav-avatar').click();

        // Click Reshow welcome
        cy.get('#reshowWelcomeMenuItem').click();

        cy.wait('@showTour');

        cy.get('.popover.tour').contains('Welcome to the Xibo CMS!');

        // Click to disable welcome tour
        cy.get('button[data-role="end"]').click();
        cy.wait('@disableTour');

        cy.visit('/statusdashboard').then(() => {
            cy.wait(500);
            cy.get('.popover.tour').should('not.be.visible');
        });
    });*/
});