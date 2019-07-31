describe('Campaigns', function () {

    var testRun = "";

    beforeEach(function () {
        cy.login();

        testRun = Cypress._.random(0, 1e6);
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

    it('should add an empty campaign', function() {

        cy.visit('/campaign/view');
        
        // Click on the Add Campaign button
        cy.contains('Add Campaign').click();

        cy.get('.modal input#name')
            .type('Cypress Test Campaign ' + testRun);

        cy.get('.modal .save-button').click();

        // Filter for the created campaign
        cy.get('#Filter input[name="name"]')
            .type('Cypress Test Campaign ' + testRun);

        // Should have no layouts assigned
        cy.get('#campaigns tbody tr').should('have.length', 1);
        cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(2)').contains('0');
    });

    it('should assign layouts to an existing campaign', function() {

        // Create some layouts
        createTempLayouts(2);

        // Create a new campaign and then assign some layouts to it
        cy.createCampaign('Cypress Test Campaign ' + testRun).then((res) => {

            cy.visit('/campaign/view');

            // Filter for the created campaign
            cy.get('#Filter input[name="name"]')
                .type('Cypress Test Campaign ' + testRun);

            // Should have no layouts assigned
            cy.get('#campaigns tbody tr').should('have.length', 1);
            cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(2)').contains('0');

            // Click on the first row element to open the edit modal
            cy.get('#campaigns tr:first-child .dropdown-toggle').click();
            cy.get('#campaigns tr:first-child .campaign_button_edit').click();

            // Assign 2 layouts
            cy.get('#layoutAssignments tr:nth-child(1) a.assignItem').click();
            cy.get('#layoutAssignments tr:nth-child(2) a.assignItem').click();

            // Save
            cy.get('.bootbox .save-button').click();

            // Wait for 4th campaign grid reload
            cy.server();
            cy.route('/campaign?draw=4&*').as('campaignGridLoad');
            cy.wait('@campaignGridLoad');

            // Should have 2 layouts assigned
            cy.get('#campaigns tbody tr').should('have.length', 1);
            cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(2)').contains('2');

            // Delete temp layouts
            deleteTempLayouts(2);
        });
    });

    it('searches and delete existing campaign', function() {

        // Create a new campaign and then search for it and delete it
        cy.createCampaign('Cypress Test Campaign ' + testRun).then((res) => {
            cy.visit('/campaign/view');

            // Filter for the created campaign
            cy.get('#Filter input[name="name"]')
                .type('Cypress Test Campaign ' + testRun);

            // Click on the first row element to open the delete modal
            cy.get('#campaigns tbody tr').should('have.length', 1);
            cy.get('#campaigns tr:first-child .dropdown-toggle').click();
            cy.get('#campaigns tr:first-child .campaign_button_delete').click();

            // Delete test campaign
            cy.get('.bootbox .save-button').click();

            // Check if campaign is deleted in toast message
            cy.contains('Deleted Cypress Test Campaign ' + testRun);
        });
    });

    it('selects multiple campaigns and delete them', function() {

        // Create a new campaign and then search for it and delete it
        cy.createCampaign('Cypress Test Campaign ' + testRun).then((res) => {
            // Delete all test campaigns
            cy.visit('/campaign/view');

            // Clear filter and search for text campaigns
            cy.get('#Filter input[name="name"]')
                .clear()
                .type('Cypress Test Campaign');

            // Wait for 2nd campaign grid reload
            cy.server();
            cy.route('/campaign?draw=2&*').as('campaignGridLoad');
            cy.wait('@campaignGridLoad');

            // Select all
            cy.get('button[data-toggle="selectAll"]').click();
            
            // Delete all
            cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
            cy.get('.dataTables_info li[data-button-id="campaign_button_delete"]').click({force: true});

            // Save button must be visible
            cy.get('button.save-button').should('be.visible');

            cy.get('button.save-button').click({force: true});

            // Save button should be hidden ( delete done )
            cy.get('button.save-button').should('not.be.visible');
        });
    });
});