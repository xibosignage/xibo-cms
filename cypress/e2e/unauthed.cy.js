describe('Unauthenticated CMS access', function () {
    it('should visit the login page and check the version', function () {

        cy.visit('/login').then(() => {

            cy.url().should('include', '/login');

            cy.contains('Version 4.');
        });
    });

    it('should redirect to login when an authenticated page is requested', function() {
        cy.visit('/logout').then(() => {
            cy.visit('/layout/view').then(() => {
                cy.url().should('include', '/login');
            });
        });
    });
});
