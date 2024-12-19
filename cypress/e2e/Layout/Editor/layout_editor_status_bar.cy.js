/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

describe('Layout Editor', function() {
  beforeEach(function() {
    cy.login();
    cy.visit('/layout/view');
    cy.get('button.layout-add-button').click();
    cy.get('#layout-viewer').should('be.visible');
  });

  it('should display the correct Layout status icon and tooltip', function() {
    // Verify tooltip displays on hover with correct status
    // Check if the status icon with the correct class is displayed
    cy.get('#layout-info-status')
      .should('be.visible')
      .and('have.class', 'badge-danger')
      .trigger('mouseover');

    cy.get('.popover')
      .should('be.visible')
      .and('contain', 'This Layout is invalid');

    cy.get('#layout-info-status')
      .trigger('mouseout');
  });

  it('should display the correct Layout name', () => {
    // Verify the Layout name text
    cy.get('.layout-info-name span')
      .should('be.visible')
      .and('contain', 'Untitled');
  });

  it('should display the correct Layout duration', () => {
    // Verify the duration is correctly displayed
    cy.get('.layout-info-duration .layout-info-duration-value')
      .should('be.visible')
      .and('contain', '00:00');
  });

  it('should display the correct Layout dimensions', () => {
    // Verify the dimensions are correctly displayed
    cy.get('.layout-info-dimensions span')
      .should('be.visible')
      .and('contain', '1920x1080');
  });
});
