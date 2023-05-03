/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

describe('Proof of Play', function () {

    beforeEach(function () {
        cy.login();
    });

    it('Range: Today - Test media stats for a layout and a display', function() {

        cy.visit('/report/form/proofofplayReport');

        // Click on the Apply button
        cy.contains('Apply').click();

        cy.get('#stats_wrapper').should('be.visible');
        cy.get('#stats').should('be.visible');

        // Should have media stats
        cy.get('#stats tbody tr').should('have.length', 1);
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(1)').contains('media'); // stat type
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Display 1'); // display
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(6)').contains('POP Layout 1'); // layout
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(8)').contains('POP Media 1'); // media
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(10)').contains(2); // number of plays
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(12)').contains(121); // total duration
    });

    it('Range: Today - Test a layout stat for an ad campaign, a layout and a display', function() {

        cy.visit('/report/form/proofofplayReport');

        // Click on the Apply button
        cy.contains('Apply').click();

        cy.get('#stats').should('be.visible');

        // Should have layout stat
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(1)').contains('layout'); // stat type
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Display 1'); // display
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Ad Campaign 1'); // ad campaign
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(6)').contains('POP Layout 1'); // layout
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(10)').contains(1); // number of plays
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(12)').contains(60); // total duration
    });

    it('Range: Lastweek - Test media stats for a layout and a display', function() {

        cy.visit('/report/form/proofofplayReport');

        // Click on the Apply button
        cy.contains('Apply').click();

        cy.get('#stats_wrapper').should('be.visible');
        cy.get('#stats').should('be.visible');

        // Should have media stats
        cy.get('#stats tbody tr').should('have.length', 1);
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(1)').contains('media'); // stat type
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Display 1'); // display
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(6)').contains('POP Layout 1'); // layout
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(8)').contains('POP Media 1'); // media
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(10)').contains(2); // number of plays
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(12)').contains(120); // total duration
    });

    it('Range: Lastweek - Test a layout stat for an ad campaign, a layout and a display', function() {

        cy.visit('/report/form/proofofplayReport');

        // Click on the Apply button
        cy.contains('Apply').click();

        cy.get('#stats').should('be.visible');

        // Should have layout stat
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(1)').contains('layout'); // stat type
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Display 1'); // display
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Ad Campaign 1'); // ad campaign
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(6)').contains('POP Layout 1'); // layout
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(10)').contains(1); // number of plays
        cy.get('#stats tbody tr:nth-child(1) td:nth-child(12)').contains(60); // total duration
    });
});
