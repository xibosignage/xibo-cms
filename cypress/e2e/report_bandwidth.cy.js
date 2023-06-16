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

describe('Bandwidth', function () {

    beforeEach(function () {
        cy.login();
    });

    it('should load tabular data and charts', () => {
        // Create and alias for load Display
        cy.server();
        cy.route('/display?start=*').as('loadDisplays');

        cy.visit('/report/form/bandwidth');

        // Click on the select2 selection
        cy.get('#displayId + span .select2-selection').click();

        // Wait for Display to load
        cy.wait('@loadDisplays');

        // Type the display name
        cy.get('.select2-container--open input[type="search"]').type('POP Display 1');

        // Wait for Display to load
        cy.wait('@loadDisplays');
        cy.get('.select2-container--open .select2-results > ul').contains('POP Display 1').click();

        // Click on the Apply button
        cy.contains('Apply').should('be.visible').click();

        cy.get('.chart-container').should('be.visible');

        // Click on Tabular
        cy.contains('Tabular').should('be.visible').click();

        // Should have media stats
        cy.get('#bandwidthTbl tbody tr:nth-child(1) td:nth-child(1)').contains('Submit Stats');
        cy.get('#bandwidthTbl tbody tr:nth-child(1) td:nth-child(2)').contains(200); // Bandwidth
        cy.get('#bandwidthTbl tbody tr:nth-child(1) td:nth-child(3)').contains('bytes'); // Unit
    });
});
