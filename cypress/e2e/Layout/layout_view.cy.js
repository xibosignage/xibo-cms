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

describe('Layout View', function() {

    beforeEach(function() {
        cy.login();
    });

    it.skip('should create a new layout and be redirected to the layout designer', function() {

        cy.visit('/layout/view');

        cy.get('button[href="/layout/form/add"]').click();

        // Select first template card
        cy.get('#layout-add-templates .card:first').click();
        cy.get('#layout-create-stepper-next-button').click();

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
            cy.get('#layouts tr:first-child .dropdown-toggle').click({force: true});
            cy.get('#layouts tr:first-child .layout_button_delete').click({force: true});

            // Delete test layout
            cy.get('.bootbox .save-button').click();

            // Check if layout is deleted in toast message
            cy.get('.toast').contains('Deleted ' + uuid);
        });

    });
});