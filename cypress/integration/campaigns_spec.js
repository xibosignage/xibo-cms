describe('Campaigns', function () {

    var testRun = "";

    beforeEach(function () {
        cy.login();

        testRun = Cypress._.random(0, 1e6);

        cy.server();
        cy.route('/campaign?draw=*').as('campaignGridLoad');
        cy.route('DELETE', '/campaign/*').as('deleteCampaign');
        cy.route('POST', '/campaign').as('addCampaign');
        cy.route('PUT', '/campaign/*').as('saveCampaign');

        cy.visit('/campaign/view');
    });

    after(function() {

        // Delete all test campaigns
        cy.visit('/campaign/view');

        // Select rows
        cy.get('#campaigns').contains('Cypress Test Campaign').click();

        // Delete all
        cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
        cy.get('.dataTables_info li[data-button-id="campaign_button_delete"]').click({force: true});
        cy.get('button.save-button').click({force: true});
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

        // Click on the Add Campaign button
        cy.contains('Add Campaign').click();

        cy.get('.modal input#name')
            .type('Cypress Test Campaign ' + testRun);

        cy.get('.modal .save-button').click();

        // Filter for the created campaign
        cy.get('#Filter input[name="name"]')
            .type('Cypress Test Campaign ' + testRun);

        // Wait for the filter to make effect
        cy.wait(2000);
        cy.wait('@campaignGridLoad');

        // Should have no layouts assigned
        cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(2)').contains('0');

        cy.contains('Cypress Test Campaign ' + testRun);
    });

    it('should add a campaign with layouts', function() {

        // Create some layouts
        createTempLayouts(3);

        // Create a new campaign with assign layouts
        // Click on the Add Campaign button
        cy.contains('Add Campaign').click();

        cy.get('.modal input#name')
            .type('Cypress Test Campaign ' + testRun);
        
            // Assign 3 layouts
        cy.get('#layoutAssignments tr:nth-child(1) a.assignItem').click();
        cy.get('#layoutAssignments tr:nth-child(2) a.assignItem').click();
        cy.get('#layoutAssignments tr:nth-child(3) a.assignItem').click();

        // Save
        cy.get('.bootbox .save-button').click();

        // Wait for the campaign to save
        cy.wait('@addCampaign');

        // Filter for the created campaign
        cy.get('#Filter input[name="name"]')
            .clear()
            .type('Cypress Test Campaign ' + testRun);

        // Wait for the filter to make effect
        cy.wait(2000);
        cy.wait('@campaignGridLoad');

        // Should have 3 layouts assigned
        cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(2)').contains('3');

        // Delete temp layouts
        deleteTempLayouts(3);
    });

    it('should assign layouts to an existing campaign', function() {

        // Create some layouts
        createTempLayouts(3);

        // Create a new campaign and then assign some layouts to it
        cy.createCampaign('Cypress Test Campaign ' + testRun).then((res) => {
            cy.visit('/campaign/view');

            // Filter for the created campaign
            cy.get('#Filter input[name="name"]')
                .type('Cypress Test Campaign ' + testRun);

            // Wait for the filter to make effect
            cy.wait(2000);
            cy.wait('@campaignGridLoad');

            // Should have no layouts assigned
            cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(2)').contains('0');

            // Click on the first row element to open the edit modal
            cy.get('#campaigns tr:first-child .dropdown-toggle').click();
            cy.get('#campaigns tr:first-child .campaign_button_edit').click();

            // Assign 3 layouts
            cy.get('#layoutAssignments tr:nth-child(1) a.assignItem').click();
            cy.get('#layoutAssignments tr:nth-child(2) a.assignItem').click();
            cy.get('#layoutAssignments tr:nth-child(3) a.assignItem').click();

            // Save
            cy.get('.bootbox .save-button').click();
            
            // Wait for the campaign to save
            cy.wait('@saveCampaign');

            // Filter for the created campaign
            cy.get('#Filter input[name="name"]')
                .clear()
                .type('Cypress Test Campaign ' + testRun);

            // Wait for the filter to make effect
            cy.wait(2000);
            cy.wait('@campaignGridLoad');

            // Should have 3 layouts assigned
            cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(2)').contains('3');

            // Delete temp layouts
            deleteTempLayouts( 3);
        });
    });

    it('search and delete existing campaign', function() {

        // Create a new campaign and then search for it and delete it
        cy.createCampaign('Cypress Test Campaign ' + testRun).then((res) => {
            cy.visit('/campaign/view');

            // Filter for the created campaign
            cy.get('#Filter input[name="name"]')
                .type('Cypress Test Campaign ' + testRun);

            // Wait for the filter to make effect
            cy.wait(2000);
            cy.wait('@campaignGridLoad');

            // Click on the first row element to open the delete modal
            cy.get('#campaigns tr:first-child .dropdown-toggle').click();
            cy.get('#campaigns tr:first-child .campaign_button_delete').click();

            // Delete test campaign
            cy.get('.bootbox .save-button').click();

            // Wait for the delete request
            cy.wait('@deleteCampaign');

            // Check if campaign is deleted in toast message
            cy.get('.toast').contains('Deleted Cypress Test Campaign ' + testRun);
        });
    });

});