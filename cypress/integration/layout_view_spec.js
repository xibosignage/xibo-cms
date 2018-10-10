describe('Layout View', function() {

    beforeEach(function() {
        cy.login();
        cy.visit('/layout/view');
    });

    it('create a new layout', function() {

        cy.get('a[href="/layout/form/add"]').click();

        // Create random name
        const uuid = Cypress._.random(0, 1e8);

        // Save id as an alias
        cy.wrap(uuid).as('layout_view_test_layout');

        cy.get('#layoutAddForm input[name="name"]')
            .type(uuid);

        cy.get('.modal-dialog').contains('Save').click();

        cy.get('#layout-editor');
        
    });

    it('search and delete existing layout', function() {
        
        cy.server();
        cy.route('/layout?draw=*').as('layoutGridLoad');
        cy.route('DELETE', '/layout/*').as('deleteLayout');

        // Filter for the created layout
        cy.get('#Filter input[name="layout"]')
            .type(this.layout_view_test_layout);

        // Wait for the filter to make effect
        cy.wait(2000);
        cy.wait('@layoutGridLoad');

        // Click on the first row element to open the designer
        cy.get('#layouts tr:first-child .dropdown-toggle').click();

        cy.get('#layouts tr:first-child .layout_button_delete').click();

        // Delete test layout
        cy.get('.bootbox .save-button').click();

        // Wait for the widget to save
        cy.wait('@deleteLayout');

        // Check if layout is deleted in toast message
        cy.get('.toast').contains('Deleted ' + this.layout_view_test_layout);
    });
});