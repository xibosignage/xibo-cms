describe('Layout View', function() {

    beforeEach(function() {
        cy.login();
    });

    it('should create a new layout and be redirected to the layout designer', function() {

        cy.visit('/layout/view');

        cy.get('button[href="/layout/form/add"]').click();

        // Create random name
        let uuid = Cypress._.random(0, 1e10);

        // Save id as an alias
        cy.wrap(uuid).as('layout_view_test_layout');

        cy.get('#layoutAddForm input[name="name"]')
            .type(uuid);

        cy.get('.modal-dialog').contains('Save').click();

        cy.url().should('include', '/layout/designer');
    });

    it('searches and delete existing layout', function() {

        // Create random name
        let uuid = Cypress._.random(0, 1e10);

        // Create a new layout and go to the layout's designer page, then load toolbar prefs
        cy.createLayout(uuid).as('testLayoutId').then((res) => {

            cy.server();
            cy.route('/layout?draw=2&*').as('layoutGridLoad');

            cy.visit('/layout/view');

            // Filter for the created layout
            cy.get('#Filter input[name="layout"]')
                .type(uuid);

            // Wait for the layout grid reload
            cy.wait('@layoutGridLoad');

            // Click on the first row element to open the designer
            cy.get('#layouts tr:first-child .dropdown-toggle').click();

            cy.get('#layouts tr:first-child .layout_button_delete').click();

            // Delete test layout
            cy.get('.bootbox .save-button').click();

            // Check if layout is deleted in toast message
            cy.get('.toast').contains('Deleted ' + uuid);
        });

    });
});