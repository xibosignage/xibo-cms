/* eslint-disable max-len */
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

describe('Layout Editor', function() {
  beforeEach(function() {
    cy.clearCookies();
    cy.reload();
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

  // it('should navigate to all toolbar tabs', function() {
  //   cy.intercept('POST', '/user/pref').as('updatePreferences');
  //   cy.openToolbarMenu(0, false);

  //   const tabs = [
  //     {tabSelector: '#btn-menu-0', dataTitle: 'Add widgets'},
  //     {tabSelector: '#btn-menu-1', dataTitle: 'Global Elements'},
  //     {tabSelector: '#btn-menu-2', dataTitle: 'Library image search'},
  //     {tabSelector: '#btn-menu-3', dataTitle: 'Library audio search'},
  //     {tabSelector: '#btn-menu-4', dataTitle: 'Library video search'},
  //     {tabSelector: '#btn-menu-5', dataTitle: 'Library other media search'},
  //     {tabSelector: '#btn-menu-6', dataTitle: 'Add Playlists'},
  //     {tabSelector: '#btn-menu-7', dataTitle: 'Interactive actions'},
  //     {tabSelector: '#btn-menu-8', dataTitle: 'Search for Layout Templates'},
  //   ];

  //   // Iterate through each tab
  //   tabs.forEach((tab) => {
  //     // Click the tab
  //     cy.get(tab.tabSelector).click();
  //     cy.wait('@updatePreferences');

  //     // Verify that the active tab has the expected data-title attribute
  //     cy.get(tab.tabSelector)
  //       .should('have.class', 'active')
  //       .and('have.attr', 'data-title', tab.dataTitle);
  //   });
  // });

  it('should navigate to Widgets tab, search and add a widget', function() {
    const keyword = 'Clock';

    cy.openToolbarMenu(0, false);
    cy.toolbarSearch(keyword);
    cy.get('.toolbar-pane-content')
      .find('.toolbar-card')
      .should('have.length.greaterThan', 0);

    // Check if the title of cards contains the keyword
    cy.get('.toolbar-pane-content')
      .find('.toolbar-card')
      .each(($card) => {
        cy.wrap($card)
          .find('.card-title')
          .filter(':visible')
          .should('include.text', keyword);
      });
  });

  it.skip('should navigate to Global Elements tab and search for an element', function() {
    const keyword = 'Text';

    cy.openToolbarMenu(1, false);
    cy.toolbarSearch(keyword);
    cy.get('.toolbar-pane-content')
      .find('.toolbar-card')
      .should('have.length.greaterThan', 0)
      .each(($card) => {
        cy.wrap($card)
          .find('.card-title')
          .invoke('text')
          // .should('include.text', keyword);
          .then((title) => {
            console.log(`Card title found: ${title}`);
            expect(title).to.include(keyword);
          });
      });
  });

  it.skip('should navigate to Library Image Search tab and search for a media', function() {
    const keyword = 'Logo';

    cy.openToolbarMenu(2, false);
    cy.toolbarSearch(keyword);

    // Check if the title of cards contains the keyword
    cy.get('.toolbar-pane-content')
      .find('.toolbar-card[data-type="media"]')
      .each(($card) => {
        cy.wrap($card)
          .find('span.media-title')
          .filter(':visible')
          .should('have.length.greaterThan', 0)
          .and('include.text', keyword);
      });
  });

  it('should navigate to Library Image Search tab and filter media', function() {
    cy.intercept('POST', '/user/pref').as('updatePreferences');
    cy.intercept('GET', '/folders?start=0&length=10').as('loadFolders');
    cy.intercept('GET', '/library/search*').as('librarySearch');
    cy.openToolbarMenu(2, false);

    cy.get('#input-folder')
      .parent()
      .find('.select2-selection')
      .click();
    cy.wait('@loadFolders');
    cy.get('.select2-results__option')
      .contains('FolderWithImage')
      .click();
    cy.wait('@librarySearch').then(({response}) => {
      expect(response.statusCode).to.eq(200);
      expect(response.url).to.include('folderId=7');
    });
    cy.wait('@updatePreferences');
  });

  it.skip('should navigate to Interactive Actions tab and search for actions', function() {
    const keyword = 'Next';

    cy.openToolbarMenu(7, false);
    cy.toolbarSearch(keyword);
    cy.get('.toolbar-pane-content')
      .find('.toolbar-card')
      .each(($card) => {
        cy.wrap($card)
          .find('.card-title')
          .should('include.text', keyword);
      });
  });
});
