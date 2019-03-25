describe('Display Groups', function () {

    var testRun = "";

    beforeEach(function () {
        cy.login();

        testRun = Cypress._.random(0, 1e6);

        cy.server();
        cy.route('/displaygroup?draw=*').as('displaygroupGridLoad');
        cy.route('DELETE', '/displaygroup/*').as('deleteDisplaygroup');
        cy.route('POST', '/displaygroup').as('addDisplaygroup');
        cy.route('PUT', '/displaygroup/*').as('saveDisplaygroup');

        cy.visit('/displaygroup/view');
    });

    /**
     * Create a number of layouts
     */
    function createTempLayouts(num) {
        for(let index = 1; index <= num; index++) {
            var rand = Cypress._.random(0, 1e6);
            cy.createLayout(rand).as('testLayoutId' + index);
        }
    }

    /**
     * Delete a number of layouts
     */
    function deleteTempLayouts(num) {
        for(let index = 1; index <= num;index++) {
            cy.get('@testLayoutId' + index).then((id) => {
                cy.deleteLayout(id);
            });
        }
    }

    it('should add two empty display groups', function() {

        // Click on the Add Displaygroup button
        cy.contains('Add Display Group').click();

        cy.get('.modal input#displayGroup')
            .type('Cypress Test Displaygroup ' + testRun + '_1');

        // Add first by clicking next
        cy.get('.modal #dialog_btn_3').click();

        // Check if displaygroup is added in toast message
        cy.contains('Added Cypress Test Displaygroup ' + testRun + '_1');

        cy.get('.modal input#displayGroup')
            .type('Cypress Test Displaygroup ' + testRun + '_2');

        // Add first by clicking next
        cy.get('.modal .save-button').click();

        // Check if displaygroup is added in toast message
        cy.contains('Added Cypress Test Displaygroup ' + testRun + '_2');
    });

    it('should add a displaygroup with the form filled', function() {

        // Create some layouts
        createTempLayouts(3);

        // Create a new displaygroup with assign layouts
        // Click on the Add Displaygroup button
        cy.contains('Add Display Group').click();

        cy.get('.modal input#displayGroup')
            .type('Cypress Test Displaygroup ' + testRun);

        cy.get('.modal input#description')
            .type('Description');

        cy.get('.modal input#isDynamic').check();
        
        cy.get('.modal input#dynamicCriteria')
            .type('testLayoutId');
        // Add first by clicking next
        cy.get('.modal .save-button').click();

        // Check if displaygroup is added in toast message
        cy.get('.toast').contains('Added Cypress Test Displaygroup ' + testRun);

        // Delete temp layouts
        deleteTempLayouts(3);
    });

    it('searches and delete existing displaygroup', function() {

        // Create a new displaygroup and then search for it and delete it
        cy.createDisplaygroup('Cypress Test Displaygroup ' + testRun).then((res) => {
            cy.visit('/displaygroup/view');

            // Filter for the created displaygroup
            cy.get('#Filter input[name="displayGroup"]')
                .type('Cypress Test Displaygroup ' + testRun);

            // Wait for the filter to make effect
            cy.wait(2000);
            cy.wait('@displaygroupGridLoad');

            // Click on the first row element to open the delete modal
            cy.get('#displaygroups tr:first-child .dropdown-toggle').click();
            cy.get('#displaygroups tr:first-child .displaygroup_button_delete').click();

            // Delete test displaygroup
            cy.get('.bootbox .save-button').click();

            // Wait for the delete request
            cy.wait('@deleteDisplaygroup');

            // Check if displaygroup is deleted in toast message
            cy.get('.toast').contains('Deleted Cypress Test Displaygroup ' + testRun);
        });
    });

    it('selects multiple display groups and delete them', function() {

        // Create a new displaygroup and then search for it and delete it
        cy.createDisplaygroup('Cypress Test Displaygroup ' + testRun).then((res) => {

            // Delete all test displaygroups
            cy.visit('/displaygroup/view');

            // Clear filter
            cy.get('#Filter input[name="displayGroup"]')
                .clear()
                .type('Cypress Test Displaygroup');

            // Wait for the filter to make effect
            cy.wait(3000);


            // Select all
            cy.get('button[data-toggle="selectAll"]').click();

            // Delete all
            cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
            cy.get('.dataTables_info li[data-button-id="displaygroup_button_delete"]').click({force: true});

            // Save button must be visible
            cy.get('button.save-button').should('be.visible');
            
            cy.get('input#confirmDelete').check();
            cy.get('button.save-button').click({force: true});

            // Save button should be hidden ( delete done )
            cy.get('button.save-button').should('not.be.visible');
        });
    });
});