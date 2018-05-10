describe('Layout Designer', function() {
    beforeEach(function() {
        cy.login();
    });

    it('should navigate to the Layout Designer page and have a Layout with 2 regions', function() {
        cy.visit('/layout/designer/1');


    });
});