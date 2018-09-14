describe('Layout Designer', function() {

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
        
        // Filter for the created layout
        cy.get('#Filter #layout')
            .type(this.layout_view_test_layout);

        // Click on the first row element to open the designer
        if(cy.get('#layouts tr:first-child .dropdown-toggle')) {
            cy.get('#layouts tr:first-child .dropdown-toggle').click();

            cy.get('#layouts tr:first-child .layout_button_delete').click();

            // Delete test layout
            cy.get('.bootbox .save-button').click();
        }

        // Check if layout is deleted
        cy.visit('/layout/view');

        cy.get('table#layouts').contains('No data available in table');
    });
});