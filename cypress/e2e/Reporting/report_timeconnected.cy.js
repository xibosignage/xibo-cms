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
describe('Time Connected', function() {
  beforeEach(function() {
    cy.login();
  });

  it('should load time connected data of displays', () => {
    cy.visit('/report/form/timeconnected');

    // Click on the select2 selection
    cy.get('.select2-search__field').click();

    // Type the display name
    cy.get('.select2-container--open textarea[type="search"]').type('POP Display Group');
    cy.get('.select2-container--open .select2-results > ul').contains('POP Display Group').click();

    // Click on the Apply button
    cy.contains('Apply').should('be.visible').click();

    // Should have media stats
    cy.get('#records_table tr:nth-child(1) th:nth-child(1)').contains('POP Display 1');
    cy.get('#records_table tr:nth-child(2) td:nth-child(2)').contains('100%');
  });
});
