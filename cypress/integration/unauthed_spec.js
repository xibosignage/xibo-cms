describe('Unauthenticated CMS access', function () {
    it('should visit the login page and check the version', function () {
        cy.visit('/');

        cy.url().should('include', '/login');

        cy.contains('Version 1.8.8');
    });
});