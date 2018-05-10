describe('Dashboard', function () {

    beforeEach(function () {
        cy.login();

        cy.visit('/');
    });

    it('should be at the dashboard page', function() {
        cy.url().should('include', '/dashboard');

        cy.contains('xibo_admin');

        cy.contains('Dashboard');
    });
});