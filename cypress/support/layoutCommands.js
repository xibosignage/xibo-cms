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