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

// User Group
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