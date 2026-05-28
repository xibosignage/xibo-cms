/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
describe('Layout Editor Status Bar', function() {
  const layoutStatusSelector = '#layout-info-status';
  const layoutNameSelector = '.layout-info-name span';
  const layoutDurationSelector = '.layout-info-duration .layout-info-duration-value';
  const layoutDimensionsSelector = '.layout-info-dimensions span';
  const tooltipSelector = '.popover';

  beforeEach(function() {
    cy.login();
    cy.intercept('GET', '/layout/status/*').as('layoutStatusLoad');
    // The toolbar pref response triggers renderBars() → topbar.render() → reloadTooltips()
    // → clearTooltips() which removes ALL .popover elements from the body.
    // Waiting for this here ensures every clearTooltips() call from initialisation has
    // already fired before the popover test starts, eliminating the race condition.
    cy.intercept('GET', '/user/pref?preference=toolbar').as('toolbarPref');
    cy.visit('/layout/view');
    cy.get('button.layout-add-button').click();
    cy.get('#layout-viewer').should('be.visible');
    cy.wait('@layoutStatusLoad');
    cy.wait('@toolbarPref');
  });

  it('should display the correct Layout status icon and tooltip', function() {
    cy.get(layoutStatusSelector)
      .should('be.visible')
      .and('have.class', 'badge-danger');

    // Use the app's jQuery instance (win.$) to call Bootstrap's popover API directly.
    // cy.trigger() and native dispatchEvent both fail here: cy.trigger() uses Cypress's jQuery
    // instance which doesn't share Bootstrap's event bindings, and native MouseEvent dispatch
    // is swallowed by Bootstrap's internal hover-state guard in headless mode.
    cy.window().then((win) => {
      win.$(layoutStatusSelector).popover('show');
    });

    cy.get(tooltipSelector)
      .should('be.visible')
      .and('contain', 'This Layout is invalid');

    cy.window().then((win) => {
      win.$(layoutStatusSelector).popover('hide');
    });
  });

  it('should display the correct Layout name', () => {
    // Verify the Layout name text
    cy.get(layoutNameSelector)
      .should('be.visible')
      .and('contain', 'Untitled');
  });

  it('should display the correct Layout duration', () => {
    // Verify the duration is correctly displayed
    cy.get(layoutDurationSelector)
      .should('be.visible')
      .and('contain', '00:00');
  });

  it('should display the correct Layout dimensions', () => {
    // Verify the dimensions are correctly displayed
    cy.get(layoutDimensionsSelector)
      .should('be.visible')
      .and('contain', '1920x1080');
  });
});
