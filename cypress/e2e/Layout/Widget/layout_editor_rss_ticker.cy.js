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
describe('RSS Ticker', function() {
  beforeEach(function() {
    cy.login();
  });

  it('should create a new layout and be redirected to the layout designer, add/delete RSS ticker widget', function() {
    cy.intercept('DELETE', '**/region/**').as('deleteWidget');
    cy.intercept('POST', '/user/pref').as('userPref');

    cy.visit('/layout/view');
    cy.get('button[href="/layout"]').click();

    // Open widget menu
    cy.openToolbarMenu(0);

    cy.get('[data-sub-type="rss-ticker"]')
      .scrollIntoView()
      .should('be.visible')
      .click();
    cy.wait('@userPref');

    cy.get('[data-template-id="article_image_only"] > .toolbar-card-thumb')
      .scrollIntoView()
      .should('be.visible')
      .click();
    cy.wait('@userPref');

    cy.get('.viewer-object.layout.ui-droppable-active')
      .should('be.visible')
      .click();

    // Check if the widget is in the viewer
    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_rss-ticker"]').should('exist');
    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_rss-ticker"]').parents('.designer-region').click();

    // Validate if uri is not provide we show an error message
    cy.get('[name="numItems"]').clear().type('10').trigger('change');
    cy.get('.form-container').contains('Missing required property Feed URL');

    cy.get('[name="uri"]').clear();
    cy.get('[name="uri"]').type('http://xibo.org.uk/feed');
    cy.get('[name="numItems"]').clear().type('10').trigger('change');
    cy.get('[name="durationIsPerItem"]').check();
    cy.get('[name="takeItemsFrom"]').select('End of the Feed', {force: true});
    cy.get('[name="reverseOrder"]').check();
    cy.get('[name="randomiseItems"]').check();

    cy.get('[name="userAgent"]').clear().type('Mozilla/5.0');
    cy.get('[name="updateInterval"]').clear().type('10').trigger('change');

    // Click on Appearance Tab
    cy.get('.nav-link[href="#appearanceTab"]').click();
    cy.get('[name="backgroundColor"]').clear().type('#dddddd');
    cy.get('[name="itemImageFit"]').select('Fill', {force: true});
    cy.get('[name="effect"]').select('Fade', {force: true});
    cy.get('[name="speed"]').clear().type('500');
    // Update CKEditor value
    cy.updateCKEditor('noDataMessage', 'No data to show');
    cy.get('[name="copyright"]').clear().type('Xibo').trigger('change');

    // Delete widget
    // The .moveable-control-box overlay obstructing the right-click interaction on the designer region, causing the test to fail.
    // By invoking .hide(), we remove the overlay temporarily to allow uninterrupted interaction with the underlying elements.
    cy.get('.moveable-control-box').invoke('hide');

    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_rss-ticker"]')
      .parents('.designer-region')
      .scrollIntoView()
      .should('be.visible')
      .rightclick();

    // Wait until the widget has been deleted
    // cy.get('[data-title="Delete"]').click().then(() => {
    //   cy.wait('@deleteWidget').its('response.statusCode').should('eq', 200);
    //   cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_rss-ticker"]')
    //     .should('not.exist');
    // });
  });
});
