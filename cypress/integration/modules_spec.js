describe('Modules Page', function () {
    beforeEach(function () {
        cy.login();
    });

    it('should load the modules page and show a complete table of modules', function () {
        cy.visit('/module/view');

        cy.contains('Modules');

        // TODO: How many modules are we expecting by default?
        cy.contains('Showing 1 to 10 of');
    });
});