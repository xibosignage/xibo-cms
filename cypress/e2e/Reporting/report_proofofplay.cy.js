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
describe('Proof of Play', function() {
  const display1 = 'POP Display 1';
  beforeEach(function() {
    cy.login();
  });

  it('Range: Test export', function() {
    // Create and alias for load displays
    cy.intercept({
      url: '/display?start=*',
      query: {display: display1},
    }).as('loadDisplayAfterSearch');

    cy.visit('/report/view');
    cy.contains('Export').click();

    cy.get(':nth-child(1) > .col-sm-10 > .input-group > .flatpickr-wrapper > .datePickerHelper').click();
    cy.get('.open > .flatpickr-innerContainer > .flatpickr-rContainer > .flatpickr-days > .dayContainer > .today').click();
    cy.get(':nth-child(2) > .col-sm-10 > .input-group > .flatpickr-wrapper > .datePickerHelper').click();
    cy.get('.open > .flatpickr-innerContainer > .flatpickr-rContainer > .flatpickr-days > .dayContainer > .today').next().click();

    // Click on the select2 selection
    cy.get('#displayId + span .select2-selection').click();
    cy.get('.select2-container--open input[type="search"]').type(display1);
    cy.wait('@loadDisplayAfterSearch');
    cy.selectOption(display1);

    cy.get('.total-stat').contains('Total number of records to be exported 5');
  });

  it('Range: Today - Test layout/media stats for a layout and a display', function() {
    cy.intercept('/report/data/proofofplayReport?*').as('reportData');

    cy.visit('/report/form/proofofplayReport');

    cy.get('#type').select('media');

    // Click on the Apply button
    cy.contains('Apply').click();

    // Wait for
    cy.wait('@reportData');
    cy.get('#stats tbody').contains('media');

    // Should have media stats - Test media stats for a layout and a display
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(1)').contains('media'); // stat type
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Display 1'); // display
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(6)').contains('POP Layout 1'); // layout
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(8)').contains('child_folder_media'); // media
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(10)').contains(2); // number of plays
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(12)').contains(120); // total duration


    cy.get('#type').select('layout');

    // Click on the Apply button
    cy.contains('Apply').click();

    // Wait for
    cy.wait('@reportData');
    cy.contains('Tabular').should('be.visible').click();

    cy.get('#stats tbody').contains('layout');

    // Should have layout stat - Test a layout stat for an ad campaign, a layout and a display
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(1)').contains('layout'); // stat type
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Display 1'); // display
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(4)').contains('POP Ad Campaign 1'); // ad campaign
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(6)').contains('POP Layout 1'); // layout
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(10)').contains(1); // number of plays
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(12)').contains(60); // total duration
  });

  it('Range: Lastweek - Test media stats for a layout and a display', function() {
    cy.intercept('/report/data/proofofplayReport?*').as('reportData');

    cy.visit('/report/form/proofofplayReport');

    // Range: Lastweek
    cy.get('#reportFilter').select('lastweek');

    cy.get('#type').select('media');

    // Click on the Apply button
    cy.contains('Apply').click();

    // Wait for
    cy.wait('@reportData');

    cy.get('#stats').within(() => {
      // Check if the "No data available in table" message is not present
      cy.contains('No data available in table').should('not.exist');
      cy.get('tbody tr').should('have.length', 1);
      // Should have media stats
      cy.get('tbody td').eq(0).should('contain', 'media'); // stat type
      cy.get('tbody td').eq(2).contains('POP Display 1'); // display
      cy.get('tbody td').eq(5).contains('POP Layout 1'); // layout
      cy.get('tbody td').eq(7).contains('child_folder_media'); // media
      cy.get('tbody td').eq(9).contains(2); // number of plays
      cy.get('tbody td').eq(11).contains(120); // total duration
    });

    cy.get('#type').select('layout');

    // Click on the Apply button
    cy.contains('Apply').click();

    // Wait for
    cy.wait('@reportData');

    cy.get('#stats').within(() => {
      // Check if the "No data available in table" message is not present
      cy.contains('No data available in table').should('not.exist');
      cy.get('tbody tr').should('have.length', 1);
      // Should have layout stat - Test a layout stat for an ad campaign, a layout and a display
      cy.get('tbody td').eq(0).contains('layout'); // stat type
      cy.get('tbody td').eq(2).contains('POP Display 1'); // display
      cy.get('tbody td').eq(3).contains('POP Ad Campaign 1'); // ad campaign
      cy.get('tbody td').eq(5).contains('POP Layout 1'); // layout
      cy.get('tbody td').eq(9).contains(1); // number of plays
      cy.get('tbody td').eq(11).contains(60); // total duration
    });
  });

  it('Range: Today - Test event/widget stats for a layout and a display', function() {
    cy.intercept('/report/data/proofofplayReport?*').as('reportData');

    cy.visit('/report/form/proofofplayReport');

    cy.get('#type').select('event');

    // Click on the Apply button
    cy.contains('Apply').click();

    // Wait for
    cy.wait('@reportData');

    // Should have media stats - Test media stats for a layout and a display
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(1)').contains('event'); // stat type
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Display 1'); // display
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(9)').contains('Event123'); // tag/eventname
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(10)').contains(1); // number of plays
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(12)').contains(60); // total duration

    cy.get('#type').select('widget');

    // Click on the Apply button
    cy.contains('Apply').click();

    // Wait for
    cy.wait('@reportData');
    cy.contains('Tabular').should('be.visible').click();

    // Should have layout stat - Test a layout stat for an ad campaign, a layout and a display
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(1)').contains('widget'); // stat type
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(3)').contains('POP Display 1'); // display
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(6)').contains('POP Layout 1'); // layout
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(10)').contains(1); // number of plays
    cy.get('#stats tbody tr:nth-child(1) td:nth-child(12)').contains(60); // total duration
  });
});
