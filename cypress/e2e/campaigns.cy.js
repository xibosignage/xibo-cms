/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

describe('Campaigns', function () {

    var testRun = "";

    beforeEach(function () {
        cy.login();

        testRun = Cypress._.random(0, 1e9);
    });

    /**
     * Create a number of layouts
     */
    function createTempLayouts(num) {
        for(let index = 1; index <= num; index++) {
            var rand = Cypress._.random(0, 1e9);
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

        // Wait for the edit form to pop open
        cy.contains('.modal .modal-title', testRun);

        // Switch to the layouts tab.
        cy.contains('.modal .nav-tabs .nav-link', 'Layouts').click();

        // Should have no layouts assigned
        cy.get('.modal #LayoutAssignSortable').children()
          .should('have.length', 0);
    });

    it.skip('should assign layouts to an existing campaign', function() {

        // Create some layouts
        createTempLayouts(2);

        // Create a new campaign and then assign some layouts to it
        cy.createCampaign('Cypress Test Campaign ' + testRun).then((res) => {

            cy.server();
            cy.route('/campaign?draw=3&*').as('campaignGridLoad');

            cy.visit('/campaign/view');

            // Filter for the created campaign
            cy.get('#Filter input[name="name"]')
                .type('Cypress Test Campaign ' + testRun);

            // Should have no layouts assigned
            cy.get('#campaigns tbody tr').should('have.length', 1);
            cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(5)').contains('0');

            // Click on the first row element to open the edit modal
            cy.get('#campaigns tr:first-child .dropdown-toggle').click();
            cy.get('#campaigns tr:first-child .campaign_button_edit').click();

            // Switch to the layouts tab.
            cy.contains('.modal .nav-tabs .nav-link', 'Layouts').click();

            // Assign 2 layouts
            cy.get('#layoutAssignments tr:nth-child(1) a.assignItem').click();
            cy.get('#layoutAssignments tr:nth-child(2) a.assignItem').click();

            // Save
            cy.get('.bootbox .save-button').click();

            // Wait for 4th campaign grid reload
            cy.wait('@campaignGridLoad');

            // Should have 2 layouts assigned
            cy.get('#campaigns tbody tr').should('have.length', 1);
            cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(5)').contains('2');

            // Delete temp layouts
            //deleteTempLayouts(2);
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

            cy.server();
            cy.route('/campaign?draw=2&*').as('campaignGridLoad');

            // Delete all test campaigns
            cy.visit('/campaign/view');

            // Clear filter and search for text campaigns
            cy.get('#Filter input[name="name"]')
                .clear()
                .type('Cypress Test Campaign');

            // Wait for 2nd campaign grid reload
            cy.wait('@campaignGridLoad');

            // Select all
            cy.get('button[data-toggle="selectAll"]').click();

            // Delete all
            cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
            cy.get('.dataTables_info a[data-button-id="campaign_button_delete"]').click();

            cy.get('button.save-button').click();

            // Modal should contain one successful delete at least
            cy.get('.modal-body').contains(': Success');
        });
    });
});
