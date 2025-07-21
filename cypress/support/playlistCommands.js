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
// Playlist
Cypress.Commands.add('createNonDynamicPlaylist', (name) => {
  return cy.request({
    method: 'POST',
    url: '/api/playlist',
    headers: {
      Authorization: `Bearer ${Cypress.env('accessToken')}`,
    },
    body: { name },
    form: true,
  }).then((response) => response.body.playlistId);
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