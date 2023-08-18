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
Cypress.Commands.add('login', function(callbackRoute = '/login') {
  cy.session('saveSession', () => {
    cy.visit(callbackRoute);
    cy.request({
      method: 'POST',
      url: '/login',
      form: true,
      body: {
        username: 'xibo_admin',
        password: 'password',
      },
    }).then((res) => {
      // Get access token and save it as a environment variable
      cy.getAccessToken().then(function() {
        cy.getCookie('PHPSESSID').should('exist');
      });
    });
  });
});

Cypress.Commands.add('getAccessToken', function() {
  cy.request({
    method: 'POST',
    url: '/api/authorize/access_token',
    form: true,
    body: {
      client_id: Cypress.env('client_id'),
      client_secret: Cypress.env('client_secret'),
      grant_type: 'client_credentials',
    },
  }).then((res) => {
    Cypress.env('accessToken', res.body.access_token);
  });
});

Cypress.Commands.add('tutorialClose', function() {
  const csrf_token = Cypress.$('meta[name="token"]').attr('content');

  // Make the ajax request to hide the user welcome tutorial
  Cypress.$.ajax({
    url: '/user/welcome',
    type: 'PUT',
    headers: {
      'X-XSRF-TOKEN': csrf_token,
    },
  });
});

Cypress.Commands.add('formRequest', (method, url, formData) => {
  return new Promise(function(resolve, reject) {
    const xhr = new XMLHttpRequest();

    xhr.open(method, url);
    xhr.setRequestHeader('Authorization', 'Bearer ' + Cypress.env('accessToken'));

    xhr.onload = function() {
      if (this.status >= 200 && this.status < 300) {
        resolve(xhr.response);
      } else {
        reject({
          status: this.status,
          statusText: xhr.statusText,
        });
      }
    };
    xhr.onerror = function() {
      reject({
        status: this.status,
        statusText: xhr.statusText,
      });
    };

    xhr.send(formData);
  });
});

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

// Layout
Cypress.Commands.add('createLayout', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/layout',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
      resolutionId: 1, // HD landscape on the testing build
    },
  }).then((res) => {
    return res.body.layoutId;
  });
});

Cypress.Commands.add('checkoutLayout', function(id) {
  cy.request({
    method: 'PUT',
    url: '/api/layout/checkout/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
  });
});

Cypress.Commands.add('addMediaToLibrary', function(fileName) {
  // Declarations
  const method = 'POST';
  const url = '/api/library';
  const fileType = '*/*';

  // Get file from fixtures as binary
  cy.fixture(fileName, 'binary').then((zipBin) => {
    // File in binary format gets converted to blob so it can be sent as Form data
    const blob = Cypress.Blob.binaryStringToBlob(zipBin, fileType);

    // Build up the form
    const formData = new FormData();

    formData.set('files[]', blob, fileName); // adding a file to the form

    // Perform the request
    return cy.formRequest(method, url, formData).then((res) => {
      const parsedJSON = JSON.parse(res);

      // Return id
      return parsedJSON.files[0].name;
    });
  });
});

Cypress.Commands.add('importLayout', function(fileName) {
  // Declarations
  const method = 'POST';
  const url = '/api/layout/import';
  const fileType = 'application/zip';

  // Get file from fixtures as binary
  cy.fixture(fileName, 'binary').then((zipBin) => {
    // File in binary format gets converted to blob so it can be sent as Form data
    const blob = Cypress.Blob.binaryStringToBlob(zipBin, fileType);

    // Build up the form
    const formData = new FormData();

    // Create random name
    const uuid = Cypress._.random(0, 1e9);

    formData.set('files[]', blob, fileName); // adding a file to the form
    formData.set('name[]', uuid); // adding a name to the form

    // Perform the request
    return cy.formRequest(method, url, formData).then((res) => {
      const parsedJSON = JSON.parse(res);
      // Return id
      return parsedJSON.files[0].id;
    });
  });
});

Cypress.Commands.add('deleteLayout', function(id) {
  cy.request({
    method: 'DELETE',
    failOnStatusCode: false,
    url: '/api/layout/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
  });
});

// Playlist
Cypress.Commands.add('createNonDynamicPlaylist', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/playlist',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.playlistId;
  });
});


Cypress.Commands.add('addWidgetToPlaylist', function(playlistId, widgetType, widgetData) {
  cy.request({
    method: 'POST',
    url: '/api/playlist/widget/' + widgetType + '/' + playlistId,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: widgetData,
  });
});

Cypress.Commands.add('addRandomMediaToPlaylist', function(playlistId) {
  // Get media
  cy.request({
    method: 'GET',
    url: '/api/library?retired=0&assignable=1&start=0&length=1',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
  }).then((res) => {
    const media = [];
    media.push(res.body[0].mediaId);

    // Add media to playlist
    cy.request({
      method: 'POST',
      url: '/api/playlist/library/assign/' + playlistId,
      form: true,
      headers: {
        Authorization: 'Bearer ' + Cypress.env('accessToken'),
      },
      body: {
        media: media,
      },
    });
  });
});

Cypress.Commands.add('deletePlaylist', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/playlist/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
  });
});

// Campaign
Cypress.Commands.add('createCampaign', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/campaign',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.campaignId;
  });
});

// Display Group
Cypress.Commands.add('createDisplaygroup', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/displaygroup',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      displayGroup: name,
    },
  }).then((res) => {
    return res.body.displaygroupId;
  });
});

/**
 * Open playlist editor modal and wait for toolbar user prefs to load
 * @param {String} playlistName
 */
Cypress.Commands.add('openPlaylistEditorAndLoadPrefs', function(playlistId) {
  cy.server();
  cy.route('/user/pref?preference=toolbar').as('userPrefsLoad');

  // Reload playlist table page
  cy.visit('/playlist/view');

  // Clear toolbar preferences
  cy.clearToolbarPrefs();

  cy.window().then((win) => {
    win.XiboCustomFormRender(win.$('<li class="XiboCustomFormButton playlist_timeline_button_edit" href="/playlist/form/timeline/' + playlistId + '"></li>'));

    // Wait for user prefs to load
    cy.wait('@userPrefsLoad');
  });
});

/**
 * Add media items to library
 */
Cypress.Commands.add('populateLibraryWithMedia', function() {
  // Add audio media to library
  cy.addMediaToLibrary('../assets/audioSample.mp3');

  // Add image media to library
  cy.addMediaToLibrary('../assets/imageSample.png');
});

/**
 * Drag one element to another one
 * @param {string} draggableSelector
 * @param {string} dropableSelector
 */
Cypress.Commands.add('dragToElement', function(draggableSelector, dropableSelector) {
  return cy.get(dropableSelector).then(($el) => {
    const position = {
      x: $el.offset().left + $el.width() / 2 + window.scrollX,
      y: $el.offset().top + $el.height() / 2 + window.scrollY,
    };

    cy.get(draggableSelector).invoke('show');

    cy.get(draggableSelector)
      .trigger('mousedown', {
        which: 1,
      })
      .trigger('mousemove', {
        which: 1,
        pageX: position.x,
        pageY: position.y,
      })
      .trigger('mouseup');
  });
});

/**
 * Go to layout editor page and wait for toolbar user prefs to load
 * @param {number} layoutId
 */
Cypress.Commands.add('goToLayoutAndLoadPrefs', function(layoutId) {
  cy.server();
  cy.route('/user/pref?preference=toolbar').as('userPrefsLoad');

  cy.clearToolbarPrefs();

  cy.visit('/layout/designer/' + layoutId);

  // Wait for user prefs to load
  cy.wait('@userPrefsLoad');
});

// Schedule a layout
Cypress.Commands.add('scheduleCampaign', function(campaignId, displayName) {
  cy.request({
    method: 'POST',
    url: '/api/scheduleCampaign',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      campaignId: campaignId,
      displayName: displayName,
    },
  }).then((res) => {
    return res.body.eventId;
  });
});

// Create a campaign
Cypress.Commands.add('createCampaign', function(campaignName) {
  cy.request({
    method: 'POST',
    url: '/api/createCampaign',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: campaignName,
    },
  }).then((res) => {
    return res.body.campaignId;
  });
});

// Create a command
Cypress.Commands.add('createCommand', function(name, description, code) {
  cy.request({
    method: 'POST',
    url: '/api/createCommand',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      command: name,
      description: description,
      code: code,
    },
  }).then((res) => {
    return res.body.commandId;
  });
});

//  Set Display Status
Cypress.Commands.add('displaySetStatus', function(displayName, statusId) {
  cy.request({
    method: 'POST',
    url: '/api/displaySetStatus',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      displayName: displayName,
      statusId: statusId,
    },
  }).then((res) => {
    return res.body;
  });
});

// Check Display Status
Cypress.Commands.add('displayStatusEquals', function(displayName, statusId) {
  cy.request({
    method: 'GET',
    url: '/api/displayStatusEquals',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      displayName: displayName,
      statusId: statusId,
    },
  }).then((res) => {
    return res;
  });
});

/**
 * Force open toolbar menu
 * @param {number} menuIdx
 */
Cypress.Commands.add('openToolbarMenu', function(menuIdx) {
  cy.intercept('GET', '/user/pref?preference=toolbar').as('toolbarPrefsLoad');
  cy.intercept('GET', '/user/pref?preference=editor').as('editorPrefsLoad');
  cy.intercept('POST', '/user/pref?preference=toolbar').as('toolbarPrefsLoad');

  // Wait for the toolbar to reload when getting prefs at start
  cy.wait('@toolbarPrefsLoad');
  cy.wait('@editorPrefsLoad');

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
