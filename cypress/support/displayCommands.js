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

// Display Status
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