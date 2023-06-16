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

describe('Distribution by Layout, Media or Event', function () {

    beforeEach(function () {
        cy.login();
    });

    it('Range: Today, Checks duration and count of a layout stat', () => {
        // Create and alias for load layout
        cy.server();
        cy.route('/display?start=*').as('loadDisplays');
        cy.route('/layout?start=*').as('loadLayout');

        cy.visit('/report/form/distributionReport');

        // Click on the select2 selection
        cy.get('#displayId + span .select2-selection').click();

        // Wait for display to load
        cy.wait('@loadDisplays');

        // Type the display name
        cy.get('.select2-container--open input[type="search"]').type('POP Display 1');

        // Wait for display to load
        cy.wait('@loadDisplays');
        cy.get('.select2-container--open .select2-results > ul').contains('POP Display 1').click();

        // Click on the select2 selection
        cy.get('#layoutId + span .select2-selection').click();

        // Wait for layout to load
        cy.wait('@loadLayout');

        // Type the layout name
        cy.get('.select2-container--open input[type="search"]').type('POP Layout 1');

        // Wait for layout to load
        cy.wait('@loadLayout');
        cy.get('.select2-container--open .select2-results > ul').contains('POP Layout 1').click();

        // Click on the Apply button
        cy.contains('Apply').should('be.visible').click();

        cy.get('.chart-container').should('be.visible');

        // Click on Tabular
        cy.contains('Tabular').should('be.visible').click();
        cy.contains('Next').should('be.visible').click();

        // Should have media stats
        cy.get('#distributionTbl tbody tr:nth-child(3) td:nth-child(1)').contains('12:00 PM'); // Period
        cy.get('#distributionTbl tbody tr:nth-child(3) td:nth-child(2)').contains(60); // Duration
        cy.get('#distributionTbl tbody tr:nth-child(3) td:nth-child(3)').contains(1); // Count
    });

    it('Create/Delete a Daily Distribution Report Schedule', () => {
        // Create and alias for load layout
        cy.server();
        cy.route('/display?start=*').as('loadDisplays');
        cy.route('/layout?start=*').as('loadLayout');

        cy.visit('/report/form/distributionReport');

        // Click on the select2 selection
        cy.get('#layoutId + span .select2-selection').click();

        // Wait for layout to load
        cy.wait('@loadLayout');

        // Type the layout name
        cy.get('.select2-container--open input[type="search"]').type('POP Layout 1');

        // Wait for layout to load
        cy.wait('@loadLayout');
        cy.get('.select2-container--open .select2-results > ul').contains('POP Layout 1').click();

        // ------
        // ------
        // Create a Daily Distribution Report Schedule
        let reportschedule = 'Daily Distribution by Layout 1 and Display 1';
        cy.get('#reportAddBtn').click();
        cy.get('#reportScheduleAddForm #name ').type(reportschedule);

        // Click on the select2 selection
        cy.get('#reportScheduleAddForm #displayId + span .select2-selection').click();

        // Wait for display to load
        cy.wait('@loadDisplays');

        // Type the display name
        cy.get('.select2-container--open input[type="search"]').type('POP Display 1');

        // Wait for display to load
        cy.wait('@loadDisplays');
        cy.get('.select2-container--open .select2-results > ul').contains('POP Display 1').click();

        cy.get('#dialog_btn_2').should('be.visible').click();

        cy.visit('/report/reportschedule/view');
        cy.get('#name').type(reportschedule);

        // Click on the first row element to open the designer
        cy.get('#reportschedules_wrapper tr:first-child .dropdown-toggle').click();

        cy.get('#reportschedules_wrapper tr:first-child .reportschedule_button_delete').click();

        // Delete test campaign
        cy.get('.bootbox .save-button').click();

        // Check if layout is deleted in toast message
        cy.get('.toast').contains('Deleted ' + reportschedule);
    });
});
