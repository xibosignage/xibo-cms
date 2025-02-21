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
describe('Layout Designer (Populated)', function() {
  beforeEach(function() {
    cy.login();

    // Import existing
    cy.importLayout('../assets/export_test_layout.zip').as('testLayoutId').then((res) => {
      cy.checkoutLayout(res);

      cy.goToLayoutAndLoadPrefs(res);
    });
  });

  // Open widget form, change the name and duration, save, and see the name change result
  it.skip('changes and saves widget properties', () => {
    // Create and alias for reload widget
    cy.intercept('GET', '/playlist/widget/form/edit/*').as('reloadWidget');

    // Select the first widget from the first region on timeline ( image )
    cy.get('#layout-timeline .designer-region:first [data-type="widget"]:first-child').click();

    // Type the new name in the input
    cy.get('#properties-panel input[name="name"]').clear().type('newName');

    // Set a duration
    cy.get('#properties-panel #useDuration').check();
    cy.get('#properties-panel input[name="duration"]').clear().type(12);

    // Save form
    cy.get('#properties-panel button[data-action="save"]').click();

    // Should show a notification for the name change
    cy.get('.toast-success').contains('newName');

    // Check if the values are the same entered after reload
    cy.wait('@reloadWidget').then(() => {
      cy.get('#properties-panel input[name="name"]').should('have.attr', 'value').and('equal', 'newName');
      cy.get('#properties-panel input[name="duration"]').should('have.attr', 'value').and('equal', '12');
    });
  });

  // On layout edit form, change background color and layer, save and check the changes
  it.skip('changes and saves layout properties', () => {
    // Create and alias for reload layout

    cy.intercept('GET', '/layout?layoutId=*').as('reloadLayout');

    // Change background color
    cy.get('#properties-panel input[name="backgroundColor"]').clear().type('#ccc');

    // Change layer
    cy.get('#properties-panel input[name="backgroundzIndex"]').clear().type(1);

    // Save form
    cy.get('#properties-panel button[data-action="save"]').click();

    // Should show a notification for the successful save
    cy.get('.toast-success').contains('Edited');

    // Check if the values are the same entered after reload
    cy.wait('@reloadLayout').then(() => {
      cy.get('#properties-panel input[name="backgroundColor"]').should('have.attr', 'value').and('equal', '#cccccc');
      cy.get('#properties-panel input[name="backgroundzIndex"]').should('have.value', '1');
    });
  });

  // On layout edit form, change background image check the changes
  it.skip('should change layout´s background image', () => {
    // Create and alias for reload layout

    cy.intercept('GET', '/layout?layoutId=*').as('reloadLayout');
    cy.intercept('GET', '/library/search?*').as('mediaLoad');

    cy.get('#properties-panel #backgroundRemoveButton').click();

    // Open library search tab
    cy.get('.editor-main-toolbar #btn-menu-0').click();
    cy.get('.editor-main-toolbar #btn-menu-1').click();

    cy.wait('@mediaLoad');

    cy.get('.editor-bottom-bar #navigator-edit-btn').click();

    cy.get('.editor-main-toolbar #media-content-1 .toolbar-card:nth-of-type(2)').find('img').should('be.visible');

    // Get a table row, select it and add to the region
    cy.get('.editor-main-toolbar #media-content-1 .toolbar-card:nth-of-type(2) .select-button').click({force: true}).then(() => {
      cy.get('#properties-panel-form-container .background-image-drop').click().then(() => {
        // Save form
        cy.get('#properties-panel button[data-action="save"]').click();

        // Should show a notification for the successful save
        cy.get('.toast-success').contains('Edited');

        // Check if the background field has an image
        cy.get('#properties-panel .background-image-add img#bg_image_image').should('be.visible');
      });
    });
  });

  // Navigator
  it.skip('should change and save the region´s position', () => {
    // Create and alias for position save and reload layout

    cy.intercept('GET', '/layout?layoutId=*').as('reloadLayout');
    cy.intercept('GET', '/region/form/edit/*').as('reloadRegion');
    cy.intercept('GET', '**/region/preview/*').as('regionPreview');

    // Open navigator edit
    cy.get('.editor-bottom-bar #navigator-edit-btn').click();

    // Wait for the region to preview
    cy.wait('@regionPreview');

    cy.get('#layout-navigator [data-type="region"]:first').then(($originalRegion) => {
      const regionId = $originalRegion.attr('id');

      // Select region
      cy.get('#layout-navigator-content #' + regionId).click();

      // Move region 50px for each dimension
      cy.get('#layout-navigator-content #' + regionId).then(($movedRegion) => {
        const regionOriginalPosition = {
          top: Math.round($movedRegion.position().top),
          left: Math.round($movedRegion.position().left),
        };

        const offsetToAdd = 50;

        // Move the region
        cy.get('#layout-navigator-content #' + regionId)
          .trigger('mousedown', {
            which: 1,
          })
          .trigger('mousemove', {
            which: 1,
            pageX: $movedRegion.width() / 2 + $movedRegion.offset().left + offsetToAdd,
            pageY: $movedRegion.height() / 2 + $movedRegion.offset().top + offsetToAdd,
          })
          .trigger('mouseup');

        // Close the navigator edit
        cy.wait('@reloadRegion');

        // Save
        cy.get('#properties-panel button#save').click();

        // Wait for the layout to reload
        cy.wait('@reloadLayout');

        // Check if the region´s position are not the original
        cy.get('#layout-navigator-content #' + regionId).then(($changedRegion) => {
          expect(Math.round($changedRegion.position().top)).to.not.eq(regionOriginalPosition.top);
          expect(Math.round($changedRegion.position().left)).to.not.eq(regionOriginalPosition.left);
        });
      });
    });
  });

  it.skip('should delete a widget using the toolbar bin', () => {
    cy.intercept('GET', '/layout?layoutId=*').as('reloadLayout');
    cy.intercept('GET', '/region/preview/*').as('regionPreview');

    // Select a widget from the timeline
    cy.get('#layout-timeline .designer-region:first [data-type="widget"]:first-child').click().then(($el) => {
      const widgetId = $el.attr('id');

      // Wait for the widget to be loaded
      cy.wait('@regionPreview');

      // Click trash container
      cy.get('.editor-bottom-bar button#delete-btn').click({force: true});

      // Confirm delete on modal
      cy.get('[data-test="deleteObjectModal"] button.btn-bb-confirm').click();

      // Check toast message
      cy.get('.toast-success').contains('Deleted');

      // Wait for the layout to reload
      cy.wait('@reloadLayout');

      // Check that widget is not on timeline
      cy.get('#layout-timeline [data-type="widget"]#' + widgetId).should('not.exist');
    });
  });

  it.skip('saves the widgets order when sorting by dragging', () => {
    cy.intercept('GET', 'POST', '**/playlist/order/*').as('saveOrder');
    cy.intercept('GET', '/layout?layoutId=*').as('reloadLayout');

    cy.get('#layout-timeline .designer-region:first [data-type="widget"]:first-child').then(($oldWidget) => {
      const offsetX = 50;

      // Move to the second widget position ( plus offset )
      cy.wrap($oldWidget)
        .trigger('mousedown', {
          which: 1,
        })
        .trigger('mousemove', {
          which: 1,
          pageX: $oldWidget.offset().left + $oldWidget.width() * 1.5 + offsetX,
        })
        .trigger('mouseup', {force: true});

      cy.wait('@saveOrder');

      // Should show a notification for the order change
      cy.get('.toast-success').contains('Order Changed');

      // Reload layout and check if the new first widget has a different Id
      cy.wait('@reloadLayout');

      cy.get('#layout-timeline .designer-region:first [data-type="widget"]:first-child').then(($newWidget) => {
        expect($oldWidget.attr('id')).not.to.eq($newWidget.attr('id'));
      });
    });
  });

  it.skip('should publish a layout and go into a published state', () => {
    cy.intercept('PUT', '/layout/publish/*').as('layoutPublish');

    cy.get('.editor-top-bar li.navbar-submenu-options a#optionsContainerTop').click();
    cy.get('.editor-top-bar li.navbar-submenu-options #publishLayout').click();

    cy.get('button.btn-bb-Publish').click();

    // Get the id from the published layout and check if the designer reloaded to the Read Only Mode of that layout
    cy.wait('@layoutPublish').then((res) => {
      // Check if the page redirected to the layout designer with the new published layout
      cy.url().should('include', '/layout/designer/' + res.response.body.data.layoutId);

      // Check if the read only message appears
      cy.get('#read-only-message').should('exist');
    });
  });
});
