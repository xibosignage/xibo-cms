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

// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This is will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })
/* eslint-disable max-len */
Cypress.Commands.add('clearToolbarPrefs', function() {
  const preference = [];

  preference[0] =
    {
      option: 'toolbar',
      value: JSON.stringify({
        menuItems: {},
        openedMenu: -1,
      }),
    };

  cy.request({
    method: 'POST',
    url: '/api/user/pref',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      preference: preference,
    },
  });
});

/**
 * Force open toolbar menu
 * @param {number} menuIdx
 * @param {boolean} load
 */
Cypress.Commands.add('openToolbarMenu', (menuIdx, load = true) => {
  cy.intercept('GET', '/user/pref?preference=toolbar').as('toolbarPrefsLoad');
  cy.intercept('GET', '/user/pref?preference=editor').as('editorPrefsLoad');
  cy.intercept('POST', '/user/pref?preference=toolbar').as('toolbarPrefsSave');

  if (load) {
    cy.wait('@toolbarPrefsLoad');
    cy.wait('@editorPrefsLoad');
  }

  cy.get('.editor-side-bar').then(($toolbar) => {
    const $submenu = $toolbar.find('#content-' + menuIdx + ' .close-submenu');
    const $menuButton = $toolbar.find('#btn-menu-' + menuIdx);

    if ($submenu.length > 0) {
      cy.log('Just close sub-menu!');
      cy.get('#content-' + menuIdx + ' .close-submenu')
        .should('be.visible')
        .click();
    } else if (!$menuButton.hasClass('active')) {
      cy.log('Open menu!');
      cy.get('[data-test="toolbarTabs"]').eq(menuIdx).click();
    } else {
      cy.log('Do nothing!');
    }
  });
});

/**
 * Force open toolbar menu when we are on playlist editor
 * @param {number} menuIdx
 */
Cypress.Commands.add('openToolbarMenuForPlaylist', function(menuIdx) {
  cy.intercept('POST', '/user/pref').as('toolbarPrefsLoadForPlaylist');

  // Wait for the toolbar to reload when getting prefs at start
  cy.wait('@toolbarPrefsLoadForPlaylist');

  cy.get('.editor-toolbar').then(($toolbar) => {
    if ($toolbar.find('#content-' + menuIdx + ' .close-submenu').length > 0) {
      cy.log('Just close sub-menu!');
      cy.get('.close-submenu').click();
    } else if ($toolbar.find('#btn-menu-' + menuIdx + '.active').length == 0) {
      cy.log('Open menu!');
      cy.get('.editor-main-toolbar #btn-menu-' + menuIdx).click();
    } else {
      cy.log('Do nothing!');
    }
  });
});

Cypress.Commands.add('toolbarSearch', (textToType) => {
  cy.intercept('POST', '/user/pref').as('updatePreferences');

  // Clear the search box first
  cy.get('input#input-name')
    .filter(':visible')
    .should('have.length', 1)
    .invoke('val')
    .then((value) => {
      if (value !== '') {
        cy.get('input#input-name')
          .filter(':visible')
          .clear();
        cy.wait('@updatePreferences');
      }
    });
  // Type keyword to search
  cy.get('input#input-name')
    .filter(':visible')
    .type(textToType);
  cy.wait('@updatePreferences');
});

Cypress.Commands.add('toolbarSearchWithActiveFilter', (textToType) => {
  cy.intercept('POST', '/user/pref').as('updatePreferences');
  cy.intercept('GET', '/library/search*').as('librarySearch');

  // Clear the search box first
  cy.get('input#input-name')
    .filter(':visible')
    .should('have.length', 1)
    .invoke('val')
    .then((value) => {
      if (value !== '') {
        cy.get('input#input-name')
          .filter(':visible')
          .clear();
        cy.wait('@updatePreferences');
        cy.wait('@librarySearch');
      }
    });
  // Type keyword to search
  cy.get('input#input-name')
    .filter(':visible')
    .type(textToType);
  cy.wait('@updatePreferences');
  cy.wait('@librarySearch');
});

Cypress.Commands.add('toolbarFilterByFolder', (folderName, folderId) => {
  cy.intercept('POST', '/user/pref').as('updatePreferences');
  cy.intercept('GET', '/folders?start=0&length=10').as('loadFolders');
  cy.intercept('GET', '/library/search*').as('librarySearch');

  // Open folder dropdown
  cy.get('#input-folder')
    .parent()
    .find('.select2-selection')
    .click();
  cy.wait('@loadFolders');

  // Select the specified folder
  cy.get('.select2-results__option')
    .contains(folderName)
    .should('be.visible')
    .click();

  cy.wait('@updatePreferences');

  // Verify library search response
  cy.wait('@librarySearch').then(({response}) => {
    expect(response.statusCode).to.eq(200);
    expect(response.url).to.include(`folderId=${folderId}`);
  });
});