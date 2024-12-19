/* eslint-disable max-len */
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

  it('should expand and close the toolbox', function() {
    cy.openToolbarMenu(0, false);
    cy.get('.close-content')
      .filter(':visible')
      .click();
  });

  it('should be able to set zoom level to 1', function() {
    cy.openToolbarMenu(0, false);
    cy.intercept('POST', '/user/pref').as('updatePreferences');

    cy.get('.toolbar-level-control-menu').click();
    cy.get('nav.navbar')
      .then(($toolbar) => {
        // Check if the toolbar is already on level 1
        if ($toolbar.hasClass('toolbar-level-1')) return;
        cy.get('i[data-level="1"]').click();
        cy.wait('@updatePreferences');
      });
    cy.get('nav.navbar')
      .should('have.class', 'toolbar-level-1');
  });

  it('should be able to set zoom level to 2', function() {
    cy.openToolbarMenu(0, false);
    cy.intercept('POST', '/user/pref').as('updatePreferences');

    cy.get('.toolbar-level-control-menu').click();
    cy.get('nav.navbar')
      .then(($toolbar) => {
        // Check if the toolbar is already on level 2
        if ($toolbar.hasClass('toolbar-level-2')) return;
        cy.get('i[data-level="2"]').click();
        cy.wait('@updatePreferences');
      });
    cy.get('nav.navbar')
      .should('have.class', 'toolbar-level-2');
  });

  it('should navigate to Widgets tab, search and add a widget', function() {
    const keyword = 'Clock';
    cy.intercept('GET', '/playlist/widget/form/edit/*').as('addElement');

    cy.openToolbarMenu(0, false);
    // Search for a widget
    cy.toolbarSearch(keyword);
    cy.get('.toolbar-pane.toolbar-widgets-pane.active')
      .find('.toolbar-pane-content')
      .find('.toolbar-card')
      .each(($card) => {
        cy.wrap($card)
          .find('.card-title')
          .should('include.text', keyword);
      });

    // Add Clock-Analogue widget to layout
    cy.get('[data-sub-type="clock"]').click();
    cy.get('[data-sub-type="clock-analogue"]').click();
    cy.get('.viewer-object').click();
    cy.wait('@addElement').then((interception) => {
      expect(interception.response.statusCode).to.eq(200);
    });
  });

  it('should navigate to Global Elements tab, search and add an element', function() {
    const keyword = 'Text';
    cy.intercept('GET', '/playlist/widget/form/edit/*').as('addElement');

    cy.openToolbarMenu(1, false);

    // Search for an element
    cy.toolbarSearch(keyword);
    cy.get('.toolbar-pane.toolbar-global-pane.active')
      .find('.toolbar-pane-content')
      .find('.toolbar-card')
      .should('have.length.greaterThan', 0)
      .each(($card) => {
        cy.wrap($card)
          .find('.card-title')
          .should('include.text', keyword);
      });

    // Add Text element to layout
    cy.get('[data-template-id="text"]').click();
    cy.get('.viewer-object').click();
    cy.wait('@addElement').then((interception) => {
      expect(interception.response.statusCode).to.eq(200);
    });
  });

  it('should navigate to Library Image Search tab, filter, search and add media', function() {
    const keyword = 'media_for_search';
    // const folderName = 'FolderWithImage';
    // const folderId = 7;
    cy.intercept('POST', '/user/pref').as('updatePreferences');
    cy.intercept('GET', '/folders?start=0&length=10').as('loadFolders');
    cy.intercept('GET', '/library/search*').as('librarySearch');
    cy.intercept('GET', '/playlist/widget/form/edit/*').as('addElement');

    // cy.wait('@librarySearch');
    // cy.wait('@updatePreferences');

    cy.openToolbarMenu(2, false);

    // Filter media by Folder
    // cy.toolbarFilterByFolder(folderName, folderId);
    cy.get('.toolbar-pane.toolbar-image-pane.active')
      .find('#input-folder')
      .parent()
      .find('.select2-selection')
      .click();
    cy.wait('@loadFolders');
    cy.get('.select2-results__option')
      .contains('FolderWithImage')
      .click();
    cy.wait('@updatePreferences');
    cy.wait('@librarySearch');
    // .then(({response}) => {
    //   expect(response.statusCode).to.eq(200);
    //   expect(response.url).to.include('folderId=7');
    // });

    // Search for a media
    cy.toolbarSearchWithActiveFilter(keyword);
    cy.get('.toolbar-pane.toolbar-image-pane.active')
      .find('.toolbar-pane-content')
      .find('.toolbar-card[data-type="media"]')
      .each(($card) => {
        cy.wrap($card)
          .find('span.media-title')
          .should('include.text', keyword);
      });
    // cy.wait('@librarySearch');

    // Add image to layout
    cy.get('.toolbar-pane.toolbar-image-pane.active')
      .find('[data-media-id="7"]')
      .should('exist')
      .click();
    cy.get('.viewer-object').click();
    cy.wait('@addElement');
    // .then((interception) => {
    //   expect(interception.response.statusCode).to.eq(200);
    // });
  });

  it('should navigate to Library Audio Search tab, filter, search and add media', function() {
    const keyword = 'test_audio';
    // const folderName = 'ChildFolder';
    // const folderId = 2;

    cy.intercept('POST', '/user/pref').as('updatePreferences');
    cy.intercept('GET', '/folders?start=0&length=10').as('loadFolders');
    cy.intercept('GET', '/library/search*').as('librarySearch');
    cy.intercept('GET', '/playlist/widget/form/edit/*').as('addElement');

    // cy.wait('@librarySearch');
    // cy.wait('@updatePreferences');

    cy.openToolbarMenu(3, false);

    // Filter media by Folder
    // cy.toolbarFilterByFolder(folderName, folderId);
    cy.get('.toolbar-pane.toolbar-audio-pane.active')
      .find('#input-folder')
      .parent()
      .find('.select2-selection')
      .click();
    cy.wait('@loadFolders');
    cy.get('.select2-results__option')
      .contains('ChildFolder')
      .click();
    cy.wait('@updatePreferences');
    cy.wait('@librarySearch');
    // .then(({response}) => {
    //   expect(response.statusCode).to.eq(200);
    //   expect(response.url).to.include('folderId=7');
    // });

    // Search for a media
    cy.toolbarSearchWithActiveFilter(keyword);
    cy.get('.toolbar-pane.toolbar-audio-pane.active')
      .find('.toolbar-pane-content')
      .find('.toolbar-card[data-type="media"]')
      .each(($card) => {
        cy.wrap($card)
          .find('span.media-title')
          .should('include.text', keyword);
      });
    // cy.wait('@librarySearch');

    // Add audio to layout
    cy.get('.toolbar-pane.toolbar-audio-pane.active')
      .find('[data-media-id="8"]')
      .should('exist')
      .click();
    cy.get('.viewer-object').click();
    cy.wait('@addElement');
    // .then((interception) => {
    //   expect(interception.response.statusCode).to.eq(200);
    // });
  });

  it('should navigate to Library Video Search tab, filter, search and add media', function() {
    const keyword = 'test_video';
    // const folderName = 'ChildFolder';
    // const folderId = 2;

    cy.intercept('POST', '/user/pref').as('updatePreferences');
    cy.intercept('GET', '/folders?start=0&length=10').as('loadFolders');
    cy.intercept('GET', '/library/search*').as('librarySearch');
    cy.intercept('GET', '/playlist/widget/form/edit/*').as('addElement');

    // cy.wait('@librarySearch');
    // cy.wait('@updatePreferences');

    cy.openToolbarMenu(4, false);

    // Filter media by Folder
    // cy.toolbarFilterByFolder(folderName, folderId);
    cy.get('.toolbar-pane.toolbar-video-pane.active')
      .find('#input-folder')
      .parent()
      .find('.select2-selection')
      .click();
    cy.wait('@loadFolders');
    cy.get('.select2-results__option')
      .contains('ChildFolder')
      .click();
    cy.wait('@updatePreferences');
    cy.wait('@librarySearch');
    // .then(({response}) => {
    //   expect(response.statusCode).to.eq(200);
    //   expect(response.url).to.include('folderId=7');
    // });

    // Search for a media
    cy.toolbarSearchWithActiveFilter(keyword);
    cy.get('.toolbar-pane.toolbar-video-pane.active')
      .find('.toolbar-pane-content')
      .find('.toolbar-card[data-type="media"]')
      .each(($card) => {
        cy.wrap($card)
          .find('span.media-title')
          .should('include.text', keyword);
      });
    // cy.wait('@librarySearch');

    // Add video to layout
    cy.get('.toolbar-pane.toolbar-video-pane.active')
      .find('[data-media-id="9"]')
      .should('exist')
      .click();
    cy.get('.viewer-object').click();
    cy.wait('@addElement');
    // .then((interception) => {
    //   expect(interception.response.statusCode).to.eq(200);
    // });
  });

  it('should navigate to Interactive Actions tab and search for actions', function() {
    const keyword = 'Next';

    cy.openToolbarMenu(7, false);
    cy.toolbarSearch(keyword);
    cy.get('.toolbar-pane.toolbar-actions-pane.active')
      .find('.toolbar-pane-content')
      .find('.toolbar-card')
      .each(($card) => {
        cy.wrap($card)
          .find('.card-title')
          .should('include.text', keyword);
      });
  });
});
