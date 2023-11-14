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

/* eslint-disable max-len */
describe('Summary by Layout, Media or Event', function() {
  const display1 = 'POP Display 1';
  const layout1 = 'POP Layout 1';
  beforeEach(function() {
    cy.login();
  });

  it('Range: Today, Checks duration and count of a layout stat', () => {
    // Create alias
    cy.intercept({
      url: '/display?start=*',
      query: {display: display1},
    }).as('loadDisplayAfterSearch');

    cy.intercept({
      url: '/layout?start=*',
      query: {layout: layout1},
    }).as('loadLayoutAfterSearch');

    cy.intercept('/report/data/summaryReport?*').as('reportData');

    cy.visit('/report/form/summaryReport');

    // Click on the select2 selection
    cy.get('#displayId + span .select2-selection').click();
    cy.get('.select2-container--open input[type="search"]').type(display1);
    cy.wait('@loadDisplayAfterSearch');
    cy.selectOption(display1);

    // Click on the select2 selection
    cy.get('#layoutId + span .select2-selection').click();
    cy.get('.select2-container--open input[type="search"]').type(layout1);
    cy.wait('@loadLayoutAfterSearch');
    cy.selectOption(layout1);

    // Click on the Apply button
    cy.contains('Apply').should('be.visible').click();
    // Wait for report data
    cy.wait('@reportData');

    cy.get('.chart-container').should('be.visible');

    // Click on Tabular
    cy.contains('Tabular').should('be.visible').click();
    cy.contains('Next').should('be.visible').click();

    // Should have media stats
    cy.get('#summaryTbl tbody tr:nth-child(3) td:nth-child(1)').contains('12:00 PM'); // Period
    cy.get('#summaryTbl tbody tr:nth-child(3) td:nth-child(2)').contains(60); // Duration
    cy.get('#summaryTbl tbody tr:nth-child(3) td:nth-child(3)').contains(1); // Count
  });

  it('Create/Delete a Daily Summary Report Schedule', () => {
    const reportschedule = 'Daily Summary by Layout 1 and Display 1';

    // Create and alias for load layout
    cy.intercept('/report/reportschedule/form/add*').as('reportScheduleAddForm');
    cy.intercept({
      url: '/display?start=*',
      query: {display: display1},
    }).as('loadDisplayAfterSearch');

    cy.intercept({
      url: '/layout?start=*',
      query: {layout: layout1},
    }).as('loadLayoutAfterSearch');

    cy.intercept({
      url: '/report/reportschedule?*',
      query: {name: reportschedule},
    }).as('loadReportScheduleAfterSearch');

    cy.visit('/report/form/summaryReport');

    // Click on the select2 selection
    cy.get('#layoutId + span .select2-selection').click();
    cy.get('.select2-container--open input[type="search"]').type(layout1);
    cy.wait('@loadLayoutAfterSearch');
    cy.selectOption(layout1);

    // ------
    // ------
    // Create a Daily Summary Report Schedule
    cy.get('#reportAddBtn').click();
    cy.wait('@reportScheduleAddForm');
    cy.get('#reportScheduleAddForm #name ').type(reportschedule);

    // Click on the select2 selection
    cy.get('#reportScheduleAddForm #displayId + span .select2-selection').click();
    cy.get('.select2-container--open input[type="search"]').type(display1);
    cy.wait('@loadDisplayAfterSearch');
    cy.selectOption(display1);


    cy.get('#dialog_btn_2').should('be.visible').click();

    cy.visit('/report/reportschedule/view');
    cy.get('#name').type(reportschedule);
    cy.wait('@loadReportScheduleAfterSearch');

    // Click on the first row element to open the designer
    cy.get('#reportschedules_wrapper tr:first-child .dropdown-toggle').click({force: true});

    cy.get('#reportschedules_wrapper tr:first-child .reportschedule_button_delete').click({force: true});

    // Delete test campaign
    cy.get('.bootbox .save-button').click();

    // Check if layout is deleted in toast message
    cy.get('.toast').contains('Deleted ' + reportschedule);
  });
});
