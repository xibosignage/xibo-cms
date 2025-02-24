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
describe('Layout Editor Toolbar', function() {
  beforeEach(function() {
    cy.login();
    cy.visit('/layout/view');
    cy.get('button.layout-add-button').click();
    cy.get('#layout-viewer').should('be.visible');
  });

  const setZoomLevel = (level) => {
    cy.openToolbarMenu(0, false);
    cy.intercept('POST', '/user/pref').as('updatePreferences');

    cy.get('.toolbar-level-control-menu').click();
    cy.get('nav.navbar').then(($toolbar) => {
      if ($toolbar.hasClass(`toolbar-level-${level}`)) return;
      cy.get(`i[data-level="${level}"]`).click();
      cy.wait('@updatePreferences');
    });
    cy.get('nav.navbar').should('have.class', `toolbar-level-${level}`);
  };

  it('should expand and close the toolbox', function() {
    cy.openToolbarMenu(0, false);
    cy.get('.close-content').filter(':visible').click();
  });

  it('should be able to set zoom level to 1', function() {
    setZoomLevel(1);
  });

  it('should be able to set zoom level to 2', function() {
    setZoomLevel(2);
  });

  function searchAndAddElement(tabIndex, keyword, elementSelector, subTypeSelector, paneSelector) {
    cy.intercept('POST', '/region/*').as('addRegion');

    // Open the respective toolbar tab
    cy.openToolbarMenu(tabIndex, false);

    // Search for the element
    cy.toolbarSearch(keyword);
    cy.get(paneSelector + '.active')
      .find('.toolbar-pane-content')
      .find('.toolbar-card')
      .should('have.length.greaterThan', 0)
      .each(($card) => {
        cy.wrap($card)
          .find('.card-title')
          .should('include.text', keyword);
      });

    // Add the widget to layout
    cy.get(elementSelector).click();
    if (subTypeSelector) {
      cy.get(subTypeSelector).click();
    }
    cy.get('.viewer-object').click();
    cy.wait('@addRegion').then((interception) => { // todo: error here
      expect(interception.response.statusCode).to.eq(200);
    });
  }

  it('should navigate to Widgets tab, search and add a widget', function() {
    searchAndAddElement(0, 'Clock', '[data-sub-type="clock"]', '[data-sub-type="clock-analogue"]', '.toolbar-widgets-pane');
  });

  it('should navigate to Global Elements tab, search and add an element', function() {
    searchAndAddElement(1, 'Text', '[data-template-id="text"]', '', '.toolbar-global-pane');
  });

  function testLibrarySearchAndAddMedia(mediaType, tabIndex, keyword, folderName, mediaTitle) {
    cy.intercept('POST', '/user/pref').as('updatePreferences');
    cy.intercept('GET', '/folders?start=0&length=10').as('loadFolders');
    cy.intercept('GET', '/library/search*').as('librarySearch');
    cy.intercept('POST', '/region/*').as('addRegion');

    cy.openToolbarMenu(tabIndex, false);

    // Conditionally filter media by Folder if folderName is provided
    if (folderName) {
      cy.get(`.toolbar-pane.toolbar-${mediaType}-pane.active`)
        .find('#input-folder')
        .parent()
        .find('.select2-selection')
        .click();
      cy.wait('@loadFolders');
      cy.get('.select2-container--open')
        .contains(folderName)
        .click();
      cy.wait('@updatePreferences');
      cy.wait('@librarySearch');
    }

    // Search for a media
    cy.toolbarSearchWithActiveFilter(keyword);
    cy.get(`.toolbar-pane.toolbar-${mediaType}-pane.active`)
      .find('.toolbar-pane-content')
      .find('.toolbar-card[data-type="media"]')
      .each(($card) => {
        cy.wrap($card)
          .find('span.media-title')
          .should('include.text', keyword);
      });

    cy.wait('@librarySearch');
    cy.wait('@updatePreferences');

    // Add media to layout
    cy.get(`.toolbar-pane.toolbar-${mediaType}-pane.active`)
      .find(`[data-card-title="${mediaTitle}"]`)
      .should('exist')
      .click();
    cy.get('.viewer-object').click();
    cy.wait('@addRegion');
  }

  // Test cases
  it('should navigate to Library Image Search tab, filter, search and add media', function() {
    testLibrarySearchAndAddMedia('image', 2, 'media_for_search', 'FolderWithImage', 'media_for_search_in_folder');
  });

  it('should navigate to Library Audio Search tab, filter, search and add media', function() {
    testLibrarySearchAndAddMedia('audio', 3, 'test-audio', null, 'test-audio.mp3');
  });

  it('should navigate to Library Video Search tab, filter, search and add media', function() {
    testLibrarySearchAndAddMedia('video', 4, 'test-video', null, 'test-video.mp4');
  });

  it('should navigate to Interactive Actions tab and search for actions', function() {
    const keyword = 'Next';
    cy.openToolbarMenu(7, false);
    cy.toolbarSearch(keyword);
    cy.get('.toolbar-pane.toolbar-actions-pane.active')
      .find('.toolbar-pane-content .toolbar-card')
      .each(($card) => {
        cy.wrap($card).find('.card-title').should('include.text', keyword);
      });
  });
});

