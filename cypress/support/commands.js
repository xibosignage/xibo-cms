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
Cypress.Commands.add('createDisplaygroup', function(name, isDynamic = false, criteria) {
  // Define the request body object
  const requestBody = {
    displayGroup: name,
  };

  // Add 'isDynamic' to the request body if it's true
  if (isDynamic) {
    requestBody.isDynamic = true;
  }
  // Add 'isDynamic' to the request body if it's true
  if (criteria) {
    requestBody.dynamicCriteria = criteria;
  }

  cy.request({
    method: 'POST',
    url: '/api/displaygroup',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: requestBody,
  }).then((res) => {
    return res.body.displaygroupId;
  });
});

// Delete Display group
Cypress.Commands.add('deleteDisplaygroup', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/displaygroup/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {},
  }).then((res) => {
    return res;
  });
});

// Display Profile
Cypress.Commands.add('createDisplayProfile', function(name, type) {
  cy.request({
    method: 'POST',
    url: '/api/displayprofile',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
      type: type,
    },
  }).then((res) => {
    return res.body.displayProfileId;
  });
});

// Delete display profile
Cypress.Commands.add('deleteDisplayProfile', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/displayprofile/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {},
  }).then((res) => {
    return res;
  });
});

// Dataset
Cypress.Commands.add('createDataset', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/dataset',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      dataSet: name,
    },
  }).then((res) => {
    return res.body.dataSetId;
  });
});

// Delete Dataset
Cypress.Commands.add('deleteDataset', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/dataset/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {},
  }).then((res) => {
    return res;
  });
});

// Sync Group
Cypress.Commands.add('createSyncGroup', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/syncgroup/add',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
      syncPublisherPort: 9590,
    },
  }).then((res) => {
    return res.body.datasetId;
  });
});

// DayPart
Cypress.Commands.add('createDayPart', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/daypart',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
      startTime: '01:00:00',
      endTime: '02:00:00',
    },
  }).then((res) => {
    return res.body.dayPartId;
  });
});

// Delete DayPart
Cypress.Commands.add('deleteDayPart', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/daypart/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {},
  }).then((res) => {
    return res;
  });
});

// Tag
Cypress.Commands.add('createTag', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/tag',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.id;
  });
});


// Menuboard
Cypress.Commands.add('createMenuboard', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/menuboard',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.menuId;
  });
});

// Application
Cypress.Commands.add('createApplication', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/application',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.key;
  });
});

// User
Cypress.Commands.add('createUser', function(name, password, userTypeId, homeFolderId) {
  cy.request({
    method: 'POST',
    url: '/api/user',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      userName: name,
      password: password,
      userTypeId: userTypeId,
      homeFolderId: homeFolderId,
      homePageId: 'icondashboard.view',
    },
  }).then((res) => {
    return res.body.userId;
  });
});

// Delete User
Cypress.Commands.add('deleteUser', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/user/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {},
  }).then((res) => {
    return res;
  });
});

// Usergroup
Cypress.Commands.add('createUsergroup', function(name) {
  cy.request({
    method: 'POST',
    url: '/api/group',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      group: name,
    },
  }).then((res) => {
    return res.body.groupId;
  });
});

// Delete Usergroup
Cypress.Commands.add('deleteUsergroup', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/group/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {},
  }).then((res) => {
    return res;
  });
});

// Menuboard Category
Cypress.Commands.add('createMenuboardCat', function(name, menuId) {
  cy.request({
    method: 'POST',
    url: '/api/menuboard/' + menuId + '/' + 'category',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.menuCategoryId;
  });
});

// Menuboard Category Product
Cypress.Commands.add('createMenuboardCatProd', function(name, menuCatId) {
  cy.request({
    method: 'POST',
    url: '/api/menuboard/' + menuCatId + '/' + 'product',
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {
      name: name,
    },
  }).then((res) => {
    return res.body.menuProductId;
  });
});

// Delete Menuboard
Cypress.Commands.add('deleteMenuboard', function(id) {
  cy.request({
    method: 'DELETE',
    url: '/api/menuboard/' + id,
    form: true,
    headers: {
      Authorization: 'Bearer ' + Cypress.env('accessToken'),
    },
    body: {},
  }).then((res) => {
    return res;
  });
});

/**
 * Open playlist editor modal and wait for toolbar user prefs to load
 * @param {String} playlistName
 */
Cypress.Commands.add('openPlaylistEditorAndLoadPrefs', function(playlistId) {
  cy.intercept('GET', '/user/pref?preference=toolbar').as('userPrefsLoad');

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
  cy.intercept('GET', '/user/pref?preference=toolbar').as('userPrefsLoad');

  cy.clearToolbarPrefs();

  cy.visit('/layout/designer/' + layoutId);

  // Wait for user prefs to load
  cy.wait('@userPrefsLoad');
});

Cypress.Commands.add('removeAllSelectedOptions', (select2) => {
  cy.get(select2)
    .as('select2Container');

  cy.get('@select2Container')
    .then(($select2Container) => {
      if ($select2Container.find('.select2-selection__choice').length > 0) {
        cy.wrap($select2Container)
          .find('.select2-selection__choice')
          .each(($selectedOption) => {
            cy.wrap($selectedOption)
              .find('.select2-selection__choice__remove')
              .click(); // Click on the remove button for each selected option
          });
      } else {
        // No options are selected
        cy.log('No options are selected');
      }
    });
});

// Select an option from the select2
Cypress.Commands.add('selectOption', (content) => {
  cy.get('.select2-container--open').contains(content);
  cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);
  cy.get('.select2-container--open .select2-results > ul > li:first').contains(content).click();
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

// Open Options Menu within the Layout Editor
Cypress.Commands.add('openOptionsMenu', () => {
  cy.get('.navbar-submenu')
    .should('be.visible')
    .within(() => {
      cy.get('#optionsContainerTop')
        .should('be.visible')
        .and('not.be.disabled')
        .click({force: true})
        .should('have.attr', 'aria-expanded', 'true');
    });
});

// Open Row Menu of the first item on the Layouts page
Cypress.Commands.add('openRowMenu', () => {
  cy.get('#layouts tbody tr').first().within(() => {
    cy.get('.btn-group .btn.dropdown-toggle')
      .click()
      .should('have.attr', 'aria-expanded', 'true');
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

/**
 * Update data on CKEditor instance
 * @param {string} ckeditorId
 * @param {string} value
 */
Cypress.Commands.add('updateCKEditor', function(ckeditorId, value) {
  cy.get('textarea[name="' + ckeditorId + '"]').invoke('prop', 'id').then((id) => {
    cy.window().then((win) => {
      win.formHelpers.getCKEditorInstance(
        id,
      ).setData(value);
    });
  });
});
