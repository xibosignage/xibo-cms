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
describe('Layout Designer (Empty)', function() {
  beforeEach(function() {
    cy.login();
  });

  context('Unexisting Layout', function() {
    it('show layout not found if layout does not exist', function() {
      // Use a huge id to test a layout not found
      cy.visit({
        url: '/layout/designer/111111111111',
        failOnStatusCode: false,
      });

      // See page not found message
      cy.contains('Layout not found');
    });
  });

  context('Empty layout (published)', function() {
    const layoutTempName = '';

    beforeEach(function() {
      // Import a layout and go to the Layout's designer page - we need a Layout in a Published state
      cy.importLayout('../assets/export_test_layout.zip').as('testLayoutId').then((res) => {
        cy.goToLayoutAndLoadPrefs(res);
      });
    });

    it('goes into draft mode when checked out', function() {
      // Get the done button from the checkout modal
      cy.get('[data-test="welcomeModal"] button.btn-bb-checkout').click();

      // Check if campaign is deleted in toast message
      cy.contains('Checked out ' + layoutTempName);
    });

    it('should prevent a layout edit action, and show a toast message', function() {
      // Should contain widget options form
      cy.get('#properties-panel-form-container').contains('Edit Layout');

      // The save button should not be visible
      cy.get('#properties-panel-form-container [data-action="save"]').should('not.exist');
    });
  });

  context('Empty layout (draft)', function() {
    beforeEach(function() {
      // Create random name
      const uuid = Cypress._.random(0, 1e9);

      // Create a new layout and go to the layout's designer page, then load toolbar prefs
      cy.createLayout(uuid).as('testLayoutId').then((res) => {
        cy.goToLayoutAndLoadPrefs(res);
      });
    });

    it('should create a new region from within the navigator edit', () => {
      // Open navigator edit
      cy.get('.editor-bottom-bar #navigator-edit-btn').click();

      // Click on add region button
      cy.get('.editor-bottom-bar #add-btn').click();

      // Check if there are 2 regions in the timeline ( there was 1 by default )
      cy.get('#layout-timeline [data-type="region"]').should('have.length', 2);
    });

    it('should delete a region using the toolbar bin', () => {
      cy.intercept('GET', '/layout?layoutId=*').as('reloadLayout');

      // Open navigator edit
      cy.get('.editor-bottom-bar #navigator-edit-btn').click();

      // Select a region from the navigator
      cy.get('#layout-navigator-content [data-type="region"]:first-child').click().then(($el) => {
        const regionId = $el.attr('id');

        // Click trash container
        cy.get('.editor-bottom-bar #delete-btn').click();

        // Confirm delete on modal
        cy.get('[data-test="deleteObjectModal"] button.btn-bb-confirm').click();

        // Check toast message
        cy.get('.toast-success').contains('Deleted');

        // Wait for the layout to reload
        cy.wait('@reloadLayout');

        // Check that region is not on timeline
        cy.get('#layout-timeline [data-type="region"]#' + regionId).should('not.exist');
      });
    });

    it('creates a new widget by selecting a searched media from the toolbar to layout-navigator region', () => {
      cy.populateLibraryWithMedia();

      // Create and alias for reload Layout
      cy.intercept('GET', '/layout?layoutId=*').as('reloadLayout');
      cy.intercept('GET', '/library/search?*').as('mediaLoad');

      // Open library search tab
      cy.get('.editor-main-toolbar #btn-menu-0').should('be.visible').click({force: true});
      cy.get('.editor-main-toolbar #btn-menu-1').should('be.visible').click({force: true});

      cy.wait('@mediaLoad');

      cy.get('.editor-bottom-bar #navigator-edit-btn').click({force: true});

      cy.get('.editor-main-toolbar #media-content-1 .toolbar-card:nth-of-type(2)').find('img').should('be.visible');

      // Get a table row, select it and add to the region
      cy.get('.editor-main-toolbar #media-content-1 .toolbar-card:nth-of-type(2) .select-button').click({force: true}).then(() => {
        cy.get('#layout-navigator [data-type="region"]:first-child').click().then(() => {
          // Wait for the layout to reload
          cy.wait('@reloadLayout');

          // Check if there is just one widget in the timeline
          cy.get('#layout-timeline [data-type="region"] [data-type="widget"]').then(($widgets) => {
            expect($widgets.length).to.eq(1);
          });
        });
      });
    });

    it('shows the file upload form by adding a uploadable media from the toolbar to layout-navigator region', () => {
      cy.populateLibraryWithMedia();

      // Open toolbar Widgets tab
      cy.get('.editor-main-toolbar #btn-menu-1').should('be.visible').click({force: true});
      cy.get('.editor-main-toolbar #btn-menu-2').should('be.visible').click({force: true});

      cy.get('.editor-bottom-bar #navigator-edit-btn').click();

      cy.get('.editor-main-toolbar #content-2 .toolbar-pane-content .toolbar-card.upload-card').should('be.visible').then(() => {
        cy.get('.editor-main-toolbar #content-2 .toolbar-pane-content .toolbar-card.upload-card .select-upload').click({force: true});
        cy.get('#layout-navigator [data-type="region"]:first-child').click({force: true});
        cy.get('[data-test="uploadFormModal"]').contains('Upload media');
      });
    });
  });
});
