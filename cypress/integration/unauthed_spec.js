describe('Unauthenticated CMS access', function () {
    it('should visit the login page and check the version', function () {
        cy.visit('/');

        cy.url().should('include', '/login');

        cy.contains('Version 1.8.13');
    });

    it('should redirect to login when an authenticated page is requested', function() {

        cy.visit('/layout/view');
        cy.url().should('include', '/login');
    });
});