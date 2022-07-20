/*
 * Copyright (c) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

describe('Display Groups', function () {

    var testRun = "";

    beforeEach(function () {
        cy.login();

        testRun = Cypress._.random(0, 1e9);
    });

    it('should add one empty and one filled display groups', function() {

        cy.visit('/displaygroup/view');

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

        cy.get('.modal input#description')
            .type('Description');

        cy.get('.modal input#isDynamic').check();

        cy.get('.modal input#dynamicCriteria')
            .type('testLayoutId');

        // Add first by clicking next
        cy.get('.modal .save-button').click();

        // Check if displaygroup is added in toast message
        cy.contains('Added Cypress Test Displaygroup ' + testRun + '_2');
    });


    it('searches and delete existing displaygroup', function() {

        // Create a new displaygroup and then search for it and delete it
        cy.createDisplaygroup('Cypress Test Displaygroup ' + testRun).then((res) => {
            
            cy.server();
            cy.route('/displaygroup?draw=2&*').as('displaygroupGridLoad');

            cy.visit('/displaygroup/view');

            // Filter for the created displaygroup
            cy.get('#Filter input[name="displayGroup"]')
                .type('Cypress Test Displaygroup ' + testRun);

            // Wait for the grid reload
            cy.wait('@displaygroupGridLoad');

            // Click on the first row element to open the delete modal
            cy.get('#displaygroups tr:first-child .dropdown-toggle').click();
            cy.get('#displaygroups tr:first-child .displaygroup_button_delete').click();

            // Delete test displaygroup
            cy.get('.bootbox .save-button').click();

            // Check if displaygroup is deleted in toast message
            cy.get('.toast').contains('Deleted Cypress Test Displaygroup');
        });
    });

    it('selects multiple display groups and delete them', function() {

        // Create a new displaygroup and then search for it and delete it
        cy.createDisplaygroup('Cypress Test Displaygroup ' + testRun).then((res) => {

            cy.server();
            cy.route('/displaygroup?draw=2&*').as('displaygroupGridLoad');

            // Delete all test displaygroups
            cy.visit('/displaygroup/view');

            // Clear filter
            cy.get('#Filter input[name="displayGroup"]')
                .clear()
                .type('Cypress Test Displaygroup');

            // Wait for the grid reload
            cy.wait('@displaygroupGridLoad');

            // Select all
            cy.get('button[data-toggle="selectAll"]').click();

            // Delete all
            cy.get('.dataTables_info button[data-toggle="dropdown"]').click();
            cy.get('.dataTables_info a[data-button-id="displaygroup_button_delete"]').click();

            cy.get('input#checkbox-confirmDelete').check();
            cy.get('button.save-button').click();

            // Modal should contain one successful delete at least
            cy.get('.modal-body').contains(': Success');
        });
    });
});